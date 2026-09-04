<?php

/*
 *  Copyright (c) 2010-2013 Tinyboard Development Group
 */

defined('TINYBOARD') or exit;

/**
 * GD cannot resize animated GIFs without flattening to frame 1.
 * Detect multi-frame GIFs so callers can copy the original instead.
 */
function gif_is_animated(string $path): bool {
	$fh = @fopen($path, 'rb');
	if (!$fh) {
		return false;
	}
	$header = fread($fh, 6);
	if ($header !== 'GIF87a' && $header !== 'GIF89a') {
		fclose($fh);
		return false;
	}
	// Count graphic-control + image-descriptor sequences (frames)
	$count = 0;
	while (!feof($fh) && $count < 2) {
		$chunk = fread($fh, 100 * 1024);
		if ($chunk === false || $chunk === '') {
			break;
		}
		// 21 F9 04 = graphic control extension; next image often 2C
		$count += preg_match_all('/\x21\xF9\x04.{4}\x00(\x2C|\x21)/s', $chunk);
	}
	fclose($fh);
	return $count > 1;
}

/** Fit width/height into a max box (integer pixels). */
function image_fit_box(int $width, int $height, int $max_w, int $max_h): array {
	if ($width < 1 || $height < 1) {
		return [1, 1];
	}
	if ($width <= $max_w && $height <= $max_h) {
		return [$width, $height];
	}
	$x_ratio = $max_w / $width;
	$y_ratio = $max_h / $height;
	if (($x_ratio * $height) < $max_h) {
		return [$max_w, (int)ceil($x_ratio * $height)];
	}
	return [(int)ceil($y_ratio * $width), $max_h];
}

/**
 * Process an uploaded image into board src/ + thumb/.
 * Used by public posts and mod replace-image.
 *
 * @return array File metadata for the posts.files JSON entry
 */
function process_board_image_upload(string $tmp_path, string $original_name, bool $is_op = false): array {
	global $board, $config;

	if (!is_readable($tmp_path)) {
		error($config['error']['nomove']);
	}

	$size_bytes = filesize($tmp_path);
	if ($size_bytes === false || $size_bytes < 1) {
		error($config['error']['invalidimg']);
	}
	if ($size_bytes > $config['max_filesize']) {
		error(sprintf3($config['error']['filesize'], [
			'filesz' => number_format($size_bytes),
			'maxsz' => number_format($config['max_filesize']),
		]));
	}

	$filename = urldecode($original_name);
	$extension = strtolower(mb_substr($filename, mb_strrpos($filename, '.') + 1));
	if (!in_array($extension, $config['allowed_ext'], true)
		&& !($is_op && !empty($config['allowed_ext_op']) && in_array($extension, (array)$config['allowed_ext_op'], true))) {
		error($config['error']['unknownext']);
	}

	if (!$size = @getimagesize($tmp_path)) {
		error($config['error']['invalidimg']);
	}
	if (!in_array($size[2], [IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_JPEG, IMAGETYPE_BMP, IMAGETYPE_WEBP], true)) {
		error($config['error']['invalidimg']);
	}
	if ($size[0] > $config['max_width'] || $size[1] > $config['max_height']) {
		error($config['error']['maxsize']);
	}

	$file = [
		'filename' => $filename,
		'extension' => $extension,
		'size' => $size_bytes,
		'is_an_image' => true,
	];

	if (isset($config['filename_func'])) {
		$file['file_id'] = $config['filename_func']($file);
	} else {
		$file['file_id'] = time() . substr(microtime(), 2, 3);
	}

	$ext_out = $config['thumb_ext'] ? $config['thumb_ext'] : $extension;
	$file['file'] = $file['file_id'] . '.' . $extension;
	$file['thumb'] = $file['file_id'] . '.' . $ext_out;

	$src_path = $board['dir'] . $config['dir']['img'] . $file['file'];
	$thumb_path = $board['dir'] . $config['dir']['thumb'] . $file['thumb'];

	$image = new Image($tmp_path, $extension, $size);
	$file['width'] = $image->size->width;
	$file['height'] = $image->size->height;

	$max_tw = $is_op ? (int)$config['thumb_op_width'] : (int)$config['thumb_width'];
	$max_th = $is_op ? (int)$config['thumb_op_height'] : (int)$config['thumb_height'];

	if ($extension === 'gif' && gif_is_animated($tmp_path)) {
		if (!@copy($tmp_path, $thumb_path)) {
			$image->destroy();
			error($config['error']['nomove']);
		}
		[$file['thumbwidth'], $file['thumbheight']] = image_fit_box(
			(int)$image->size->width,
			(int)$image->size->height,
			$max_tw,
			$max_th
		);
	} elseif ($config['minimum_copy_resize']
		&& $image->size->width <= $max_tw
		&& $image->size->height <= $max_th
		&& $extension === $ext_out) {
		copy($tmp_path, $thumb_path);
		$file['thumbwidth'] = $image->size->width;
		$file['thumbheight'] = $image->size->height;
	} else {
		$thumb = $image->resize($ext_out, $max_tw, $max_th);
		$thumb->to($thumb_path);
		$file['thumbwidth'] = $thumb->width;
		$file['thumbheight'] = $thumb->height;
		$thumb->_destroy();
	}

	$image->destroy();

	if (!@copy($tmp_path, $src_path) && !@move_uploaded_file($tmp_path, $src_path)) {
		@unlink($thumb_path);
		error($config['error']['nomove']);
	}
	@chmod($src_path, 0644);
	@chmod($thumb_path, 0644);

	$file['hash'] = md5_file($src_path) ?: '';
	$file['file_path'] = $src_path;
	$file['thumb_path'] = $thumb_path;

	return $file;
}

