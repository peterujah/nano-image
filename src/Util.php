<?php
/**
 * Class Util.
 *
 * @author      Peter Chigozie(NG) peterujah
 * @copyright   Copyright (c), 2019 Peter(NG) peterujah
 * @license     MIT public license
 */
namespace Peterujah\Media;

use \GdImage;
use \Throwable;
use Peterujah\Media\NanoImage;
use Peterujah\Media\Exceptions\RuntimeException;
use Peterujah\Media\Exceptions\InvalidArgumentException;
use Peterujah\Media\Exceptions\UnsupportedImageException;

final class Util
{
    /**
	 * Apply the Gaussian blur, smoothing and brightness steps used by blur() to a GD image.
	 *
	 * The image is modified in place.
	 *
	 * @param GdImage|resource $image The GD image to blur.
	 * @param int $range The number of blur passes.
	 *
	 * @return GdImage|resource The same image that was passed in.
	 */
	public static function applyGaussianBlur(mixed $image, int $range = 5): mixed
	{
		if (!self::isImage($image)) {
			return $image;
		}

		for ($i = 0; $i < $range; $i++) {
			@imagefilter($image, IMG_FILTER_GAUSSIAN_BLUR);
		}

		@imagefilter($image, IMG_FILTER_SMOOTH, 99);
		@imagefilter($image, IMG_FILTER_BRIGHTNESS, 10);

		return $image;
	}

    /**
	 * Write a GD image as an uncompressed 24-bit BMP.
	 *
	 * Used when GD has no native `imagebmp()` (PHP < 7.2). The alpha channel is ignored.
	 *
	 * @param GdImage|resource $image The GD image to write.
	 * @param string|null $file Destination path, or null to send the bytes to the output.
	 * @param bool $compressed Kept for backward compatibility and ignored: the data is always uncompressed.
	 * @param int $quality -1 (default) keeps the size; 1-100 scales the width and height down to that percentage.
	 *
	 * @return bool True if the BMP was written, false otherwise.
	 */
	public static function image_bmp(
		$image,
		?string $file = null,
		bool $compressed = true,
		int $quality = -1
	): bool
	{
		if (
			!self::isImage($image) 
			|| ($quality !== -1 && ($quality < 1 
			|| $quality > 100))
		) {
			return false;
		}

		$width = imagesx($image);
		$height = imagesy($image);
		$work = $image;
		$newImage = false;

		try {
			if ($quality !== -1) {
				// Map quality (1 to 100) to a scaling factor (0.01 to 1.0)
				$scaleFactor = max(0.01, min($quality / 100, 1.0));
				$width = max(1, (int) ($width * $scaleFactor));
				$height = max(1, (int) ($height * $scaleFactor));
			}

			if ($quality !== -1 || !imageistruecolor($image)) {
				// Draw onto a white truecolor canvas: scales, converts palette images, drops alpha.
				$work = @imagecreatetruecolor($width, $height);

				if (!self::isImage($work)) {
					return false;
				}

				$newImage = true;
				imagefill($work, 0, 0, imagecolorallocate($work, 255, 255, 255));

				if (
					!imagecopyresampled(
						$work, 
						$image, 
						0, 0, 0, 0, 
						$width, 
						$height, 
						imagesx($image), 
						imagesy($image)
					)
				) {
					return false;
				}
			}

			$padding = (4 - ($width * 3) % 4) % 4;
			$imageDataSize = ($width * 3 + $padding) * $height;
			$imageHeaderSize = 54;

			$header = 'BM'
				. pack('V', $imageHeaderSize + $imageDataSize) // File size
				. pack('v', 0)                                 // Reserved
				. pack('v', 0)                                 // Reserved
				. pack('V', $imageHeaderSize)                  // Offset to pixel data
				. pack('V', 40)                                // DIB header size
				. pack('l', $width)                            // Width
				. pack('l', $height)                           // Positive height = rows stored bottom-up
				. pack('v', 1)                                 // Colour planes
				. pack('v', 24)                                // Bits per pixel
				. pack('V', 0)                                 // Compression: BI_RGB (none)
				. pack('V', $imageDataSize)                    // Pixel data size
				. pack('l', 2835)                              // Horizontal resolution (72 DPI)
				. pack('l', 2835)                              // Vertical resolution (72 DPI)
				. pack('V', 0)                                 // Palette colours
				. pack('V', 0);                                // Important colours

			$handle = null;

			if ($file !== null) {
				$handle = @fopen($file, 'wb');

				if ($handle === false) {
					return false;
				}
			}

			$ok = self::bmpWrite($handle, $header);
			$pad = str_repeat("\x00", $padding);

			// Rows are stored bottom-up, pixels as BGR.
			for ($y = $height - 1; $ok && $y >= 0; $y--) {
				$row = '';

				for ($x = 0; $x < $width; $x++) {
					$rgb = imagecolorat($work, $x, $y);
					$row .= chr($rgb & 0xFF) . chr(($rgb >> 8) & 0xFF) . chr(($rgb >> 16) & 0xFF);
				}

				$ok = self::bmpWrite($handle, $row . $pad);
			}

			if ($handle !== null) {
				$ok = fclose($handle) && $ok;

				if (!$ok) {
					@unlink($file);
				}
			}

			return $ok;
		} finally {
			if ($newImage) {
				self::destroy($work);
			}
		}
	}

