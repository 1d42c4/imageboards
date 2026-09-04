<?php

/*
 *  Cache — Array (request), filesystem, or none. No Redis/Memcached/APCu.
 */

use Vichan\Data\Driver\{CacheDriver, ArrayCacheDriver, FsCacheDriver, NoneCacheDriver};

defined('TINYBOARD') or exit;


class Cache {
	private static function buildCache(): CacheDriver {
		global $config;
		$engine = \getenv('VICHAN_CACHE_ENGINE') ?: $config['cache']['enabled'];

		switch ($engine) {
			case 'fs':
				return new FsCacheDriver(
					$config['cache']['prefix'],
					"tmp/cache/{$config['cache']['prefix']}",
					'.lock',
					$config['auto_maintenance'] ? 1000 : false
				);
			case 'none':
				return new NoneCacheDriver();
			case 'php':
			case true:
			case 'true':
			case '1':
			default:
				// In-request array cache (or when enabled is a truthy unknown value)
				if ($engine === false || $engine === 'false' || $engine === '0' || $engine === 0 || $engine === '') {
					return new NoneCacheDriver();
				}
				return new ArrayCacheDriver();
		}
	}

	public static function getCache(): CacheDriver {
		static $cache;
		return $cache ??= self::buildCache();
	}

	public static function get($key) {
		global $config, $debug;

		$ret = self::getCache()->get($key);
		if ($ret === null) {
			$ret = false;
		}

		if ($config['debug']) {
			$debug['cached'][] = $config['cache']['prefix'] . $key . ($ret === false ? ' (miss)' : ' (hit)');
		}

		return $ret;
	}

	public static function set($key, $value, $expires = false) {
		global $config, $debug;

		if (!$expires) {
			$expires = $config['cache']['timeout'];
		}

		self::getCache()->set($key, $value, $expires);

		if ($config['debug']) {
			$debug['cached'][] = $config['cache']['prefix'] . $key . ' (set)';
		}
	}

	public static function delete($key) {
		global $config, $debug;

		self::getCache()->delete($key);

		if ($config['debug']) {
			$debug['cached'][] = $config['cache']['prefix'] . $key . ' (deleted)';
		}
	}

	public static function flush() {
		self::getCache()->flush();
		return false;
	}
}