/** Unlink board image + thumb from a files[] JSON entry (object or array). */
function unlink_board_file_entry($entry): void {
	global $board, $config;
	if (!$entry) {
		return;
	}
	$e = (array)$entry;
	if (!empty($e['file']) && $e['file'] !== 'deleted') {
		file_unlink($board['dir'] . $config['dir']['img'] . $e['file']);
	}
	if (!empty($e['thumb']) && !in_array($e['thumb'], ['spoiler', 'deleted', 'file'], true)) {
		file_unlink($board['dir'] . $config['dir']['thumb'] . $e['thumb']);
	}
}

class Image {
	public $src, $format, $image, $size;
	public function __construct($src, $format = false, $size = false) {
		global $config;

		$this->src = $src;
		$this->format = $format;

		if ($config['thumb_method'] == 'imagick') {
			$classname = 'ImageImagick';
		} elseif (in_array($config['thumb_method'], array('convert', 'convert+gifsicle', 'gm', 'gm+gifsicle'))) {
			$classname = 'ImageConvert';
		} else {
			$classname = 'Image' . strtoupper($this->format);
			if (!class_exists($classname)) {
				error(_('Unsupported file format: ') . $this->format);
			}
		}

		$this->image = new $classname($this, $size);

		if (!$this->image->valid()) {
			$this->delete();
			error($config['error']['invalidimg']);
		}

		$this->size = (object)array('width' => $this->image->_width(), 'height' => $this->image->_height());
		if ($this->size->width < 1 || $this->size->height < 1) {
			$this->delete();
			error($config['error']['invalidimg']);
		}
	}

	public function resize($extension, $max_width, $max_height) {
		global $config;

		if ($config['thumb_method'] == 'imagick') {
			$classname = 'ImageImagick';
		} elseif ($config['thumb_method'] == 'convert') {
			$classname = 'ImageConvert';
		} elseif ($config['thumb_method'] == 'convert+gifsicle') {
			$classname = 'ImageConvert';
			$gifsicle = true;
		} elseif ($config['thumb_method'] == 'gm') {
			$classname = 'ImageConvert';
			$gm = true;
		} elseif ($config['thumb_method'] == 'gm+gifsicle') {
			$classname = 'ImageConvert';
			$gm = true;
			$gifsicle = true;
		} else {
			$classname = 'Image' . strtoupper($extension);
			if (!class_exists($classname)) {
				error(_('Unsupported file format: ') . $extension);
			}
		}

		$thumb = new $classname(false);
		$thumb->src = $this->src;
		$thumb->format = $this->format;
		$thumb->original_width = $this->size->width;
		$thumb->original_height = $this->size->height;

		$x_ratio = $max_width / $this->size->width;
		$y_ratio = $max_height / $this->size->height;

		if (($this->size->width <= $max_width) && ($this->size->height <= $max_height)) {
			$width = $this->size->width;
			$height = $this->size->height;
		} elseif (($x_ratio * $this->size->height) < $max_height) {
			$height = ceil($x_ratio * $this->size->height);
			$width = $max_width;
		} else {
			$width = ceil($y_ratio * $this->size->width);
			$height = $max_height;
		}

		$thumb->_resize($this->image->image, $width, $height);

		return $thumb;
	}

	public function to($dst) {
		$this->image->to($dst);
	}

	public function delete() {
		file_unlink($this->src);
	}
	public function destroy() {
		$this->image->_destroy();
	}
}

class ImageGD {
	public function GD_create() {
		$this->image = imagecreatetruecolor($this->width, $this->height);
	}
	public function GD_copyresampled() {
		imagecopyresampled($this->image, $this->original, 0, 0, 0, 0, $this->width, $this->height, $this->original_width, $this->original_height);
	}
	public function GD_resize() {
		$this->GD_create();
		$this->GD_copyresampled();
	}
}