	/**
	 * Encode a GD image in the given format.
	 *
	 * @param GdImage|resource $image The image to encode.
	 * @param string|null $file Destination path, or null to send the bytes to the output.
	 * @param int $quality Quality, 0 to 100 (clamped).
	 * @param int $type `IMAGETYPE_*` format.
	 *
	 * @return bool True if the image was encoded, false if the format 
	 * 		or its GD encoder is unavailable or encoding failed.
	 */
	public static function encode(
        mixed $image, 
        ?string $file, 
        int $quality, 
        int $type, 
        bool $maybeTransparent = false
    ): bool
	{
		$quality = max(0, min(100, (int) $quality));

		if (!self::encoderAvailable($type)) {
			return false;
		}

		try {
			if ($type === IMAGETYPE_JPEG) {
				return (bool) @imagejpeg($image, $file, $quality);
			}

			if ($type === IMAGETYPE_PNG) {
				return (bool) @imagepng($image, $file, min(9, (int) ($quality / 10)));
			}

			if ($type === IMAGETYPE_GIF) {
				$palette = self::toGifPalette($image, $maybeTransparent);

				if (!self::isImage($palette)) {
					return false;
				}

				try {
					return (bool) @imagegif($palette, $file);
				} finally {
					self::destroy($palette);
				}
			}

			if ($type === IMAGETYPE_WEBP) {
				return (bool) @imagewebp($image, $file, $quality);
			}

			if ($type === IMAGETYPE_AVIF) {
				return (bool) @imageavif($image, $file, $quality);
			}

			if ($type === IMAGETYPE_BMP) {
				return function_exists('imagebmp')
					? (bool) @imagebmp($image, $file, false)
					: self::image_bmp($image, $file);
			}
		} catch (Throwable) {
			return false;
		}

		return false;
	}

    /**
	 * Write a chunk of BMP data to a file handle, or to the output when the handle is null.
	 *
	 * @param resource|null $handle The open file handle, or null for output.
	 * @param string $bytes The data to write.
	 *
	 * @return bool True if all bytes were written.
	 */
	private static function bmpWrite(mixed $handle, string $bytes): bool
	{
		if ($handle === null) {
			echo $bytes;
			return true;
		}

		return fwrite($handle, $bytes) === strlen($bytes);
	}

