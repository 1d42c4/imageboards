<?php

declare(strict_types=1);

namespace VichanModern;

final readonly class Captcha
{
    public function __construct(private Config $config, private Security $security)
    {
    }
    public function image(): never
    {
        $this->security->session();
        $random = new \Random\Randomizer(new \Random\Engine\Secure());
        $answer = $random->getBytesFromString('23456789ABCDEFGHJKLMNPQRSTUVWXYZ', 6);
        $_SESSION['captcha_hash'] = hash('sha256', $answer);
        $_SESSION['captcha_until'] = time() + 300;
        $im = imagecreatetruecolor(200, 64);
        $bg = imagecolorallocate($im, 244, 244, 238);
        if ($bg === false) {
            throw new \RuntimeException('CAPTCHA colour allocation failed.');
        }
        imagefill($im, 0, 0, $bg);
        for ($i = 0; $i < 12; $i++) {
            $colour = imagecolorallocate($im, random_int(120, 195), random_int(120, 195), random_int(120, 195));
            if ($colour === false) {
                throw new \RuntimeException('CAPTCHA colour allocation failed.');
            }
            imageline($im, random_int(0, 199), random_int(0, 63), random_int(0, 199), random_int(0, 63), $colour);
        }
        $font = $this->config->path('resources/captcha.ttf');
        for ($i = 0; $i < 6; $i++) {
            $colour = imagecolorallocate($im, random_int(20, 80), random_int(20, 80), random_int(20, 80));
            if ($colour === false) {
                throw new \RuntimeException('CAPTCHA colour allocation failed.');
            }
            if (is_file($font)) {
                imagettftext($im, 25, random_int(-15, 15), 10 + $i * 30, random_int(40, 49), $colour, $font, $answer[$i]);
            } else {
                imagestring($im, 5, 12 + $i * 30, random_int(18, 32), $answer[$i], $colour);
            }
        }
        header('Content-Type: image/png');
        imagepng($im);
        exit;
    }
    public function verify(string $answer): void
    {
        $this->security->session();
        if (!$this->config->bool('captcha')) {
            return;
        }
        $expected = (string) ($_SESSION['captcha_hash'] ?? '');
        $expires = (int) ($_SESSION['captcha_until'] ?? 0);
        unset($_SESSION['captcha_hash'], $_SESSION['captcha_until']);
        if ($expires < time() || !hash_equals($expected, hash('sha256', strtoupper($answer)))) {
            throw new HttpError('The verification code is incorrect or expired. Try the new code.');
        }
    }
}
