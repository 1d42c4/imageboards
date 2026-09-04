<?php
/**
 * Periodic maintenance. Run if auto_maintenance is off.
 * Collects optional filesystem cache.
 */

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit('Not Found');
}

require dirname(__FILE__) . '/inc/cli.php';

$time_tot = 0.0;

if (($config['cache']['enabled'] ?? false) === 'fs') {
	$fs_cache = new Vichan\Data\Driver\FsCacheDriver(
		$config['cache']['prefix'],
		"tmp/cache/{$config['cache']['prefix']}",
		'.lock',
		false
	);
	$start = microtime(true);
	$fs_cache->collect();
	$delta = microtime(true) - $start;
	echo "Collected filesystem cache in $delta seconds!\n";
	$time_tot += $delta;
} else {
	echo "No filesystem cache to collect (cache.enabled is not 'fs').\n";
}

$time_tot = number_format((float)$time_tot, 4, '.', '');
echo "Maintenance finished in {$time_tot}s\n";
if (function_exists('modLog')) {
	modLog("Ran maintenance tool in {$time_tot}s");
}
