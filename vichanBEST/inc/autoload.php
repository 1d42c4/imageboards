<?php
/**
 * Composer-free autoloader for this fork.
 */

spl_autoload_register(static function (string $class): void {
	// PSR-ish: Vichan\Foo\Bar -> inc/Foo/Bar.php or inc/Data/...
	if (str_starts_with($class, 'Vichan\\')) {
		$rel = str_replace('\\', '/', substr($class, strlen('Vichan\\')));
		$candidates = [
			__DIR__ . '/' . $rel . '.php',
			__DIR__ . '/Data/' . preg_replace('#^Data/#', '', $rel) . '.php',
		];
		// Map Vichan\Data\Driver\X -> inc/Data/Driver/X.php
		$path = __DIR__ . '/' . $rel . '.php';
		if (is_readable($path)) {
			require_once $path;
			return;
		}
		// Functions namespace files already loaded via requires
		return;
	}

	// Legacy / bundled libs
	$map = [
		'Lifo\\IP\\CIDR' => __DIR__ . '/lib/CIDR.php',
		'Lifo\\IP\\IP' => __DIR__ . '/lib/CIDR.php',
		'Lifo\\IP\\BC' => __DIR__ . '/lib/CIDR.php',
	];
	if (isset($map[$class]) && is_readable($map[$class])) {
		require_once $map[$class];
	}
});

// Core includes (order matters for some globals)
$__vichan_core = [
	__DIR__ . '/security.php',
	__DIR__ . '/events.php',
	__DIR__ . '/database.php',
	__DIR__ . '/bans.php',
	__DIR__ . '/functions/format.php',
	__DIR__ . '/functions/net.php',
	__DIR__ . '/functions/num.php',
	__DIR__ . '/functions/theme.php',
	__DIR__ . '/display.php',
	__DIR__ . '/template.php',
	__DIR__ . '/cache.php',
	__DIR__ . '/mod/auth.php',
	__DIR__ . '/context.php',
	__DIR__ . '/service/captcha-queries.php',
	__DIR__ . '/catalog.php',
	__DIR__ . '/archive.php',
	__DIR__ . '/board_moderation.php',
	__DIR__ . '/homepage.php',
	__DIR__ . '/functions.php',
];

foreach ($__vichan_core as $__f) {
	if (is_readable($__f)) {
		require_once $__f;
	}
}
unset($__f, $__vichan_core);