	/**
	 * Make a palette copy of an image that GD can write as a GIF with transparency.
	 *
	 * The image is flattened onto white and reduced to at most 255 colours; then, if the image
	 * may be transparent, every pixel that is more than about half transparent is set to a
	 * reserved transparent palette entry (GIF only has on/off transparency). The input image
	 * is not modified.
	 *
	 * @param GdImage|resource $image A truecolor image, possibly with alpha.
	 *
	 * @return GdImage|resource|false The palette image (caller must release it), or false on failure.
	 */
	private static function toGifPalette(mixed $image, bool $maybeTransparent): mixed
	{
		$width = imagesx($image);
		$height = imagesy($image);

		if (!self::canAllocate($width, $height)) {
			return false;
		}

		$palette = self::createFlatCanvas($width, $height);

		if (!self::isImage($palette)) {
			return false;
		}

		if (!imagecopy($palette, $image, 0, 0, 0, 0, $width, $height)) {
			self::destroy($palette);
			return false;
		}

		imagetruecolortopalette($palette, true, 255);

		if (!$maybeTransparent) {
			return $palette;
		}

		$key = false;

		for ($y = 0; $y < $height; $y++) {
			for ($x = 0; $x < $width; $x++) {
				if (((imagecolorat($image, $x, $y) >> 24) & 0x7F) <= 63) {
					continue;
				}

				if ($key === false) {
					$key = imagecolorallocate($palette, 255, 0, 255);

					if ($key === false || $key < 0) {
						return $palette; // palette is full: keep the opaque version
					}

					imagecolortransparent($palette, $key);
				}

				imagesetpixel($palette, $x, $y, $key);
			}
		}

		return $palette;
	}

	/**
	 * Convert a freshly decoded image into a truecolor image with alpha support.
	 *
	 * Palette images (GIF, 8-bit PNG) are expanded to truecolor, keeping transparency.
	 * Alpha blending is switched off and alpha saving on, so pixels are copied exactly.
	 *
	 * @param GdImage|resource $image The decoded image (consumed).
	 *
	 * @return GdImage|resource|false The prepared image, or false on failure.
	 */
	public static function normalize(mixed $image): mixed
	{
		if (!self::isImage($image)) {
			return false;
		}

		if (imageistruecolor($image)) {
			imagealphablending($image, false);
			imagesavealpha($image, true);
			return $image;
		}

		$width = imagesx($image);
		$height = imagesy($image);

		if (!self::canAllocate($width, $height)) {
			self::destroy($image);
			return false;
		}

		$canvas = self::createCanvas($width, $height);

		if (!self::isImage($canvas) || !imagecopy($canvas, $image, 0, 0, 0, 0, $width, $height)) {
			self::destroy($canvas);
			self::destroy($image);
			return false;
		}

		self::destroy($image);

		return $canvas;
	}

