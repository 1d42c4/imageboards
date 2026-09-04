<?php
/**
 * Image upload processing — GD only (no ImageMagick/convert/gm).
 * Animated GIFs: original is copied for the thumb so playback is preserved.
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
	$count = 0;
	while (!feof($fh) && $count < 2) {
		$chunk = fread($fh, 100 * 1024);
		if ($chunk === false || $chunk === '') {
			break;
		}
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
	} elseif ($image->size->width <= $max_tw
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

	if (!empty($config['strip_exif']) || !empty($config['redraw_image'])) {
		// Re-encode with GD to drop metadata
		$image->to($src_path);
	} else {
		if (!@copy($tmp_path, $src_path) && !@move_uploaded_file($tmp_path, $src_path)) {
			@unlink($thumb_path);
			$image->destroy();
			error($config['error']['nomove']);
		}
	}
	$image->destroy();

	@chmod($src_path, 0644);
	@chmod($thumb_path, 0644);

	$file['hash'] = md5_file($src_path) ?: '';
	$file['file_path'] = $src_path;
	$file['thumb_path'] = $thumb_path;
	$file['size'] = filesize($src_path) ?: $size_bytes;

	return $file;
}

// unlink_board_file_entry() lives in functions.php (available without loading this file).

class Image {
	public $src, $format, $image, $size;

	public function __construct($src, $format = false, $size = false) {
		global $config;

		$this->src = $src;
		$this->format = $format;

		$classname = 'Image' . strtoupper((string)$this->format);
		if (!class_exists($classname)) {
			error(_('Unsupported file format: ') . $this->format);
		}

		$this->image = new $classname($this, $size);

		if (!$this->image->valid()) {
			$this->delete();
			error($config['error']['invalidimg']);
		}

		$this->size = (object)['width' => $this->image->_width(), 'height' => $this->image->_height()];
		if ($this->size->width < 1 || $this->size->height < 1) {
			$this->delete();
			error($config['error']['invalidimg']);
		}
	}

	public function resize($extension, $max_width, $max_height) {
		$classname = 'Image' . strtoupper($extension);
		if (!class_exists($classname)) {
			error(_('Unsupported file format: ') . $extension);
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
			$height = (int)ceil($x_ratio * $this->size->height);
			$width = $max_width;
		} else {
			$width = (int)ceil($y_ratio * $this->size->width);
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

class ImageBase {
	public $image, $src, $original, $original_width, $original_height, $width, $height, $format;

	public function valid() {
		return (bool)$this->image;
	}

	public function __construct($img, $size = false) {
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
		return imagesx($this->image);
	}

	public function _height() {
		return imagesy($this->image);
	}

	public function _destroy() {
		return imagedestroy($this->image);
	}

	public function _resize($original, $width, $height) {
		$this->original = &$original;
		$this->width = $width;
		$this->height = $height;
		$this->image = imagecreatetruecolor($this->width, $this->height);
		if (method_exists($this, 'prepare_canvas')) {
			$this->prepare_canvas();
		}
		imagecopyresampled(
			$this->image,
			$this->original,
			0,
			0,
			0,
			0,
			$this->width,
			$this->height,
			$this->original_width,
			$this->original_height
		);
	}
}

class ImagePNG extends ImageBase {
	public function from() {
		$this->image = @imagecreatefrompng($this->src);
	}

	public function to($src) {
		imagepng($this->image, $src);
	}

	public function prepare_canvas() {
		imagecolortransparent($this->image, imagecolorallocatealpha($this->image, 0, 0, 0, 0));
		imagesavealpha($this->image, true);
		imagealphablending($this->image, false);
	}
}

class ImageGIF extends ImageBase {
	public function from() {
		$this->image = @imagecreatefromgif($this->src);
	}

	public function to($src) {
		imagegif($this->image, $src);
	}

	public function prepare_canvas() {
		imagecolortransparent($this->image, imagecolorallocatealpha($this->image, 0, 0, 0, 0));
		imagesavealpha($this->image, true);
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
