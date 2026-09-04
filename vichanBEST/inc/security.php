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
	header('Referrer-Policy: strict-origin-when-cross-origin');
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

/**
 * Whether real client IPs may be written to the database / logs.
 * When false, posts, reports, mod logs, notes, etc. never store REMOTE_ADDR.
 */
function privacy_store_ip(): bool {
	global $config;
	return !empty($config['privacy']['store_ip']);
}

/**
 * Value written to DB columns that historically held IPs.
 * Empty string when privacy mode is on — never the real address.
 */
function get_ip_for_storage(): string {
	if (!privacy_store_ip()) {
		return '';
	}
	return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * Short-lived flood/rate-limit key. In privacy mode this is an irreversible
 * HMAC of the request IP (not recoverable as an address if the DB leaks).
 * Length kept within typical varchar(39) IP columns.
 */
function get_flood_key(): string {
	global $config;
	if (privacy_store_ip()) {
		return get_ip_for_storage();
	}
	$salt = $config['cookies']['salt'] ?? ($config['secure_trip_salt'] ?? 'vichan-privacy');
	$raw = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
	// Prefix marks non-IP tokens so nothing treats them as addresses.
	return 'x' . substr(hash_hmac('sha256', $raw, $salt), 0, 38);
}

/**
 * Human-facing IP string for mod UI / ban pages.
 */
function get_ip_for_display(?string $stored = null): string {
	if (!privacy_store_ip()) {
		return ''; // no IP chrome when storage is off
	}
	if ($stored !== null && $stored !== '') {
		return cloak_ip($stored);
	}
	return cloak_ip($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
}

/**
 * Apply privacy-related defaults after config is fully loaded.
 */
function privacy_apply_runtime_defaults(): void {
	global $config;
	if (privacy_store_ip()) {
		return;
	}
	// No GeoIP → country flags from visitor address
	$config['country_flags'] = false;
	// IP-locked mod sessions still use request IP in-memory only (not post storage)
}
