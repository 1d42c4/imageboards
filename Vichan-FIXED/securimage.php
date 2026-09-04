<?php
/**
 * Native captcha endpoint (JSON for JS, optional PNG for noscript).
 * Modes: get (JSON), get+raw=1 (PNG + Set-Cookie), check (validate).
 */
require_once 'inc/bootstrap.php';
loadConfig();

use function Vichan\Service\{native_captcha_create, native_captcha_verify, native_captcha_draw};
use const Vichan\Service\NATIVE_CAPTCHA_TTL;

$extra = $_GET['extra'] ?? $_POST['extra'] ?? ($config['captcha']['native']['extra'] ?? '');
$mode = $_GET['mode'] ?? '';

switch ($mode) {
	case 'get':
		$challenge = native_captcha_create((string)$extra);

		// Noscript / raw: set cookie so POST can recover captcha_cookie without JS fields
		if (isset($_GET['raw'])) {
			$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
				|| (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);
			setcookie('captcha_cookie', $challenge['cookie'], [
				'expires' => time() + NATIVE_CAPTCHA_TTL,
				'path' => $config['cookies']['path'] ?? '/',
				'secure' => $secure,
				'httponly' => true,
				'samesite' => 'Strict',
			]);
			// Decode data URI for raw PNG body
			$raw = base64_decode(substr($challenge['image'], strlen('data:image/png;base64,')), true);
			if ($raw === false) {
				$raw = native_captcha_draw($challenge['text']);
			}
			header('Content-Type: image/png');
			header('Cache-Control: no-store, no-cache, must-revalidate');
			echo $raw;
			break;
		}

		header('Content-Type: application/json; charset=utf-8');
		header('Cache-Control: no-store, no-cache, must-revalidate');
		echo json_encode([
			'cookie' => $challenge['cookie'],
			'captchahtml' => $challenge['html'],
			'image' => $challenge['image'],
		]);
		break;

	case 'check':
		header('Content-Type: text/plain; charset=utf-8');
		$cookie = $_GET['cookie'] ?? $_POST['cookie'] ?? '';
		$text = $_GET['text'] ?? $_POST['text'] ?? '';
		$ok = native_captcha_verify((string)$text, (string)$cookie, (string)$extra);
		echo $ok ? '1' : '0';
		break;

	default:
		http_response_code(400);
		echo 'Bad mode';
}