class ImageBase extends ImageGD {
	public $image, $src, $original, $original_width, $original_height, $width, $height;
	public function valid() {
		return (bool)$this->image;
	}

	public function __construct($img, $size = false) {
		if (method_exists($this, 'init'))
			$this->init();

		if ($size && $size[0] > 0 && $size[1] > 0) {
			$this->width = $size[0];
			$this->height = $size[1];
		}

		if ($img !== false) {
			$this->src = $img->src;
			$this->from();
		}
	}

	public function _width() {
		if (method_exists($this, 'width'))
			return $this->width();
		// use default GD functions
		return imagesx($this->image);
	}
	public function _height() {
		if (method_exists($this, 'height'))
			return $this->height();
		// use default GD functions
		return imagesy($this->image);
	}
	public function _destroy() {
		if (method_exists($this, 'destroy'))
			return $this->destroy();
		// use default GD functions
		return imagedestroy($this->image);
	}
	public function _resize($original, $width, $height) {
		$this->original = &$original;
		$this->width = $width;
		$this->height = $height;

		if (method_exists($this, 'resize'))
			$this->resize();
		else
			// use default GD functions
			$this->GD_resize();
	}
}

class ImageImagick extends ImageBase {
	public function init() {
		$this->image = new Imagick();
		$this->image->setBackgroundColor(new ImagickPixel('transparent'));
	}
	public function from() {
		try {
			$this->image->readImage($this->src);
		} catch(ImagickException $e) {
			// invalid image
			$this->image = false;
		}
	}
	public function to($src) {
		global $config;
		if ($config['strip_exif']) {
			$this->image->stripImage();
		}
		if (preg_match('/\.gif$/i', $src))
			$this->image->writeImages($src, true);
		else
			$this->image->writeImage($src);
	}
	public function width() {
		return $this->image->getImageWidth();
	}
	public function height() {
		return $this->image->getImageHeight();
	}
	public function destroy() {
		return $this->image->destroy();
	}
	public function resize() {
		global $config;

		if ($this->format == 'gif' && ($config['thumb_ext'] == 'gif' || $config['thumb_ext'] == '')) {
			$this->image = new Imagick();
			$this->image->setFormat('gif');

			$keep_frames = array();
			for ($i = 0; $i < $this->original->getNumberImages(); $i += floor($this->original->getNumberImages() / $config['thumb_keep_animation_frames']))
				$keep_frames[] = $i;

			$i = 0;
			$delay = 0;
			foreach ($this->original as $frame) {
				$delay += $frame->getImageDelay();

				if (in_array($i, $keep_frames)) {
					// $frame->scaleImage($this->width, $this->height, false);
					$frame->sampleImage($this->width, $this->height);
					$frame->setImagePage($this->width, $this->height, 0, 0);
					$frame->setImageDelay($delay);
					$delay = 0;

					$this->image->addImage($frame->getImage());
				}
				$i++;
			}
		} else {
			$this->image = clone $this->original;
			$this->image->scaleImage($this->width, $this->height, false);
		}
	}
}


class ImageConvert extends ImageBase {
	public $width, $height, $temp, $gm = false, $gifsicle = false;

