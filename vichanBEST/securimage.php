<?php
/**
 * Simple GD captcha endpoint (replaces dapphp/securimage dependency).
 * Modes: get (JSON image+cookie), check (validate).
 */
require_once 'inc/bootstrap.php';
loadConfig();

$expires_in = 120;

function captcha_rand_string(int $length, string $charset): string {
	$ret = '';
	$max = mb_strlen($charset, 'utf-8') - 1;
	for ($i = 0; $i < $length; $i++) {
		$ret .= mb_substr($charset, random_int(0, $max), 1, 'utf-8');
	}
	return $ret;
}

function captcha_cleanup(int $expires_in): void {
	$q = prepare('DELETE FROM ``captchas`` WHERE `created_at` < :t');
	$q->bindValue(':t', time() - $expires_in, PDO::PARAM_INT);
	$q->execute();
}

function captcha_draw_code(string $code): string {
	$w = 140;
	$h = 50;
	$im = imagecreatetruecolor($w, $h);
	$bg = imagecolorallocate($im, 245, 245, 245);
	$fg = imagecolorallocate($im, 20, 20, 20);
	$noise = imagecolorallocate($im, 180, 180, 180);
	imagefilledrectangle($im, 0, 0, $w, $h, $bg);
	for ($i = 0; $i < 80; $i++) {
		imagesetpixel($im, random_int(0, $w - 1), random_int(0, $h - 1), $noise);
	}
	for ($i = 0; $i < 5; $i++) {
		imageline($im, random_int(0, $w), random_int(0, $h), random_int(0, $w), random_int(0, $h), $noise);
	}
	imagestring($im, 5, 28, 16, $code, $fg);
	ob_start();
	imagepng($im);
	$data = ob_get_clean();
	imagedestroy($im);
	return $data;
}

$mode = $_GET['mode'] ?? '';
switch ($mode) {
	case 'get':
		if (!isset($_GET['extra'])) {
			$_GET['extra'] = $config['captcha']['extra'] ?? '';
		}
		header('Content-Type: application/json; charset=utf-8');
		$extra = $_GET['extra'];
		$cookie = captcha_rand_string(20, 'abcdefghijklmnopqrstuvwxyz');
		$code = captcha_rand_string(5, 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789');
		$raw = captcha_draw_code($code);
		$b64 = 'data:image/png;base64,' . base64_encode($raw);
		$query = prepare('INSERT INTO ``captchas`` (`cookie`, `extra`, `text`, `created_at`) VALUES (:c, :e, :t, :a)');
		$query->bindValue(':c', $cookie);
		$query->bindValue(':e', $extra);
		$query->bindValue(':t', $code);
		$query->bindValue(':a', time(), PDO::PARAM_INT);
		$query->execute() or error(db_error($query));
		captcha_cleanup($expires_in);
		echo json_encode([
			'cookie' => $cookie,
			'captchahtml' => '<img src="' . $b64 . '" alt="captcha">',
			'image' => $b64,
		]);
		break;

	case 'check':
		header('Content-Type: text/plain; charset=utf-8');
		$cookie = $_GET['cookie'] ?? $_POST['cookie'] ?? '';
		$text = $_GET['text'] ?? $_POST['text'] ?? '';
		$extra = $_GET['extra'] ?? $_POST['extra'] ?? ($config['captcha']['extra'] ?? '');
		$query = prepare('SELECT `text` FROM ``captchas`` WHERE `cookie` = :c AND `extra` = :e AND `created_at` >= :t');
		$query->bindValue(':c', $cookie);
		$query->bindValue(':e', $extra);
		$query->bindValue(':t', time() - $expires_in, PDO::PARAM_INT);
		$query->execute() or error(db_error($query));
		$row = $query->fetch(PDO::FETCH_ASSOC);
		$ok = $row && strcasecmp((string)$row['text'], (string)$text) === 0;
		if ($ok) {
			$del = prepare('DELETE FROM ``captchas`` WHERE `cookie` = :c');
			$del->bindValue(':c', $cookie);
			$del->execute();
		}
		echo $ok ? '1' : '0';
		captcha_cleanup($expires_in);
		break;

	default:
		http_response_code(400);
		echo 'Bad mode';
}