	/**
	 * Create a fully transparent truecolor canvas that keeps alpha when copied onto.
	 *
	 * @param int $width Canvas width (greater than 0).
	 * @param int $height Canvas height (greater than 0).
	 *
	 * @return GdImage|resource|false The canvas, or false on failure.
	 */
	public static function createCanvas(int $width, int $height): mixed
	{
		if ($width <= 0 || $height <= 0) {
			return false;
		}

		try {
			$canvas = @imagecreatetruecolor($width, $height);
		} catch (Throwable) {
			return false;
		}

		if (!self::isImage($canvas)) {
			return false;
		}

		imagealphablending($canvas, false);
		imagesavealpha($canvas, true);
		imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 0, 0, 0, 127));

		return $canvas;
	}

	/**
	 * Create a white truecolor canvas for formats without transparency.
	 *
	 * Alpha blending stays on, so transparent pixels copied onto it end up white.
	 *
	 * @param int $width Canvas width (greater than 0).
	 * @param int $height Canvas height (greater than 0).
	 *
	 * @return GdImage|resource|false The canvas, or false on failure.
	 */
	public static function createFlatCanvas(int $width, int $height): mixed
	{
		if ($width <= 0 || $height <= 0) {
			return false;
		}

		try {
			$canvas = @imagecreatetruecolor($width, $height);
		} catch (Throwable) {
			return false;
		}

		if (!self::isImage($canvas)) {
			return false;
		}

		imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
		imagealphablending($canvas, true);

		return $canvas;
	}

    /**
	 * Allocate a transparent canvas, or throw if memory is short.
	 *
	 * @param int $width Canvas width.
	 * @param int $height Canvas height.
	 *
	 * @return GdImage|resource The canvas.
	 *
	 * @throws RuntimeException If the canvas cannot be allocated.
	 */
	public static function newCanvas(int $width, int $height): mixed
	{
		if (!self::canAllocate($width, $height)) {
			throw new RuntimeException(
				'Not enough memory to process the image.'
			);
		}

		$canvas = self::createCanvas($width, $height);

		if (!self::isImage($canvas)) {
			throw new RuntimeException(
				'Failed to allocate memory to process the image.'
			);
		}

		return $canvas;
	}

	/**
	 * Check whether a value is a GD image (`GdImage` on PHP 8+, `gd` resource on PHP 7).
	 *
	 * @param mixed $image The value to test.
	 *
	 * @return bool
	 */
	public static function isImage(mixed $image): bool
	{
		return ($image instanceof GdImage)
			|| (is_resource($image) && get_resource_type($image) === 'gd');
	}

	/**
	 * Release a GD image. Accepts anything; non-images are ignored.
	 *
	 * On PHP 8.0-8.4 imagedestroy() does nothing (memory is freed once the last reference
	 * goes), and on 8.5 it is deprecated, so it is only called where it has an effect.
	 *
	 * @param mixed $image The image to release.
	 *
	 * @return void
	 */
	public static function destroy(mixed $image): void
	{
		if (self::isImage($image) && PHP_VERSION_ID < 80500) {
			@imagedestroy($image);
		}

		$image = false;
	}

	/**
	 * Remove buffers opened above the given level.
	 *
	 * @param int $bufferLevel The output buffer level to keep.
	 *
	 * @return void
	 */
	public static function clearOutput($bufferLevel = 0)
	{
		while (ob_get_level() > $bufferLevel) {
			if (!ob_end_clean()) {
				break;
			}
		}
	}

	/**
	 * Require a value greater than zero.
	 *
	 * @param int $value The value to test.
	 * @param string $label Name used in the error message.
	 *
	 * @return void
	 *
	 * @throws InvalidArgumentException If the value is not greater than 0.
	 */
	public static function assertPositive(int $value, string $label): void
	{
		if ($value <= 0) {
			throw new InvalidArgumentException(
				"{$label} must be greater than 0."
			);
		}
	}

	/**
	 * Parse a hex colour.
	 *
	 * @param string $color `#rgb`, `#rrggbb`, `rgb` or `rrggbb`.
	 *
	 * @return int[] Red, green and blue (0-255 each).
	 *
	 * @throws InvalidArgumentException If the value is not a hex colour.
	 */
	public static function parseColor(string $color)
	{
		$hex = is_string($color) ? ltrim($color, '#') : '';

		if (!preg_match('/^[0-9a-fA-F]{3}$|^[0-9a-fA-F]{6}$/', $hex)) {
			throw new InvalidArgumentException(
				'Background must be a hex colour such as "#ffffff" or "#fff".'
			);
		}

		if (strlen($hex) === 3) {
			$hex = $hex[0] 
				. $hex[0] 
				. $hex[1] 
				. $hex[1] 
				. $hex[2] 
				. $hex[2];
		}

		return [
			hexdec(substr($hex, 0, 2)), 
			hexdec(substr($hex, 2, 2)), 
			hexdec(substr($hex, 4, 2))
		];
	}

	/**
	 * Whether the image type is one NanoImage can read and write.
	 *
	 * @param int $type An `IMAGETYPE_*` value.
	 *
	 * @return bool
	 */
	public static function isSupportedType(int $type): bool
	{
		return match($type){
			IMAGETYPE_JPEG, 
			IMAGETYPE_PNG, 
			IMAGETYPE_GIF, 
			IMAGETYPE_BMP, 
			IMAGETYPE_WEBP, 
			IMAGETYPE_AVIF => true,
			default 	   => false
		};
	}

	/**
	 * Whether the format can store transparency.
	 *
	 * @param int $type An `IMAGETYPE_*` value.
	 *
	 * @return bool
	 */
	public static function isAlphaSupported(int $type): bool
	{
		return $type === IMAGETYPE_PNG
			|| $type === IMAGETYPE_GIF
			|| $type === IMAGETYPE_WEBP
			|| $type === IMAGETYPE_AVIF;
	}

	/**
	 * Name of the GD function that decodes a file of the given type, if GD has it.
	 *
	 * @param int $type An `IMAGETYPE_*` value.
	 *
	 * @return string|null The function name, or null if the type is unsupported or GD lacks the decoder.
	 */
	public static function decoderFor(int $type): ?string
	{
		$handler = match($type){
			IMAGETYPE_JPEG   => 'imagecreatefromjpeg',
			IMAGETYPE_PNG    => 'imagecreatefrompng',
			IMAGETYPE_GIF    => 'imagecreatefromgif',
			IMAGETYPE_BMP    => 'imagecreatefrombmp',
			IMAGETYPE_WEBP   => 'imagecreatefromwebp',
			IMAGETYPE_AVIF   => 'imagecreatefromavif',
			default 		 => null
		};

		if($handler === null || !function_exists($handler)){
			return null;
		}

		return $handler;
	}

	/**
	 * Whether this PHP/GD build can write the given format.
	 *
	 * @param int $type An `IMAGETYPE_*` value.
	 *
	 * @return bool
	 */
	public static function encoderAvailable(int $type): bool
	{
		if ($type === IMAGETYPE_BMP) {
			return true;
		}

		$handler = match($type){
			IMAGETYPE_JPEG   => 'imagejpeg',
			IMAGETYPE_PNG  	 => 'imagepng',
			IMAGETYPE_GIF 	 => 'imagegif',
			IMAGETYPE_WEBP   => 'imagewebp',
			IMAGETYPE_AVIF   => 'imageavif',
			default 		 => null
		};

		return $handler !== null 
			&& function_exists($handler);
	}

	/**
	 * Map a file extension to an `IMAGETYPE_*` value.
	 *
	 * @param string $extension Lower-case extension without a dot.
	 *
	 * @return int The type, or 0 if the extension is not supported.
	 */
	public static function typeFromExtension(string $extension): int
	{
		return match ($extension) {
			NanoImage::JPG,
			NanoImage::JPEG => IMAGETYPE_JPEG,
			NanoImage::PNG  => IMAGETYPE_PNG,
			NanoImage::GIF  => IMAGETYPE_GIF,
			NanoImage::WEBP => IMAGETYPE_WEBP,
			NanoImage::BMP  => IMAGETYPE_BMP,
			NanoImage::AVIF => IMAGETYPE_AVIF,
			default    => 0
		};
	}

	/**
	 * Map an `IMAGETYPE_*` value to a file extension.
	 *
	 * @param int $type An `IMAGETYPE_*` value.
	 *
	 * @return string The extension without a dot, or an empty string for unknown types.
	 */
	public static function extensionForType(int $type): string
	{
		return match ($type) {
			IMAGETYPE_JPEG 	 => NanoImage::JPG,
			IMAGETYPE_PNG  	 => NanoImage::PNG,
			IMAGETYPE_GIF  	 => NanoImage::GIF,
			IMAGETYPE_BMP  	 => NanoImage::BMP,
			IMAGETYPE_WEBP   => NanoImage::WEBP,
			IMAGETYPE_AVIF   => NanoImage::AVIF,
			default    		 => ''
		};
	}

	/**
	 * The PHP memory limit in bytes.
	 *
	 * @return int Bytes, or -1 when there is no limit.
	 */
	private static function memoryLimit(): int
	{
		$value = trim((string) ini_get('memory_limit'));

		if ($value === '' || (int) $value <= 0) {
			return -1;
		}

		$number = (int) $value;

		switch (strtolower(substr($value, -1))) {
			case 'g':
				$number *= 1024;
			case 'm':
				$number *= 1024;
			case 'k':
				$number *= 1024;
		}

		return $number;
	}

	/**
	 * Whether a truecolor image of the given size fits in the remaining memory.
	 *
	 * Uses 4 bytes per pixel plus 1 MB of slack. Always true when memory_limit is unlimited.
	 *
	 * @param int $width Image width.
	 * @param int $height Image height.
	 *
	 * @return bool
	 */
	public static function canAllocate(int $width, int $height): bool
	{
		$limit = self::memoryLimit();

		if ($limit < 0) {
			return true;
		}

		return ($limit - memory_get_usage(true)) >= ($width * $height * 4 + 1048576);
	}

	/**
	 * Refuse to decode an image that cannot fit in memory, instead of dying with a fatal error.
	 *
	 * @param int $width Image width.
	 * @param int $height Image height.
	 *
	 * @return void
	 *
	 * @throws UnsupportedImageException If the decoded image would exceed the available memory.
	 */
	public static function assertMemory(int $width, int $height): void
	{
		if (!self::canAllocate($width, $height)) {
			throw new UnsupportedImageException(
				"Image dimensions {$width}x{$height} need more memory than PHP's memory_limit allows."
			);
		}
	}
}
