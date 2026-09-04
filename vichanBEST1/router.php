<?php
/**
 * Front controller for PHP's built-in server:
 *   php -S 127.0.0.1:8080 router.php
 *
 * Serves static files as-is; blocks sensitive paths; routes PHP entry points.
 */

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');
$file = __DIR__ . $uri;

// Block sensitive paths
$blocked = [
	'#^/(inc|vendor|tools|tmp|\.git)(/|$)#i',
	'#/(composer\.(json|lock|phar)|install\.pgsql\.sql|\.installed)$#i',
	'#/\.#',
];

foreach ($blocked as $re) {
	if (preg_match($re, $uri)) {
		http_response_code(404);
		header('Content-Type: text/plain; charset=utf-8');
		echo 'Not Found';
		return true;
	}
}

// Existing static file or directory index
if ($uri !== '/' && file_exists($file)) {
	if (is_dir($file)) {
		foreach (['index.html', 'index.php'] as $idx) {
			if (is_file(rtrim($file, '/\\') . DIRECTORY_SEPARATOR . $idx)) {
				if (str_ends_with($idx, '.php')) {
					require rtrim($file, '/\\') . DIRECTORY_SEPARATOR . $idx;
					return true;
				}
				return false; // let built-in server serve HTML
			}
		}
	}
	// Let the built-in server handle static assets
	if (!str_ends_with(strtolower($uri), '.php')) {
		return false;
	}
	// Executable PHP under document root (front controllers only)
	$base = basename($uri);
	$allowed_php = [
		'post.php', 'mod.php', 'report.php',
		'securimage.php',
	];
	if (in_array($base, $allowed_php, true)) {
		require $file;
		return true;
	}
	http_response_code(404);
	echo 'Not Found';
	return true;
}

// No file: 404 for unknown paths (boards are static HTML after build)
http_response_code(404);
header('Content-Type: text/plain; charset=utf-8');
echo 'Not Found';
return true;
