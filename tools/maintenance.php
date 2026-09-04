<?php
/**
 * Periodic maintenance. Run if auto_maintenance is off.
 */

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit('Not Found');
}

require dirname(__FILE__) . '/inc/cli.php';

echo "Clearing expired bans...\n";
$start = microtime(true);
$deleted_count = Bans::purge($config['require_ban_view'], $config['purge_bans']);
$delta = microtime(true) - $start;
echo "Deleted $deleted_count expired bans in $delta seconds!\n";
$time_tot = $delta;
$deleted_tot = $deleted_count;

if ($config['cache']['enabled'] === 'fs') {
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
}

$time_tot = number_format((float)$time_tot, 4, '.', '');
modLog("Deleted $deleted_tot expired bans in {$time_tot}s with maintenance tool");
