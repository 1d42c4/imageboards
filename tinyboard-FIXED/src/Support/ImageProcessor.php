<?php

declare(strict_types=1);

namespace Newboard\Support;

use Newboard\Config;

/**
 * GD-only image store + thumbnail.
 *
 * @phpstan-type UploadResult array{
 *   file_path: string,
 *   file_orig: string,
 *   file_size: int,
 *   file_width: int,
 *   file_height: int,
 *   thumb_path: string,
 *   thumb_width: int,
 *   thumb_height: int
 * }
 */
final class ImageProcessor
{
    public function __construct(private readonly Config $config)
    {
    }

    /**
     * @param array<string, mixed> $file from $_FILES element
     * @return UploadResult|null
     */
    public function store(array $file, string $boardUri): ?array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('Upload failed.');
        }

        $tmp = (string) ($file['tmp_name'] ?? '');
        $size = (int) ($file['size'] ?? 0);
        $orig = (string) ($file['name'] ?? 'image');
        $max = $this->config->int('board.max_image_bytes', 5_000_000);
        if ($size <= 0 || $size > $max) {
            throw new \RuntimeException('Image too large or empty.');
        }
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            throw new \RuntimeException('Invalid upload.');
        }

        $info = @getimagesize($tmp);
        if ($info === false) {
            throw new \RuntimeException('Not a valid image.');
        }
        $mime = $info['mime'] ?? '';
        /** @var array<string, string> $allowed */
        $allowed = $this->config->get('board.allowed_mime', []);
        if (!isset($allowed[$mime])) {
            throw new \RuntimeException('Image type not allowed.');
        }
        $ext = $allowed[$mime];
        $w = (int) $info[0];
        $h = (int) $info[1];

        $boardUri = preg_replace('/[^a-zA-Z0-9_-]/', '', $boardUri) ?: 'board';
        $baseDir = $this->config->string('paths.uploads') . '/' . $boardUri;
        $srcDir = $baseDir . '/src';
        $thumbDir = $baseDir . '/thumb';
        foreach ([$srcDir, $thumbDir] as $d) {
            if (!is_dir($d) && !mkdir($d, 0750, true) && !is_dir($d)) {
                throw new \RuntimeException('Cannot create upload directory.');
            }
        }

        $id = bin2hex(random_bytes(8));
        $filename = $id . '.' . $ext;
        $dest = $srcDir . '/' . $filename;
        if (!move_uploaded_file($tmp, $dest)) {
            throw new \RuntimeException('Could not save upload.');
        }

        $thumbMax = $this->config->int('board.thumb_max', 255);
        [$tw, $th] = $this->thumbSize($w, $h, $thumbMax);
        $thumbName = $id . 's.' . ($ext === 'gif' ? 'png' : $ext);
        // keep gif animation by using original as thumb for gif small enough; else png thumb
        if ($mime === 'image/gif' && $w <= $thumbMax && $h <= $thumbMax) {
            $thumbName = $id . 's.gif';
            copy($dest, $thumbDir . '/' . $thumbName);
            $tw = $w;
            $th = $h;
        } else {
            $this->writeThumb($dest, $thumbDir . '/' . $thumbName, $mime, $tw, $th);
        }

        $relSrc = $boardUri . '/src/' . $filename;
        $relThumb = $boardUri . '/thumb/' . $thumbName;

        return [
            'file_path' => $relSrc,
            'file_orig' => mb_substr($orig, 0, $this->config->int('board.max_filename', 200)),
            'file_size' => $size,
            'file_width' => $w,
            'file_height' => $h,
            'thumb_path' => $relThumb,
            'thumb_width' => $tw,
            'thumb_height' => $th,
        ];
    }

    /** @return array{0:int,1:int} */
    private function thumbSize(int $w, int $h, int $max): array
    {
        if ($w <= $max && $h <= $max) {
            return [$w, $h];
        }
        $ratio = min($max / max($w, 1), $max / max($h, 1));

        return [max(1, (int) round($w * $ratio)), max(1, (int) round($h * $ratio))];
    }

    private function writeThumb(string $src, string $dest, string $mime, int $tw, int $th): void
    {
        $im = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($src),
            'image/png' => @imagecreatefrompng($src),
            'image/gif' => @imagecreatefromgif($src),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($src) : false,
            default => false,
        };
        if ($im === false) {
            throw new \RuntimeException('Could not read image for thumbnail.');
        }
        $sw = imagesx($im);
        $sh = imagesy($im);
        $thumb = imagecreatetruecolor($tw, $th);
        if ($thumb === false) {
            imagedestroy($im);
            throw new \RuntimeException('Could not create thumbnail.');
        }
        imagecopyresampled($thumb, $im, 0, 0, 0, 0, $tw, $th, $sw, $sh);

        $ok = match (true) {
            str_ends_with($dest, '.jpg'), str_ends_with($dest, '.jpeg') => imagejpeg($thumb, $dest, 85),
            str_ends_with($dest, '.png') => imagepng($thumb, $dest, 6),
            str_ends_with($dest, '.webp') && function_exists('imagewebp') => imagewebp($thumb, $dest, 85),
            default => imagepng($thumb, $dest, 6),
        };
        imagedestroy($im);
        imagedestroy($thumb);
        if (!$ok) {
            throw new \RuntimeException('Could not write thumbnail.');
        }
    }
}
