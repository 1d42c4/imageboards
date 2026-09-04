<?php
/**
 * Simple CAPTCHA image generator for TinyIB.
 *
 * Based on cool-php-captcha (GPLv3) by Jose Rodriguez.
 */

declare(strict_types=1);

session_start();

error_reporting(E_ALL);

$captcha = new SimpleCaptcha();
$captcha->CreateImage();

/**
 * SimpleCaptcha class
 */
class SimpleCaptcha {
	/** Width of the image */
	public int $width = 175;

	/** Height of the image */
	public int $height = 55;

	/** Dictionary word file (empty for random text) */
	public string $wordsFile = '';

	/**
	 * Path for resource files (fonts, words, etc.)
	 */
	public string $resourcesPath = 'fonts';

	/** Min word length (for non-dictionary random text generation) */
	public int $minWordLength = 4;

	/**
	 * Max word length (for non-dictionary random text generation)
	 */
	public int $maxWordLength = 5;

	/** Session variable name to store the CAPTCHA solution */
	public string $session_var = 'tinyibcaptcha';

	/** Background color in RGB */
	public array $backgroundColor = [254, 254, 254];

	/** Foreground colors in RGB */
	public array $colors = [
		[27, 78, 181], // blue
		[22, 163, 35], // green
		[214, 36, 7],  // red
	];

	/** Shadow color in RGB, or empty to disable */
	public array $shadowColor = [0, 0, 0];

	/** Horizontal line width through the text (0 to disable) */
	public int $lineWidth = 0;

	/**
	 * Font configuration
	 *
	 * - font: TTF file
	 * - spacing: relative spacing between characters
	 * - minSize / maxSize: font size
	 */
	public array $fonts = [
		'Roboto-Regular' => ['spacing' => 0, 'minSize' => 27, 'maxSize' => 27, 'font' => 'roboto_regular.ttf'],
		'Roboto-Bold' => ['spacing' => 0, 'minSize' => 27, 'maxSize' => 27, 'font' => 'roboto_bold.ttf'],
	];

	/** Wave period / amplitude */
	public int $Yperiod = 12;
	public int $Yamplitude = 14;
	public int $Xperiod = 11;
	public int $Xamplitude = 5;

	/** Max letter rotation degrees */
	public int $maxRotation = 8;

	/** Internal image scale factor (higher = better quality, slower) */
	public int $scale = 3;

	/**
	 * Blur effect for better image quality (but slower image processing).
	 * Better image results with scale=3
	 */
	public bool $blur = true;

	/** Debug overlay */
	public bool $debug = false;

	/** Image format: jpeg or png */
	public string $imageFormat = 'png';

	/** @var \GdImage|null GD image resource */
	public $im = null;

	/** @var int|null */
	protected $GdBgColor = null;

	/** @var int|null */
	protected $GdFgColor = null;

	/** @var int|null */
	protected $GdShadowColor = null;

	protected int $textFinalX = 0;

	public function __construct(array $config = []) {
		foreach ($config as $key => $value) {
			if (property_exists($this, $key)) {
				$this->$key = $value;
			}
		}
	}

	public function CreateImage(): void {
		$ini = microtime(true);

		$this->ImageAllocate();

		$text = $this->GetCaptchaText();
		$fontcfg = $this->fonts[array_rand($this->fonts)];
		$this->WriteText($text, $fontcfg);

		$_SESSION[$this->session_var] = $text;

		if (!empty($this->lineWidth)) {
			$this->WriteLine();
		}
		$this->WaveImage();
		if ($this->blur && function_exists('imagefilter')) {
			imagefilter($this->im, IMG_FILTER_GAUSSIAN_BLUR);
		}
		$this->ReduceImage();

		if ($this->debug) {
			imagestring(
				$this->im,
				1,
				1,
				$this->height - 8,
				"$text {$fontcfg['font']} " . round((microtime(true) - $ini) * 1000) . 'ms',
				$this->GdFgColor
			);
		}

		$this->WriteImage();
		$this->Cleanup();
	}

	protected function ImageAllocate(): void {
		// Previous GdImage is released automatically when reassigned
		$this->im = imagecreatetruecolor($this->width * $this->scale, $this->height * $this->scale);

		$this->GdBgColor = imagecolorallocate(
			$this->im,
			$this->backgroundColor[0],
			$this->backgroundColor[1],
			$this->backgroundColor[2]
		);
		imagefilledrectangle(
			$this->im,
			0,
			0,
			$this->width * $this->scale,
			$this->height * $this->scale,
			$this->GdBgColor
		);

		$color = $this->colors[random_int(0, count($this->colors) - 1)];
		$this->GdFgColor = imagecolorallocate($this->im, $color[0], $color[1], $color[2]);

		if (!empty($this->shadowColor) && count($this->shadowColor) >= 3) {
			$this->GdShadowColor = imagecolorallocate(
				$this->im,
				$this->shadowColor[0],
				$this->shadowColor[1],
				$this->shadowColor[2]
			);
		}
	}

	protected function GetCaptchaText(): string {
		$text = $this->GetDictionaryCaptchaText();
		if ($text === false || $text === '') {
			$text = $this->GetRandomCaptchaText();
		}
		return $text;
	}

	protected function GetRandomCaptchaText(?int $length = null): string {
		if ($length === null || $length <= 0) {
			$length = random_int($this->minWordLength, $this->maxWordLength);
		}

		$words = 'abcdefghijlmnopqrstvwyz';
		$vocals = 'aeiou';

		$text = '';
		$vocal = (bool)random_int(0, 1);
		for ($i = 0; $i < $length; $i++) {
			if ($vocal) {
				$text .= $vocals[random_int(0, 4)];
			} else {
				$text .= $words[random_int(0, 22)];
			}
			$vocal = !$vocal;
		}
		return $text;
	}