	public function init() {
		global $config;

		if ($config['thumb_method'] == 'gm' || $config['thumb_method'] == 'gm+gifsicle')
			$this->gm = true;
		if ($config['thumb_method'] == 'convert+gifsicle' || $config['thumb_method'] == 'gm+gifsicle')
			$this->gifsicle = true;

		$this->temp = false;
	}
	public function get_size($src, $try_gd_first = true) {
		if ($try_gd_first) {
			if ($size = @getimagesize($src))
				return $size;
		}
		$size = shell_exec_error(($this->gm ? 'gm ' : 'magick ') . 'identify -format "%w %h" ' . escapeshellarg($src . '[0]'));
		if (preg_match('/^(\d+) (\d+)$/', $size, $m))
			return array($m[1], $m[2]);
		return false;
	}
	public function from() {
		if ($this->width > 0 && $this->height > 0) {
			$this->image = true;
			return;
		}
		$size = $this->get_size($this->src, false);
		if ($size) {
			$this->width = $size[0];
			$this->height = $size[1];

			$this->image = true;
		} else {
			// mark as invalid
			$this->image = false;
		}
	}
	public function to($src) {
		global $config;

		if (!$this->temp) {
			if ($config['strip_exif']) {
				if($error = shell_exec_error(($this->gm ? 'gm convert ' : 'magick ') .
						escapeshellarg($this->src) . ' -auto-orient -strip ' . escapeshellarg($src))) {
					$this->destroy();
					error(_('Failed to redraw image!'), null, $error);
				}
			} else {
				if($error = shell_exec_error(($this->gm ? 'gm convert ' : 'magick ') .
						escapeshellarg($this->src) . ' -auto-orient ' . escapeshellarg($src))) {
					$this->destroy();
					error(_('Failed to redraw image!'), null, $error);
				}
			}
		} else {
			rename($this->temp, $src);
			chmod($src, 0664);
			$this->temp = false;
		}
	}
	public function width() {
		return $this->width;
	}
	public function height() {
		return $this->height;
	}
	public function destroy() {
		if ($this->temp !== false) {
			@unlink($this->temp);
			$this->temp = false;
		}
	}
	public function resize() {
		global $config;

		if ($this->temp) {
			// remove old
			$this->destroy();
		}

		$this->temp = tempnam($config['tmp'], 'convert') . ($config['thumb_ext'] == '' ? '' : '.' . $config['thumb_ext']);

		$config['thumb_keep_animation_frames'] = (int)$config['thumb_keep_animation_frames'];

		if ($this->format == 'gif' && ($config['thumb_ext'] == 'gif' || $config['thumb_ext'] == '') && $config['thumb_keep_animation_frames'] > 1) {
			if ($this->gifsicle) {
				if (($error = shell_exec("gifsicle -w --unoptimize -O2 --resize {$this->width}x{$this->height} < " .
						escapeshellarg($this->src . '') . " \"#0-{$config['thumb_keep_animation_frames']}\" -o " .
						escapeshellarg($this->temp))) || !file_exists($this->temp)) {
					$this->destroy();
					error(_('Failed to resize image!'), null, $error);
				}
			} else {
				$convert_args = &$config['convert_args'];

				if (($error = shell_exec_error(($this->gm ? 'gm convert ' : 'magick ') .
					sprintf($convert_args,
						$this->width,
						$this->height,
						escapeshellarg($this->src),
						$this->width,
						$this->height,
						escapeshellarg($this->temp)))) || !file_exists($this->temp)) {
					$this->destroy();
					error(_('Failed to resize image!'), null, $error);
				}
				if ($size = $this->get_size($this->temp)) {
					$this->width = $size[0];
					$this->height = $size[1];
				}
			}
		} else {
			$convert_args = &$config['convert_args'];

			if (($error = shell_exec_error(($this->gm ? 'gm convert ' : 'magick ') .
				sprintf($convert_args,
					$this->width,
					$this->height,
					escapeshellarg($this->src . '[0]'),
					$this->width,
					$this->height,
					escapeshellarg($this->temp)))) || !file_exists($this->temp)) {

					if (strpos($error, "known incorrect sRGB profile") === false &&
                                            strpos($error, "iCCP: Not recognizing known sRGB profile that has been edited") === false &&
                                            strpos($error, "cHRM chunk does not match sRGB") === false) {
						$this->destroy();
						error(_('Failed to resize image!')." "._('Details: ').nl2br(htmlspecialchars($error)), null, array('convert_error' => $error));
					}
					if (!file_exists($this->temp)) {
						$this->destroy();
						error(_('Failed to resize image!'), null, $error);
					}
			}
			if ($size = $this->get_size($this->temp)) {
				$this->width = $size[0];
				$this->height = $size[1];
			}
		}
	}
}

class ImagePNG extends ImageBase {
	public function from() {
		$this->image = @imagecreatefrompng($this->src);
	}
	public function to($src) {
		global $config;
		imagepng($this->image, $src);
	}
	public function resize() {
		$this->GD_create();
		imagecolortransparent($this->image, imagecolorallocatealpha($this->image, 0, 0, 0, 0));
		imagesavealpha($this->image, true);
		imagealphablending($this->image, false);
		$this->GD_copyresampled();
	}
}

class ImageGIF extends ImageBase {
	public function from() {
		$this->image = @imagecreatefromgif($this->src);
	}
	public function to($src) {
		imagegif ($this->image, $src);
	}
	public function resize() {
		$this->GD_create();
		imagecolortransparent($this->image, imagecolorallocatealpha($this->image, 0, 0, 0, 0));
		imagesavealpha($this->image, true);
		$this->GD_copyresampled();
	}
}

class ImageJPG extends ImageBase {
	public function from() {
		$this->image = @imagecreatefromjpeg($this->src);
	}
	public function to($src) {
		imagejpeg($this->image, $src);
	}
}
class ImageJPEG extends ImageJPG {
}

class ImageBMP extends ImageBase {
	public function from() {
		$this->image = @imagecreatefrombmp($this->src);
	}
	public function to($src) {
		imagebmp($this->image, $src);
	}
}

class ImageWEBP extends ImageBase {
	public function from() {
		$this->image = @imagecreatefromwebp($this->src);
	}
	public function to($src) {
		imagewebp($this->image, $src);
	}
}
