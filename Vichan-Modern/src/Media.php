<?php

declare(strict_types=1);

namespace VichanModern;

final readonly class Media
{
    private const array TYPES = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp', 'mp4' => 'video/mp4'];
    public function __construct(private Config $config, private Db $db)
    {
    }
    /**
     * @param array<string, mixed> $upload
     * @return list<array<string, mixed>> */
    public function receive(array $upload): array
    {
        $files = [];
        if ($upload === []) {
            return [];
        }
        if (!isset($upload['error']) || !is_array($upload['error']) || count($upload['error']) > $this->config->int('max_files')) {
            throw new HttpError('Too many files or an invalid upload.');
        }
        try {
            foreach ($upload['error'] as $i => $error) {
                if ($error === UPLOAD_ERR_NO_FILE) {
                    continue;
                }
                if ($error !== UPLOAD_ERR_OK || !isset($upload['tmp_name'][$i], $upload['name'][$i]) || !is_string($upload['tmp_name'][$i]) || !is_string($upload['name'][$i])) {
                    throw new HttpError('The upload failed or exceeded the server limit.');
                }
                $tmp = $upload['tmp_name'][$i];
                if (!is_uploaded_file($tmp)) {
                    throw new HttpError('Invalid upload source.');
                }
                $files[] = $this->store($tmp, $upload['name'][$i]);
            }
            return $files;
        } catch (\Throwable $error) {
            $this->remove($files);
            throw $error;
        }
    }
    /**
     * @return array<string, mixed> */
    private function store(string $tmp, string $name): array
    {
        $size = filesize($tmp);
        if ($size === false || $size === 0 || $size > $this->config->int('max_upload_bytes')) {
            throw new HttpError('File too large or empty.');
        }
        $name = Input::text(['name' => basename(str_replace('\\', '/', $name))], 'name', 180);
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($tmp);
        if (!isset(self::TYPES[$ext]) || $mime !== self::TYPES[$ext]) {
            throw new HttpError('Upload a genuine JPEG, PNG, GIF, WebP or MP4 file.');
        }
        $token = bin2hex(random_bytes(24));
        $destination = $this->config->path('var/media/' . $token);
        $width = $height = $thumb = 0;
        try {
            if ($ext === 'mp4') {
                $this->validateMp4($tmp);
                if (!move_uploaded_file($tmp, $destination)) {
                    throw new \RuntimeException('Could not store upload.');
                }
            } else {
                $info = getimagesize($tmp);
                if ($info === false || $info[0] < 1 || $info[1] < 1 || $info[0] > 12000 || $info[1] > 12000 || $info[0] * $info[1] > $this->config->int('max_image_pixels')) {
                    throw new HttpError('Image dimensions exceed the limit.');
                }
                [$width, $height] = $info;
                if ($ext === 'gif') {
                    $this->validateGif($tmp, $width, $height);
                }
                $bytes = file_get_contents($tmp);
                $im = $bytes === false ? false : imagecreatefromstring($bytes);
                if (!$im instanceof \GdImage) {
                    throw new HttpError('The image could not be decoded.');
                }
                if ($ext === 'gif') {
                    if (!move_uploaded_file($tmp, $destination)) {
                        throw new \RuntimeException('Could not store image.');
                    }
                } else {
                    $ok = match ($ext) {
                        'jpg', 'jpeg' => imagejpeg($im, $destination, 92), 'png' => $this->png($im, $destination), 'webp' => imagewebp($im, $destination, 90)
                    };
                    if (!$ok) {
                        throw new \RuntimeException('Could not encode image.');
                    }
                }
                $scale = min(1.0, 250 / $width, 250 / $height);
                $small = imagescale($im, max(1, (int) round($width * $scale)), max(1, (int) round($height * $scale)));
                if (!$small instanceof \GdImage || !$this->png($small, $destination . '.thumb')) {
                    throw new \RuntimeException('Could not create thumbnail.');
                }
                $thumb = 1;
            }
            return ['token' => $token, 'filename' => $name, 'mime' => $mime, 'extension' => $ext, 'bytes' => filesize($destination), 'width' => $width, 'height' => $height, 'thumb' => $thumb];
        } catch (\Throwable $error) {
            $this->remove([['token' => $token]]);
            if ($error instanceof \ErrorException) {
                throw new HttpError('The media file is malformed or unsupported.');
            }
            throw $error;
        }
    }
    private function png(\GdImage $image, string $path): bool
    {
        imagealphablending($image, false);
        imagesavealpha($image, true);
        return imagepng($image, $path);
    }
    private function validateGif(string $path, int $width, int $height): void
    {
        $data = file_get_contents($path);
        if ($data === false || strlen($data) < 14) {
            throw new HttpError('Invalid GIF.');
        }
        $length = strlen($data);
        $offset = 13 + ((ord($data[10]) & 128) ? 3 * (2 ** ((ord($data[10]) & 7) + 1)) : 0);
        $frames = 0;
        $blocks = 0;
        while ($offset < $length) {
            if (++$blocks > 10000) {
                throw new HttpError('Too many GIF blocks.');
            }
            $type = ord($data[$offset++]);
            if ($type === 59) {
                if ($frames < 1 || $offset !== $length) {
                    throw new HttpError('Invalid GIF trailer.');
                } return;
            }
            if ($type === 44) {
                if ($offset + 9 > $length) {
                    throw new HttpError('Truncated GIF.');
                }
                $frame = unpack('vleft/vtop/vwidth/vheight/Cflags', substr($data, $offset, 9));
                if ($frame === false || $frame['width'] < 1 || $frame['height'] < 1 || $frame['width'] + $frame['left'] > $width || $frame['height'] + $frame['top'] > $height || $frame['width'] * $frame['height'] > $this->config->int('max_image_pixels')) {
                    throw new HttpError('GIF frame too large.');
                }
                $offset += 9 + (($frame['flags'] & 128) ? 3 * (2 ** (($frame['flags'] & 7) + 1)) : 0);
                $offset++; // LZW code size; GD validates image decoding.
                if (++$frames > 500 || $frames * $width * $height > 200000000) {
                    throw new HttpError('Animated GIF exceeds the frame/pixel budget.');
                }
            } elseif ($type === 33) {
                $offset++;
            } else {
                throw new HttpError('Invalid GIF block.');
            }
            do {
                if ($offset >= $length) {
                    throw new HttpError('Truncated GIF block.');
                }
                $block = ord($data[$offset++]);
                $offset += $block;
                if (++$blocks > 200000) {
                    throw new HttpError('Too many GIF data blocks.');
                }
            } while ($block !== 0);
        }
        throw new HttpError('Missing GIF trailer.');
    }
    private function validateMp4(string $path): void
    {
        $stream = fopen($path, 'rb');
        if ($stream === false) {
            throw new HttpError('Unreadable MP4.');
        }
        $length = filesize($path);
        if ($length === false) {
            fclose($stream);
            throw new HttpError('Unreadable MP4 length.');
        }
        $seen = [];
        $video = false;
        try {
            $offset = 0;
            $boxes = 0;
            while ($offset < $length) {
                if (++$boxes > 10000 || $length - $offset < 8) {
                    throw new HttpError('Malformed MP4 container.');
                }
                fseek($stream, $offset);
                $header = fread($stream, 8);
                if ($header === false || strlen($header) !== 8) {
                    throw new HttpError('Truncated MP4.');
                }
                $size = $this->uint32($header);
                $type = substr($header, 4, 4);
                $minimum = 8;
                if ($size === 1) {
                    $large = fread($stream, 8);
                    if ($large === false || strlen($large) !== 8) {
                        throw new HttpError('Truncated MP4 box.');
                    }
                    if ($this->uint32($large) !== 0) {
                        throw new HttpError('MP4 box too large.');
                    }
                    $size = $this->uint32(substr($large, 4));
                    $minimum = 16;
                } elseif ($size === 0) {
                    $size = $length - $offset;
                }
                if ($size < $minimum || $size > $length - $offset) {
                    throw new HttpError('Invalid MP4 box length.');
                }
                if ($offset === 0 && $type !== 'ftyp') {
                    throw new HttpError('Missing MP4 file type.');
                }
                if ($type === 'ftyp') {
                    if ($size < $minimum + 8) {
                        throw new HttpError('Truncated MP4 file type.');
                    }
                    $brand = fread($stream, 4);
                    if (!in_array($brand, ['isom', 'iso2', 'iso4', 'iso5', 'iso6', 'mp41', 'mp42', 'avc1', 'M4V ', 'MSNV', 'dash'], true)) {
                        throw new HttpError('Unsupported MP4 brand.');
                    }
                }
                if ($type === 'moov') {
                    $metadataSize = $size - $minimum;
                    if ($metadataSize < 16 || $metadataSize > 4194304) {
                        throw new HttpError('MP4 metadata is missing or too large.');
                    }
                    $metadata = fread($stream, $metadataSize);
                    if ($metadata === false || strlen($metadata) !== $metadataSize) {
                        throw new HttpError('Truncated MP4 metadata.');
                    }
                    $budget = 10000;
                    $types = [];
                    $this->mp4Metadata($metadata, 0, $budget, $types);
                    if (!isset($types['mvhd'], $types['trak'], $types['vide'], $types['video-codec'])) {
                        throw new HttpError('MP4 does not contain a supported video track.');
                    }
                    $video = true;
                }
                if ($type === 'mdat' && $size === $minimum) {
                    throw new HttpError('Empty MP4 media data.');
                }
                if (!in_array($type, ['ftyp', 'moov', 'mdat', 'free', 'skip', 'wide', 'moof', 'mfra', 'sidx', 'styp', 'pdin', 'uuid'], true)) {
                    throw new HttpError('Unsupported MP4 container box.');
                }
                $seen[$type] = true;
                $offset += $size;
            }
            if (!$video || !isset($seen['ftyp'], $seen['moov'], $seen['mdat'])) {
                throw new HttpError('MP4 is missing required video container data.');
            }
        } finally {
            fclose($stream);
        }
    }
    private function uint32(string $bytes): int
    {
        if (strlen($bytes) < 4) {
            throw new HttpError('Truncated binary value.');
        }
        return (ord($bytes[0]) << 24) | (ord($bytes[1]) << 16) | (ord($bytes[2]) << 8) | ord($bytes[3]);
    }
    /** @param array<string, bool> $types */
    private function mp4Metadata(string $data, int $depth, int &$budget, array &$types): void
    {
        if ($depth > 8) {
            throw new HttpError('MP4 metadata is nested too deeply.');
        }
        $offset = 0;
        $length = strlen($data);
        while ($offset < $length) {
            if (--$budget < 0 || $length - $offset < 8) {
                throw new HttpError('Malformed MP4 metadata.');
            }
            $size = $this->uint32(substr($data, $offset, 4));
            $type = substr($data, $offset + 4, 4);
            if ($size < 8 || $size > $length - $offset) {
                throw new HttpError('Invalid MP4 metadata length.');
            }
            $payload = substr($data, $offset + 8, $size - 8);
            $types[$type] = true;
            if (in_array($type, ['trak', 'mdia', 'minf', 'stbl', 'edts', 'dinf', 'mvex'], true)) {
                $this->mp4Metadata($payload, $depth + 1, $budget, $types);
            }
            if ($type === 'hdlr' && strlen($payload) >= 12 && substr($payload, 8, 4) === 'vide') {
                $types['vide'] = true;
            }
            if ($type === 'stsd') {
                if (strlen($payload) < 8) {
                    throw new HttpError('Truncated MP4 sample description.');
                }
                $count = $this->uint32(substr($payload, 4, 4));
                if ($count > 16) {
                    throw new HttpError('Too many MP4 sample descriptions.');
                }
                $entry = 8;
                for ($i = 0; $i < $count; $i++) {
                    if (strlen($payload) - $entry < 8) {
                        throw new HttpError('Truncated MP4 sample entry.');
                    }
                    $entrySize = $this->uint32(substr($payload, $entry, 4));
                    $codec = substr($payload, $entry + 4, 4);
                    if ($entrySize < 8 || $entrySize > strlen($payload) - $entry) {
                        throw new HttpError('Invalid MP4 sample entry length.');
                    }
                    if (in_array($codec, ['avc1', 'avc3', 'hvc1', 'hev1', 'av01', 'vp09'], true)) {
                        if ($entrySize < 86) {
                            throw new HttpError('Truncated MP4 video dimensions.');
                        }
                        $w = (ord($payload[$entry + 32]) << 8) | ord($payload[$entry + 33]);
                        $h = (ord($payload[$entry + 34]) << 8) | ord($payload[$entry + 35]);
                        if ($w < 1 || $h < 1 || $w * $h > 16777216) {
                            throw new HttpError('MP4 dimensions exceed the limit.');
                        }
                        $types['video-codec'] = true;
                    }
                    $entry += $entrySize;
                }
            }
            $offset += $size;
        }
    }
    /**
     * @param list<array<string, mixed>> $files */
    public function remove(array $files): void
    {
        foreach ($files as $file) {
            $token = (string) $file['token'];
            if (!preg_match('/\A[a-f0-9]{48}\z/D', $token)) {
                throw new \LogicException('Unsafe media token.');
            }
            foreach (['', '.thumb'] as $suffix) {
                $path = $this->config->path('var/media/' . $token . $suffix);
                if (is_file($path)) {
                    unlink($path);
                }
            }
        }
    }
    public function serve(string $token, bool $thumbnail): never
    {
        if (!preg_match('/\A[a-f0-9]{48}\z/D', $token)) {
            throw new HttpError('File not found.', 404);
        }
        $file = $this->db->one('SELECT * FROM files WHERE token=?', [$token]);
        if ($file === null || ($thumbnail && !(bool) $file['thumb'])) {
            throw new HttpError('File not found.', 404);
        }
        $path = $this->config->path('var/media/' . $token . ($thumbnail ? '.thumb' : ''));
        if (!is_file($path)) {
            throw new HttpError('File not found.', 404);
        }
        $length = filesize($path);
        $start = 0;
        $end = $length - 1;
        header('Content-Type: ' . ($thumbnail ? 'image/png' : $file['mime']));
        header("Content-Disposition: inline; filename=\"media." . ($thumbnail ? 'png' : $file['extension']) . "\"");
        header('Cache-Control: public, max-age=0, must-revalidate');
        header('Accept-Ranges: bytes');
        $range = $_SERVER['HTTP_RANGE'] ?? '';
        if (is_string($range) && $range !== '') {
            if (!preg_match('/\Abytes=(\d{0,12})-(\d{0,12})\z/D', $range, $match) || ($match[1] === '' && $match[2] === '')) {
                header('Content-Range: bytes */' . $length);
                throw new HttpError('Invalid byte range.', 416);
            }
            if ($match[1] === '') {
                $start = max(0, $length - (int) $match[2]);
            } else {
                $start = (int) $match[1];
                if ($match[2] !== '') {
                    $end = min($end, (int) $match[2]);
                }
            }
            if ($start > $end || $start >= $length) {
                header('Content-Range: bytes */' . $length);
                throw new HttpError('Range outside file.', 416);
            }
            http_response_code(206);
            header('Content-Range: bytes ' . $start . '-' . $end . '/' . $length);
        }
        header('Content-Length: ' . ($end - $start + 1));
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'HEAD') {
            exit;
        }
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new HttpError('File unavailable.', 404);
        }
        fseek($handle, $start);
        $remaining = $end - $start + 1;
        while ($remaining > 0 && !feof($handle) && !connection_aborted()) {
            $chunk = fread($handle, min(65536, $remaining));
            if ($chunk === false || $chunk === '') {
                break;
            } echo $chunk;
            $remaining -= strlen($chunk);
        }
        fclose($handle);
        exit;
    }
}
