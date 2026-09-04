<?php

declare(strict_types=1);

namespace Newboard;

/**
 * Zero-dependency PSR-4-ish autoloader for Newboard\ → src/.
 */
final class Autoload
{
    public static function register(string $srcDir): void
    {
        $srcDir = rtrim($srcDir, '/\\') . DIRECTORY_SEPARATOR;

        spl_autoload_register(static function (string $class) use ($srcDir): void {
            $prefix = 'Newboard\\';
            if (!str_starts_with($class, $prefix)) {
                return;
            }
            $relative = str_replace('\\', DIRECTORY_SEPARATOR, substr($class, strlen($prefix)));
            $file = $srcDir . $relative . '.php';
            if (is_readable($file)) {
                require_once $file;
            }
        });
    }
}