	/**
	 * @return string|false
	 */
	protected function GetDictionaryCaptchaText(bool $extended = false): string|false {
		if ($this->wordsFile === '') {
			return false;
		}

		if (str_starts_with($this->wordsFile, '/') || preg_match('#^[A-Za-z]:[\\\\/]#', $this->wordsFile)) {
			$wordsfile = $this->wordsFile;
		} else {
			$wordsfile = __DIR__ . '/' . $this->resourcesPath . '/' . $this->wordsFile;
		}

		if (!is_file($wordsfile)) {
			return false;
		}

		$fp = fopen($wordsfile, 'r');
		if ($fp === false) {
			return false;
		}

		$length = strlen((string)fgets($fp));
		if (!$length) {
			fclose($fp);
			return false;
		}

		$line = random_int(1, (int)(filesize($wordsfile) / $length) - 2);
		if (fseek($fp, $length * $line) === -1) {
			fclose($fp);
			return false;
		}
		$text = trim((string)fgets($fp));
		fclose($fp);

		if ($extended) {
			$chars = preg_split('//', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
			$vocals = ['a', 'e', 'i', 'o', 'u'];
			foreach ($chars as $i => $char) {
				if (random_int(0, 1) && in_array($char, $vocals, true)) {
					$chars[$i] = $vocals[random_int(0, 4)];
				}
			}
			$text = implode('', $chars);
		}

		return $text;
	}

	protected function WriteLine(): void {
		$x1 = $this->width * $this->scale * 0.15;
		$x2 = $this->textFinalX;
		$y1 = random_int((int)($this->height * $this->scale * 0.40), (int)($this->height * $this->scale * 0.65));
		$y2 = random_int((int)($this->height * $this->scale * 0.40), (int)($this->height * $this->scale * 0.65));
		$width = $this->lineWidth / 2 * $this->scale;

		for ($i = (int)(-$width); $i <= $width; $i++) {
			imageline($this->im, (int)$x1, $y1 + $i, (int)$x2, $y2 + $i, $this->GdFgColor);
		}
	}

	protected function WriteText(string $text, array $fontcfg = []): void {
		if ($fontcfg === []) {
			$fontcfg = $this->fonts[array_rand($this->fonts)];
		}

		$fontfile = __DIR__ . '/' . $this->resourcesPath . '/' . $fontcfg['font'];

		$lettersMissing = $this->maxWordLength - strlen($text);
		$fontSizefactor = 1 + ($lettersMissing * 0.09);

		$x = 20 * $this->scale;
		$y = (int)round(($this->height * 27 / 40) * $this->scale);
		$length = strlen($text);
		for ($i = 0; $i < $length; $i++) {
			$degree = random_int(-$this->maxRotation, $this->maxRotation);
			$fontsize = random_int($fontcfg['minSize'], $fontcfg['maxSize']) * $this->scale * $fontSizefactor;
			$letter = $text[$i];

			if ($this->GdShadowColor !== null) {
				imagettftext(
					$this->im,
					$fontsize,
					$degree,
					(int)($x + $this->scale),
					(int)($y + $this->scale),
					$this->GdShadowColor,
					$fontfile,
					$letter
				);
			}
			$coords = imagettftext(
				$this->im,
				$fontsize,
				$degree,
				(int)$x,
				$y,
				$this->GdFgColor,
				$fontfile,
				$letter
			);
			$x += ($coords[2] - $x) + ($fontcfg['spacing'] * $this->scale);
		}

		$this->textFinalX = (int)$x;
	}

	protected function WaveImage(): void {
		$xp = $this->scale * $this->Xperiod * random_int(1, 3);
		$k = random_int(0, 100);
		for ($i = 0; $i < ($this->width * $this->scale); $i++) {
			imagecopy(
				$this->im,
				$this->im,
				$i - 1,
				(int)(sin($k + $i / $xp) * ($this->scale * $this->Xamplitude)),
				$i,
				0,
				1,
				$this->height * $this->scale
			);
		}

		$k = random_int(0, 100);
		$yp = $this->scale * $this->Yperiod * random_int(1, 2);
		for ($i = 0; $i < ($this->height * $this->scale); $i++) {
			imagecopy(
				$this->im,
				$this->im,
				(int)(sin($k + $i / $yp) * ($this->scale * $this->Yamplitude)),
				$i - 1,
				0,
				$i,
				$this->width * $this->scale,
				1
			);
		}
	}

	protected function ReduceImage(): void {
		$imResampled = imagecreatetruecolor($this->width, $this->height);
		imagecopyresampled(
			$imResampled,
			$this->im,
			0,
			0,
			0,
			0,
			$this->width,
			$this->height,
			$this->width * $this->scale,
			$this->height * $this->scale
		);
		$this->im = $imResampled;
	}

	protected function WriteImage(): void {
		if ($this->imageFormat === 'png' && function_exists('imagepng')) {
			imagealphablending($this->im, true);
			imagesavealpha($this->im, false);
			imagecolortransparent($this->im, $this->GdBgColor);

			header('Content-type: image/png');
			imagepng($this->im);
		} else {
			header('Content-type: image/jpeg');
			imagejpeg($this->im, null, 80);
		}
	}

	protected function Cleanup(): void {
		$this->im = null;
	}
}
