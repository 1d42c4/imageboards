<?php
/**
 * Security headers and request hardening.
 */

defined('TINYBOARD') or exit;

if (function_exists('security_send_headers')) {
	return;
}

/**
 * Send baseline security headers for HTML/API responses.
 * Safe to call multiple times; only sends if headers not already sent.
 */
function security_send_headers(): void {
	if (headers_sent()) {
		return;
	}

	header('X-Content-Type-Options: nosniff');
	header('X-Frame-Options: SAMEORIGIN');
	// same-origin keeps full path for our own forms; strips referer when leaving the site
	header('Referrer-Policy: same-origin');
	header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
	// Conservative CSP — allows inline scripts used by templates/legacy JS
	header(
		"Content-Security-Policy: default-src 'self'; " .
		"script-src 'self' 'unsafe-inline'; " .
		"style-src 'self' 'unsafe-inline'; " .
		"img-src 'self' data: blob: https:; " .
		"media-src 'self' blob:; " .
		"font-src 'self' data:; " .
		"frame-ancestors 'self'; " .
		"base-uri 'self'; " .
		"form-action 'self'"
	);

	// Only enable HSTS when the request is already HTTPS
	$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
		|| (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
		|| (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
	if ($https) {
		header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
	}
}

/**
 * Reject obviously hostile request URIs early.
 */
function security_reject_bad_request(): void {
	$uri = $_SERVER['REQUEST_URI'] ?? '';
	// Path traversal / null bytes
	if ($uri !== '' && (str_contains($uri, "\0") || str_contains($uri, '..'))) {
		http_response_code(400);
		header('Content-Type: text/plain; charset=utf-8');
		echo 'Bad Request';
		exit;
	}
}

