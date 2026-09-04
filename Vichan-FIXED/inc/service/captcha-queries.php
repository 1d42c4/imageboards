<?php // Verify captchas server side.
namespace Vichan\Service;

use Vichan\Data\Driver\HttpDriver;

defined('TINYBOARD') or exit;

const NATIVE_CAPTCHA_TTL = 120;

/**
 * Create a native captcha challenge (shared by securimage.php and report form).
 *
 * @return array{cookie:string,text:string,html:string,image:string}
 */
function native_captcha_create(string $extra = ''): array {
	$charset_cookie = 'abcdefghijklmnopqrstuvwxyz';
	$charset_code = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
	$cookie = native_captcha_rand(20, $charset_cookie);
	$code = native_captcha_rand(5, $charset_code);
	$raw = native_captcha_draw($code);
	$b64 = 'data:image/png;base64,' . base64_encode($raw);

	$query = \prepare('INSERT INTO ``captchas`` (`cookie`, `extra`, `text`, `created_at`) VALUES (:c, :e, :t, :a)');
	$query->bindValue(':c', $cookie);
	$query->bindValue(':e', $extra);
	$query->bindValue(':t', $code);
	$query->bindValue(':a', time(), \PDO::PARAM_INT);
	$query->execute() or \error(\db_error($query));
	native_captcha_cleanup(NATIVE_CAPTCHA_TTL);

	return [
		'cookie' => $cookie,
		'text' => $code,
		'html' => '<img src="' . $b64 . '" alt="captcha">',
		'image' => $b64,
	];
}

/**
 * Verify and consume a native captcha (in-process — no HTTP loopback).
 */
function native_captcha_verify(string $user_text, string $user_cookie, string $extra = '', int $expires_in = NATIVE_CAPTCHA_TTL): bool {
	if ($user_text === '' || $user_cookie === '') {
		return false;
	}
	$query = \prepare('SELECT `text` FROM ``captchas`` WHERE `cookie` = :c AND `extra` = :e AND `created_at` >= :t');
	$query->bindValue(':c', $user_cookie);
	$query->bindValue(':e', $extra);
	$query->bindValue(':t', time() - $expires_in, \PDO::PARAM_INT);
	$query->execute() or \error(\db_error($query));
	$row = $query->fetch(\PDO::FETCH_ASSOC);
	$ok = $row && strcasecmp((string)$row['text'], (string)$user_text) === 0;
	if ($ok) {
		$del = \prepare('DELETE FROM ``captchas`` WHERE `cookie` = :c');
		$del->bindValue(':c', $user_cookie);
		$del->execute();
	}
	native_captcha_cleanup($expires_in);
	return (bool)$ok;
}

function native_captcha_cleanup(int $expires_in = NATIVE_CAPTCHA_TTL): void {
	$q = \prepare('DELETE FROM ``captchas`` WHERE `created_at` < :t');
	$q->bindValue(':t', time() - $expires_in, \PDO::PARAM_INT);
	$q->execute();
}

function native_captcha_rand(int $length, string $charset): string {
	$ret = '';
	$max = mb_strlen($charset, 'utf-8') - 1;
	for ($i = 0; $i < $length; $i++) {
		$ret .= mb_substr($charset, random_int(0, $max), 1, 'utf-8');
	}
	return $ret;
}

function native_captcha_draw(string $code): string {
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

class ReCaptchaQuery implements RemoteCaptchaQuery {
	private HttpDriver $http;
	private string $secret;

	public function __construct(HttpDriver $http, string $secret) {
		$this->http = $http;
		$this->secret = $secret;
	}

	public function responseField(): string {
		return 'g-recaptcha-response';
	}

	public function verify(string $response): bool {
		$data = [
			'secret' => $this->secret,
			'response' => $response
		];
		$ret = $this->http->requestGet('https://www.google.com/recaptcha/api/siteverify', $data);
		$resp = json_decode($ret, true, 16, JSON_THROW_ON_ERROR);
		return isset($resp['success']) && $resp['success'];
	}
}

class HCaptchaQuery implements RemoteCaptchaQuery {
	private HttpDriver $http;
	private string $secret;
	private string $sitekey;

	public function __construct(HttpDriver $http, string $secret, string $sitekey) {
		$this->http = $http;
		$this->secret = $secret;
		$this->sitekey = $sitekey;
	}

	public function responseField(): string {
		return 'h-captcha-response';
	}

	public function verify(string $response): bool {
		$data = [
			'secret' => $this->secret,
			'response' => $response,
			'sitekey' => $this->sitekey
		];
		$ret = $this->http->requestGet('https://hcaptcha.com/siteverify', $data);
		$resp = json_decode($ret, true, 16, JSON_THROW_ON_ERROR);
		return isset($resp['success']) && $resp['success'];
	}
}

interface RemoteCaptchaQuery {
	public function responseField(): string;

	/** @throws RuntimeException|JsonException */
	public function verify(string $response): bool;
}

/** Native captcha: verifies against the DB in-process (no self-HTTP). */
class NativeCaptchaQuery {
	private string $extra;

	public function __construct(string $extra = '') {
		$this->extra = $extra;
	}

	public function verify(string $user_text, string $user_cookie): bool {
		return native_captcha_verify($user_text, $user_cookie, $this->extra);
	}
}
