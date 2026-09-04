<?php

declare(strict_types=1);

/** @return array{0: float, 1: float, 2: float} */
function theme_rgb(string $hex): array
{
    $hex = ltrim($hex, '#');
    if (strlen($hex) === 3) {
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }

    return [
        hexdec(substr($hex, 0, 2)) / 255,
        hexdec(substr($hex, 2, 2)) / 255,
        hexdec(substr($hex, 4, 2)) / 255,
    ];
}

/** @param array{0: float, 1: float, 2: float} $rgb */
function theme_luminance(array $rgb): float
{
    $linear = static fn (float $value): float => $value <= 0.04045
        ? $value / 12.92
        : (($value + 0.055) / 1.055) ** 2.4;

    return 0.2126 * $linear($rgb[0]) + 0.7152 * $linear($rgb[1]) + 0.0722 * $linear($rgb[2]);
}

function theme_contrast(string $first, string $second): float
{
    $a = theme_luminance(theme_rgb($first));
    $b = theme_luminance(theme_rgb($second));
    $lighter = max($a, $b);
    $darker = min($a, $b);

    return ($lighter + 0.05) / ($darker + 0.05);
}

$root = dirname(__DIR__);
$themeDirectory = $root . '/public/assets/css/themes';
$catalog = require $root . '/config/themes.php';
if (!is_array($catalog)) {
    fwrite(STDERR, "config/themes.php must return an array.\n");
    exit(1);
}

$contrastPairs = [
    ['--theme-text', '--theme-bg'],
    ['--theme-text', '--theme-panel'],
    ['--theme-link', '--theme-bg'],
    ['--theme-link', '--theme-panel'],
    ['--theme-accent-text', '--theme-accent'],
    ['--theme-text', '--theme-input'],
];

$failed = false;
$seen = [];
$contrastChecks = 0;
foreach ($catalog as $group => $themes) {
    if (!is_string($group) || !is_array($themes)) {
        fwrite(STDERR, "Invalid theme group.\n");
        $failed = true;
        continue;
    }

    foreach ($themes as $slug => $name) {
        if (!is_string($slug) || preg_match('/^[a-z0-9][a-z0-9-]{0,47}$/', $slug) !== 1) {
            fwrite(STDERR, "Invalid theme slug: " . var_export($slug, true) . "\n");
            $failed = true;
            continue;
        }
        if (isset($seen[$slug])) {
            fwrite(STDERR, "Duplicate theme slug: {$slug}\n");
            $failed = true;
        }
        $seen[$slug] = true;

        if (!is_string($name) || trim($name) === '') {
            fwrite(STDERR, "Theme {$slug} has no display name.\n");
            $failed = true;
        }

        $path = $themeDirectory . '/' . $slug . '.css';
        if (!is_file($path)) {
            fwrite(STDERR, "Missing theme file: {$path}\n");
            $failed = true;
            continue;
        }

        $css = (string) file_get_contents($path);
        if (!str_contains($css, ':root')) {
            fwrite(STDERR, "Theme {$slug} does not define :root variables.\n");
            $failed = true;
        }
        if (preg_match('#(?:https?:)?//#i', $css) === 1) {
            fwrite(STDERR, "Theme {$slug} contains a remote URL.\n");
            $failed = true;
        }

        preg_match_all('/url\(["\']?([^"\')]+)["\']?\)/i', $css, $urlMatches);
        foreach ($urlMatches[1] ?? [] as $asset) {
            $asset = trim($asset);
            if ($asset === '' || str_starts_with($asset, 'data:')) {
                continue;
            }
            $resolved = realpath(dirname($path) . '/' . $asset);
            $themeRoot = realpath($themeDirectory);
            if ($resolved === false || $themeRoot === false || !str_starts_with($resolved, $themeRoot . DIRECTORY_SEPARATOR)) {
                fwrite(STDERR, "Theme {$slug} references a missing or outside asset: {$asset}\n");
                $failed = true;
            }
        }

        preg_match_all('/(--[a-z0-9-]+):\s*(#[0-9a-f]{3}(?:[0-9a-f]{3})?)\s*;/i', $css, $matches, PREG_SET_ORDER);
        $colors = [];
        foreach ($matches as $match) {
            $colors[$match[1]] = $match[2];
        }
        foreach ($contrastPairs as [$foreground, $background]) {
            if (!isset($colors[$foreground], $colors[$background])) {
                continue;
            }
            ++$contrastChecks;
            $ratio = theme_contrast($colors[$foreground], $colors[$background]);
            if ($ratio < 4.5) {
                fwrite(STDERR, sprintf(
                    "Theme %s contrast is %.2f for %s on %s; expected at least 4.50.\n",
                    $slug,
                    $ratio,
                    $foreground,
                    $background,
                ));
                $failed = true;
            }
        }
    }
}

foreach (glob($themeDirectory . '/*.css') ?: [] as $path) {
    $slug = basename($path, '.css');
    if (!isset($seen[$slug])) {
        fwrite(STDERR, "Unlisted theme file: {$slug}.css\n");
        $failed = true;
    }
}

if ($failed) {
    exit(1);
}

fwrite(STDOUT, sprintf(
    "[OK] %d themes, %d contrast pairs, and all local theme assets passed.\n",
    count($seen),
    $contrastChecks,
));
