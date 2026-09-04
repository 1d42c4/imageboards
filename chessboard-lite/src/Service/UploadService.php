<?php

declare(strict_types=1);

namespace Chessboard\Service;

use Chessboard\Config;
use Chessboard\Http\HttpException;
use GdImage;
use RuntimeException;

final readonly class UploadService
{
    private const MIME_EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public function __construct(private Config $config)
    {
    }

    public function process(?array $file): ?array
    {
        if ($file === null || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) {
            throw new HttpException(422, 'The image upload did not complete successfully.');
        }
        if (!extension_loaded('gd') || !extension_loaded('fileinfo')) {
            throw new HttpException(500, 'Image uploads require the GD and fileinfo PHP extensions.');
        }

        $temporary = $file['tmp_name'] ?? null;
        if (!is_string($temporary) || !is_file($temporary)) {
            throw new HttpException(422, 'The uploaded image is missing.');
        }
        if (PHP_SAPI !== 'cli' && !is_uploaded_file($temporary)) {
            throw new HttpException(422, 'The image upload could not be verified.');
        }

        $size = filesize($temporary);
        if ($size === false || $size < 1 || $size > $this->config->requireInt('max_upload_bytes')) {
            throw new HttpException(422, 'The image must be smaller than the configured upload limit.');
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($temporary);
        if (!is_string($mime) || !isset(self::MIME_EXTENSIONS[$mime])) {
            throw new HttpException(422, 'Only JPEG, PNG, and WebP images are accepted.');
        }
        if ($mime === 'image/webp' && !function_exists('imagewebp')) {
            throw new HttpException(422, 'This server cannot safely process WebP images.');
        }

        $dimensions = @getimagesize($temporary);
        if ($dimensions === false) {
            throw new HttpException(422, 'The uploaded file is not a readable image.');
        }
        [$width, $height] = $dimensions;
        if ($width < 1 || $height < 1) {
            throw new HttpException(422, 'The uploaded image has invalid dimensions.');
        }
        $pixelLimit = $this->config->requireInt('max_image_pixels');
        if ($height > intdiv($pixelLimit, $width)) {
            throw new HttpException(422, 'The uploaded image has too many pixels.');
        }

        $bytes = file_get_contents($temporary);
        $image = $bytes === false ? false : @imagecreatefromstring($bytes);
        if (!$image instanceof GdImage) {
            throw new HttpException(422, 'The uploaded image could not be decoded.');
        }

        $extension = self::MIME_EXTENSIONS[$mime];
        $storedName = bin2hex(random_bytes(16)) . '.' . $extension;
        $thumbName = bin2hex(random_bytes(16)) . '.' . $extension;
        $originalDirectory = $this->config->requireString('storage_path') . '/original';
        $thumbDirectory = $this->config->requireString('storage_path') . '/thumb';
        $this->ensureDirectory($originalDirectory);
        $this->ensureDirectory($thumbDirectory);
        $originalPath = $originalDirectory . '/' . $storedName;
        $thumbPath = $thumbDirectory . '/' . $thumbName;

        try {
            $this->save($image, $originalPath, $mime);
            [$thumbWidth, $thumbHeight] = $this->createThumbnail(
                $image,
                $width,
                $height,
                $thumbPath,
                $mime,
            );
        } catch (\Throwable $error) {
            @unlink($originalPath);
            @unlink($thumbPath);
            throw $error;
        }

        $storedSize = filesize($originalPath);
        if ($storedSize === false) {
            @unlink($originalPath);
            @unlink($thumbPath);
            throw new HttpException(500, 'The processed image could not be stored.');
        }

        $originalName = $file['name'] ?? 'image.' . $extension;
        $originalName = is_string($originalName) ? basename(str_replace('\\', '/', $originalName)) : 'image.' . $extension;
        $originalName = mb_substr($originalName, 0, 180);

        return [
            'original_name' => $originalName,
            'stored_name' => $storedName,
            'thumb_name' => $thumbName,
            'mime_type' => $mime,
            'byte_size' => $storedSize,
            'width' => $width,
            'height' => $height,
            'thumb_width' => $thumbWidth,
            'thumb_height' => $thumbHeight,
        ];
    }

    public function remove(?array $attachment): void
    {
        if ($attachment === null) {
            return;
        }

        foreach (['stored_name' => 'original', 'thumb_name' => 'thumb'] as $key => $directory) {
            $name = $attachment[$key] ?? null;
            if (!is_string($name) || !preg_match('/^[a-f0-9]{32}\.(?:jpg|png|webp)$/', $name)) {
                continue;
            }

            $path = $this->config->requireString('storage_path') . '/' . $directory . '/' . $name;
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }

    public function resolve(string $kind, string $name): array
    {
        if (!in_array($kind, ['original', 'thumb'], true) ||
            !preg_match('/^[a-f0-9]{32}\.(?:jpg|png|webp)$/', $name)) {
            throw new HttpException(404, 'Image not found.');
        }

        $path = $this->config->requireString('storage_path') . '/' . $kind . '/' . $name;
        if (!is_file($path)) {
            throw new HttpException(404, 'Image not found.');
        }

        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $mime = match ($extension) {
            'jpg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => throw new HttpException(404, 'Image not found.'),
        };

        return ['path' => $path, 'mime' => $mime];
    }

    private function createThumbnail(
        GdImage $source,
        int $width,
        int $height,
        string $path,
        string $mime,
    ): array {
        $limit = $this->config->requireInt('thumbnail_size');
        $scale = min($limit / $width, $limit / $height, 1);
        $thumbWidth = max(1, (int) round($width * $scale));
        $thumbHeight = max(1, (int) round($height * $scale));
        $thumb = imagecreatetruecolor($thumbWidth, $thumbHeight);
        if (!$thumb instanceof GdImage) {
            throw new RuntimeException('Unable to create a thumbnail canvas.');
        }

        if ($mime === 'image/jpeg') {
            $white = imagecolorallocate($thumb, 255, 255, 255);
            imagefill($thumb, 0, 0, $white);
        } else {
            imagealphablending($thumb, false);
            imagesavealpha($thumb, true);
            $transparent = imagecolorallocatealpha($thumb, 0, 0, 0, 127);
            imagefill($thumb, 0, 0, $transparent);
        }

        if (!imagecopyresampled(
            $thumb,
            $source,
            0,
            0,
            0,
            0,
            $thumbWidth,
            $thumbHeight,
            $width,
            $height,
        )) {
            throw new RuntimeException('Unable to resize the uploaded image.');
        }

        $this->save($thumb, $path, $mime);

        return [$thumbWidth, $thumbHeight];
    }

    private function save(GdImage $image, string $path, string $mime): void
    {
        $saved = match ($mime) {
            'image/jpeg' => imagejpeg($image, $path, 88),
            'image/png' => imagepng($image, $path, 7),
            'image/webp' => imagewebp($image, $path, 86),
            default => false,
        };

        if (!$saved) {
            throw new RuntimeException('Unable to encode the uploaded image.');
        }

        @chmod($path, 0640);
    }

    private function ensureDirectory(string $path): void
    {
        if (!is_dir($path) && !mkdir($path, 0770, true) && !is_dir($path)) {
            throw new RuntimeException('Unable to create the upload directory.');
        }
    }
}
