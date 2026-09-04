<?php
/**
 * Template entrypoint — PHP view engine (no Twig/Composer).
 */
defined('TINYBOARD') or exit;

require_once __DIR__ . '/view.php';

/** Delete compiled templates under templates/cache_php/. */
function clear_template_cache(): void {
	global $config;
	$base = $config['dir']['template'] ?? (getcwd() . '/templates');
	$dir = rtrim($base, '/\\') . DIRECTORY_SEPARATOR . 'cache_php';
	if (!is_dir($dir)) {
		return;
	}
	$it = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
		RecursiveIteratorIterator::CHILD_FIRST
	);
	foreach ($it as $f) {
		$f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
	}
}

function Element($templateFile, array $options) {
	global $config, $debug, $build_pages;

	if (isset($options['body']) && !empty($config['debug'])) {
		$_debug = $debug;
		if (isset($debug['start'])) {
			$_debug['time']['total'] = '~' . round((microtime(true) - $_debug['start']) * 1000, 2) . 'ms';
			$_debug['time']['init'] = '~' . round(($_debug['start_debug'] - $_debug['start']) * 1000, 2) . 'ms';
			unset($_debug['start'], $_debug['start_debug']);
		}
		if (!empty($config['try_smarter']) && isset($build_pages) && !empty($build_pages)) {
			$_debug['build_pages'] = $build_pages;
		}
		$_debug['included'] = get_included_files();
		$_debug['memory'] = round(memory_get_usage(true) / (1024 * 1024), 2) . ' MiB';
		if (isset($_debug['time']['db_queries'])) {
			$_debug['time']['db_queries'] = '~' . round($_debug['time']['db_queries'] * 1000, 2) . 'ms';
		}
		if (isset($_debug['time']['exec'])) {
			$_debug['time']['exec'] = '~' . round($_debug['time']['exec'] * 1000, 2) . 'ms';
		}
		$options['body'] .=
			'<h3>Debug</h3><pre style="white-space: pre-wrap;font-size: 10px;">' .
				str_replace("\n", '<br/>', utf8tohtml(print_r($_debug, true))) .
			'</pre>';
	}

	return view_render($templateFile, $options);
}

// --- helpers for view_filter / templates ---

function twig_date_filter($date, $format) {
	if ($date === null || $date === '') {
		return '';
	}
	// Templates use strftime-like % formats; map to PHP date() tokens.
	$format = str_replace(
		['%Y', '%y', '%m', '%d', '%H', '%M', '%S', '%b', '%B', '%a', '%A', '%p', '%I'],
		['Y',  'y',  'm',  'd',  'H',  'i',  's',  'M',  'F',  'D',  'l',  'A',  'h'],
		$format
	);
	if (is_numeric($date)) {
		$dt = new DateTime('@' . (int)$date, new DateTimeZone('UTC'));
	} else {
		try {
			$dt = new DateTime((string)$date, new DateTimeZone('UTC'));
		} catch (Exception $e) {
			return '';
		}
	}
	if (!empty($GLOBALS['config']['timezone'])) {
		try {
			$dt->setTimezone(new DateTimeZone($GLOBALS['config']['timezone']));
		} catch (Exception $e) {
			// keep UTC
		}
	}
	return $dt->format($format);
}

function twig_hasPermission_filter($mod, $permission, $board = null) {
	return hasPermission($permission, $board, $mod);
}

function twig_extension_filter($value, $case_insensitive = true) {
	$ext = mb_substr((string)$value, mb_strrpos((string)$value, '.') + 1);
	if ($case_insensitive) {
		$ext = mb_strtolower($ext);
	}
	return $ext;
}

function twig_truncate_filter($value, $length = 30, $preserve = false, $separator = '…') {
	$value = (string)$value;
	if (mb_strlen($value) > $length) {
		if ($preserve) {
			if (false !== ($breakpoint = mb_strpos($value, ' ', $length))) {
				$length = $breakpoint;
			}
		}
		return mb_substr($value, 0, $length) . $separator;
	}
	return $value;
}

function twig_filename_truncate_filter($value, $length = 30, $separator = '…') {
	$value = (string)$value;
	if (mb_strlen($value) > $length) {
		$value = strrev($value);
		$array = array_reverse(explode('.', $value, 2));
		$array = array_map('strrev', $array);
		$filename = &$array[0];
		$extension = isset($array[1]) ? $array[1] : false;
		$filename = mb_substr($filename, 0, $length - ($extension ? mb_strlen($extension) + 1 : 0)) . $separator;
		return implode('.', $array);
	}
	return $value;
}

function twig_ratio_function($w, $h) {
	return fraction($w, $h, ':');
}

function twig_secure_link_confirm($text, $title, $confirm_message, $href) {
	if ($href === null || $href === '') {
		return '';
	}
	$href = (string)$href;
	$token = make_secure_link_token($href);
	return '<a onclick="if (event.which==2) return true;if (confirm(\'' . htmlentities(addslashes((string)$confirm_message)) . '\')) document.location=\'?/' . htmlspecialchars(addslashes($href . '/' . $token)) . '\';return false;" title="' . htmlentities((string)$title) . '" href="?/' . htmlspecialchars($href) . '">' . $text . '</a>';
}

function twig_secure_link($href) {
	if ($href === null || $href === '') {
		return '';
	}
	$href = (string)$href;
	return $href . '/' . make_secure_link_token($href);
}

function twig_check_container() {
	static $is_container = null;
	if ($is_container === null) {
		$is_docker = is_file('/.dockerenv') || is_file('/run/.containerenv');
		$is_kubernetes = is_file('/var/run/secrets/kubernetes.io/serviceaccount/namespace');
		$is_container = $is_docker || $is_kubernetes;
	}
	return $is_container;
}
