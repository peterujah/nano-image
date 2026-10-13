<?php
/**
 * Class NanoImage.
 *
 * A lightweight image helper built on PHP's GD extension. It can resize, crop,
 * scale, rotate, flip, filter and blur an image, convert between formats, and then
 * either send the result to the browser, return it as a string or save it to disk.
 *
 * Requires PHP 8.0+ and the GD extension.
 *
 * @author      Peter Chigozie(NG) peterujah
 * @copyright   Copyright (c), 2019 Peter(NG) peterujah
 * @license     MIT public license
 */
namespace Peterujah\Media;

use \GdImage;
use \Throwable;
use Peterujah\Media\Util;
use Peterujah\Media\Exceptions\LogicException;
use Peterujah\Media\Exceptions\RuntimeException;
use Peterujah\Media\Exceptions\InvalidArgumentException;
use Peterujah\Media\Exceptions\UnsupportedImageException;

class NanoImage
{
	/**
	 * Save image type as JPG.
	 *
	 * @var string JPG
	 */
	public const JPG = "jpg";

	/**
	 * Save image type as JPEG.
	 *
	 * @var string JPEG
	 */
	public const JPEG = "jpeg";

	/**
	 * Save image type as PNG.
	 *
	 * @var string PNG
	 */
	public const PNG = "png";

	/**
	 * Save image type as GIF.
	 *
	 * @var string GIF
	 */
	public const GIF = "gif";

	/**
	 * Save image type as WEBP.
	 *
	 * @var string WEBP
	 */
	public const WEBP = "webp";

	/**
	 * Save image type as BMP.
	 *
	 * @var string BMP
	 */
	public const BMP = "bmp";

	/**
	 * Save image type as AVIF (requires PHP 8.1+ with AVIF support in GD).
	 *
	 * @var string AVIF
	 */
	public const AVIF = "avif";

	/**
	 * Save naming mode: append the requested size to the file name, e.g. `photo-360x200.jpg`.
	 *
	 * @var int THUMBNAIL
	 */
	public const THUMBNAIL = 1;

	/**
	 * Save naming mode: append the current date and time to the file name,
	 * e.g. `photo-13-10-26-17-30-05.jpg`. Existing files are never overwritten in this mode.
	 *
	 * @var int TIMESTAMP
	 */
	public const TIMESTAMP = 2;

	/**
	 * Save naming mode: use the given file name exactly. An existing file is overwritten.
	 *
	 * @var int DEFAULT
	 */
	public const DEFAULT = 0;

	/**
	 * Flip mode for flip(): mirror left to right.
	 *
	 * @var int FLIP_HORIZONTAL
	 */
	public const FLIP_HORIZONTAL = 1;

	/**
	 * Flip mode for flip(): mirror top to bottom.
	 *
	 * @var int FLIP_VERTICAL
	 */
	public const FLIP_VERTICAL = 2;

	/**
	 * Flip mode for flip(): mirror both ways (same as a 180 degree rotation).
	 *
	 * @var int FLIP_BOTH
	 */
	public const FLIP_BOTH = 3;

	/**
	 * Path of the file the image was opened from (empty when loaded from a string).
	 *
	 * @var string $imagePath
	 */
	private string $imagePath = '';

	/**
	 * The GD image: a `GdImage` object on PHP 8+, a `gd` resource on PHP 7, or false.
	 *
	 * @var GdImage|resource|false $imageData
	 */
	private mixed $imageData = false;

	/**
	 * Filters queued with filter()/filters(); they are applied when the image is written.
	 *
	 * @var array $filters
	 */
	private array $filters = [];

	/**
	 * Requested output height (before aspect-ratio fitting).
	 *
	 * @var int $newHeight
	 */
	private int $newHeight = 0;

	/**
	 * Requested output width (before aspect-ratio fitting).
	 *
	 * @var int $newWidth
	 */
	private int $newWidth = 0;

	/**
	 * Width requested through resize(), used for THUMBNAIL file names (0 when not set).
	 *
	 * @var int $boxWidth
	 */
	private int $boxWidth = 0;

	/**
	 * Height requested through resize(), used for THUMBNAIL file names (0 when not set).
	 *
	 * @var int $boxHeight
	 */
	private int $boxHeight = 0;

	/**
	 * Height of the pixel data currently held in memory.
	 *
	 * @var int $height
	 */
	private int $height = 0;

	/**
	 * Width of the pixel data currently held in memory.
	 *
	 * @var int $width
	 */
	private int $width = 0;

	/**
	 * MIME type of the source image.
	 *
	 * @var string $imageMime
	 */
	private string $imageMime = '';

	/**
	 * `IMAGETYPE_*` value of the source image.
	 *
	 * @var int $imageIntType
	 */
	private int $imageIntType = 0;

	/**
	 * Extension used by the most recent save.
	 *
	 * @var string $extension
	 */
	private string $extension = '';

	/**
	 * Target directory of the save in progress.
	 *
	 * @var string $dirname
	 */
	private string $dirname = '';

	/**
	 * Target file name (without extension) of the save in progress.
	 *
	 * @var string $filename
	 */
	private string $filename = '';

	/**
	 * Target path of the save in progress.
	 *
	 * @var string $finalPath
	 */
	private string $finalPath = '';

	/**
	 * Path of the file written by the last successful save.
	 *
	 * @var string $savedPath
	 */
	private string $savedPath = '';

	/**
	 * Whether to fit the output inside the requested width/height while keeping the aspect ratio.
	 *
	 * @var bool $useRatio
	 */
	private bool $useRatio = false;

	/**
	 * Whether resize() has been called.
	 *
	 * @var bool $isResize
	 */
	private bool $isResize = false;

	/**
	 * Whether the pixel data may contain transparency (used to skip needless copies).
	 *
	 * @var bool $maybeTransparent
	 */
	private bool $maybeTransparent = false;

	/**
	 * Initialize the Nano Image class.
	 *
	 * @param string|null $source Optional image source: the path of an image file, or the raw
	 *                            image data. When omitted, call open() or load() afterwards.
	 *
	 * @throws UnsupportedImageException If the GD extension is missing, or the source cannot be loaded.
	 * @throws InvalidArgumentException If `$source` is neither null nor a string.
	 */
	public function __construct(?string $source = null)
	{
		if (!extension_loaded('gd')) {
			throw new UnsupportedImageException(
				'The GD extension is required to use NanoImage.'
			);
		}

		defined('IMAGETYPE_WEBP') || define('IMAGETYPE_WEBP', 18);
		defined('IMAGETYPE_AVIF') || define('IMAGETYPE_AVIF', 19);

		if ($source === null || $source === '') {
			return;
		}

		if (!is_string($source)) {
			throw new InvalidArgumentException(
				'The image source must be a string (file path or image data).'
			);
		}

		if (strpos($source, "\0") === false && is_file($source)) {
			$this->open($source);
			return;
		}

		$this->load($source);
	}

	/**
	 * Load an image from a file.
	 *
	 * Anything `getimagesize()` can read works, including URLs when `allow_url_fopen` is on.
	 * Supported formats: JPEG, PNG, GIF (first frame only), BMP (PHP 7.2+), WebP (PHP 7.1+)
	 * and AVIF (PHP 8.1+), each only if your GD build supports it.
	 *
	 * Opening an image replaces any image already held by this instance and
	 * resets all pending resize, filter and save settings.
	 *
	 * @param string $filename Path or URL of the image.
	 *
	 * @return self Returns the image instance.
	 *
	 * @throws UnsupportedImageException If the file is missing, invalid, of an unsupported
	 *                                   format, or too large for the available memory.
	 */
	public function open(string $filename): self
	{
		if ($filename === '' || strpos($filename, "\0") !== false) {
			throw new UnsupportedImageException('Failed to load image: invalid file name.');
		}

		$size = @getimagesize($filename);

		if ($size === false) {
			throw new UnsupportedImageException(
				"Failed to load image from: {$filename}."
			);
		}

		$width = (int) ($size[0] ?? 0);
		$height = (int) ($size[1] ?? 0);
		$type = (int) ($size[2] ?? 0);
		$mime = (string) ($size['mime'] ?? '');

		if ($width <= 0 || $height <= 0) {
			throw new UnsupportedImageException(
				'Invalid or unsupported image dimensions.'
			);
		}

		$decoder = Util::decoderFor($type);

		if ($decoder === null) {
			throw new UnsupportedImageException(
				"Unsupported image type: {$type}, {$mime}."
			);
		}

		Util::assertMemory($width, $height);

		$image = @$decoder($filename);

		if (!Util::isImage($image)) {
			throw new UnsupportedImageException(
				"Failed to decode image from: {$filename}."
			);
		}

		return $this->parse($image, $width, $height, $type, $mime, $filename);
	}

	/**
	 * Load an image from a string of image data.
	 *
	 * Supported formats are the same as for open(). Loading replaces any image
	 * already held by this instance and resets all pending settings.
	 *
	 * @param string $data The raw image data.
	 *
	 * @return self Returns the image instance.
	 *
	 * @throws UnsupportedImageException If the data is empty, not a supported image, or too
	 *                                   large for the available memory.
	 */
	public function load(string $data): self
	{
		if ($data === '') {
			throw new UnsupportedImageException('Image data cannot be an empty string.');
		}

		$info = @getimagesizefromstring($data);

		if ($info === false) {
			throw new UnsupportedImageException(
				'Failed to create image from data: unrecognized image format.'
			);
		}

		$width = (int) $info[0];
		$height = (int) $info[1];
		$type = (int) $info[2];
		$mime = (string) ($info['mime'] ?? '');

		if ($width <= 0 || $height <= 0 || !Util::isSupportedType($type)) {
			throw new UnsupportedImageException(
				"Unsupported image type: {$type}, {$mime}."
			);
		}

		Util::assertMemory($width, $height);

		$image = @imagecreatefromstring($data);

		if (!Util::isImage($image)) {
			throw new UnsupportedImageException(
				'Failed to create image from data.'
			);
		}

		return $this->parse($image, $width, $height, $type, $mime);
	}

	/**
	 * Get the width of the image data currently held in memory.
	 *
	 * This is the loaded width, updated by crop(), scale() and rotate(). A pending
	 * resize()/setWidth() is not included until the image is written.
	 *
	 * @return int The width in pixels (0 when no image is loaded).
	 */
	public function getWidth(): int
	{
		return $this->width;
	}

	/**
	 * Get the height of the image data currently held in memory.
	 *
	 * See getWidth() for what is and is not included.
	 *
	 * @return int The height in pixels (0 when no image is loaded).
	 */
	public function getHeight(): int
	{
		return $this->height;
	}

	/**
	 * Get the MIME type of the source image (e.g., 'image/jpeg', 'image/png').
	 *
	 * @return string The MIME type, or an empty string when no image is loaded.
	 */
	public function getMimeType(): string
	{
		return $this->imageMime;
	}

	/**
	 * Get the image extension (e.g., 'jpg', 'png').
	 *
	 * Returns the extension used by the last save() / saveAs() / replace() call or, before
	 * any save, the extension that matches the source format.
	 *
	 * @return string The file extension without a dot, or an empty string when unknown.
	 */
	public function getExtension(): string
	{
		if ($this->extension !== '') {
			return $this->extension;
		}

		return Util::extensionForType($this->imageIntType);
	}

	/**
	 * Get the path of the file written by the last successful save.
	 *
	 * Useful with NanoImage::THUMBNAIL and NanoImage::TIMESTAMP, which change the file name.
	 *
	 * @return string The saved file path, or an empty string if nothing has been saved.
	 */
	public function getSavedPath(): string
	{
		return $this->savedPath;
	}

	/**
	 * Retrieves EXIF metadata from the loaded image file.
	 *
	 * Returns the available metadata, such as camera details, capture date, exposure
	 * settings, image dimensions, and GPS information, depending on the image and
	 * its embedded metadata. Requires the PHP EXIF extension and a valid image file.
	 *
	 * @param bool $asArray Whether to return metadata grouped into sections (default: true).
	 * @param bool $readThumbnail Whether to read the embedded thumbnail, if available (default: false).
	 *
	 * @return array Returns the EXIF metadata as an array, or an empty array if the image
	 *               file is unavailable, the EXIF extension is not installed, or reading
	 *               the metadata fails.
	 */
	public function getExifData(bool $asArray = true, bool $readThumbnail = false): array
	{
		if (
			$this->imagePath === '' 
			|| !is_file($this->imagePath)
			|| !function_exists('exif_read_data') 
		) {
			return [];
		}

		$exif = @exif_read_data(
			$this->imagePath, 
			null, 
			$asArray,
			$readThumbnail
		);

		if ($exif === false) {
			return [];
		}

		return $exif;
	}

	/**
	 * Check if resize() has been called.
	 *
	 * @return bool True if resized, false otherwise.
	 */
	public function isResized(): bool
	{
		return $this->isResize;
	}

	/**
	 * Set a new image height.
	 *
	 * With aspectRatio(true) the output is fitted inside the width/height box. A dimension
	 * that was never set defaults to the loaded size.
	 *
	 * @param int $height The desired height in pixels (greater than 0).
	 *
	 * @return self Return the image class instance.
	 *
	 * @throws InvalidArgumentException If `$height` is not greater than 0.
	 */
	public function setHeight(int $height): self
	{
		Util::assertPositive($height, 'Height');
		$this->newHeight = $height;

		return $this;
	}

	/**
	 * Set a new image width.
	 *
	 * See setHeight() for how this interacts with aspectRatio().
	 *
	 * @param int $width The desired width in pixels (greater than 0).
	 *
	 * @return self Return the image class instance.
	 *
	 * @throws InvalidArgumentException If `$width` is not greater than 0.
	 */
	public function setWidth(int $width): self
	{
		Util::assertPositive($width, 'Width');
		$this->newWidth = $width;

		return $this;
	}

	/**
	 * Set whether to keep the aspect ratio when the image is written.
	 *
	 * When enabled, the output is the largest size that fits inside the requested
	 * width/height box without distortion.
	 *
	 * @param bool $ratio True to keep the aspect ratio, false to stretch to the exact size.
	 *
	 * @return self Return the image class instance.
	 */
	public function aspectRatio(bool $ratio = true): self
	{
		$this->useRatio = $ratio;
		return $this;
	}

	/**
	 * Resize the image.
	 *
	 * The resize is applied when the image is written (or earlier when crop(), scale(),
	 * rotate() or blur() is called after it). Enlarging is allowed.
	 *
	 * @param int $width The target width in pixels (greater than 0).
	 * @param int $height The target height in pixels (greater than 0).
	 * @param bool $ratio False to resize to exactly `$width` x `$height`; true to fit inside
	 *                    that box while keeping the aspect ratio.
	 *
	 * @return self Return the image class instance.
	 *
	 * @throws InvalidArgumentException If `$width` or `$height` is not greater than 0.
	 */
	public function resize(int $width, int $height, bool $ratio = false): self
	{
		Util::assertPositive($width, 'Width');
		Util::assertPositive($height, 'Height');

		$this->newWidth = $width;
		$this->newHeight = $height;
		$this->boxWidth = $width;
		$this->boxHeight = $height;
		$this->useRatio = $ratio;
		$this->isResize = true;

		return $this;
	}

	/**
	 * Queue a single GD filter, applied when the image is written.
	 *
	 * If GD rejects a filter (unknown constant, missing arguments), writing the image fails
	 * and the writing method returns false.
	 *
	 * @param int $filter The image filter constant (e.g., `IMG_FILTER_GRAYSCALE`).
	 * @param mixed ...$args Optional additional parameters for certain filters.
	 *
	 * @return self Return the image class instance.
	 *
	 * @example - Queue filters.
	 * ```php
	 * $image->filter(IMG_FILTER_BRIGHTNESS, 50);
	 * $image->filter(IMG_FILTER_CONTRAST, -20);
	 * ```
	 */
	public function filter(int $filter, ...$args): self
	{
		$this->filters[] = array_merge([$filter], $args);
		return $this;
	}

	/**
	 * Queue several GD filters, applied in order when the image is written.
	 *
	 * @param array $filters A list of filters. Each item is an array whose first element is the
	 *                       `IMG_FILTER_*` constant followed by that filter's arguments
	 *                       (a bare constant is accepted too).
	 *
	 * @return self Return the image class instance.
	 *
	 * @example - Queue several filters.
	 * ```php
	 * $image->filters([
	 *     [IMG_FILTER_BRIGHTNESS, 20],
	 *     [IMG_FILTER_GAUSSIAN_BLUR],
	 *     [IMG_FILTER_COLORIZE, 100, 50, 0], // red, green, blue
	 * ]);
	 * ```
	 */
	public function filters(array $filters): self
	{
		foreach ($filters as $filter) {
			$this->filters[] = (array) $filter;
		}

		return $this;
	}

	/**
	 * Queue a grayscale conversion (shortcut for `filter(IMG_FILTER_GRAYSCALE)`).
	 *
	 * @return self Return the image class instance.
	 */
	public function grayscale(): self
	{
		return $this->filter(IMG_FILTER_GRAYSCALE);
	}

	/**
	 * Queue a brightness change (shortcut for `filter(IMG_FILTER_BRIGHTNESS, $level)`).
	 *
	 * @param int $level From -255 (darkest) to 255 (brightest); 0 changes nothing.
	 *
	 * @return self Return the image class instance.
	 *
	 * @throws InvalidArgumentException If `$level` is outside -255..255.
	 */
	public function brightness(int $level): self
	{
		if ($level < -255 || $level > 255) {
			throw new InvalidArgumentException(
				'Brightness level must be between -255 and 255.'
			);
		}

		return $this->filter(IMG_FILTER_BRIGHTNESS, $level);
	}

	/**
	 * Queue a contrast change.
	 *
	 * GD's own contrast filter treats negative numbers as "more contrast"; this method
	 * flips the sign so that a positive level increases contrast.
	 *
	 * @param int $level From -100 (flattest) to 100 (strongest contrast); 0 changes nothing.
	 *
	 * @return self Return the image class instance.
	 *
	 * @throws InvalidArgumentException If `$level` is outside -100..100.
	 */
	public function contrast(int $level): self
	{
		if ($level < -100 || $level > 100) {
			throw new InvalidArgumentException('Contrast level must be between -100 and 100.');
		}

		return $this->filter(IMG_FILTER_CONTRAST, -$level);
	}

	/**
	 * Apply a blur effect to the image immediately.
	 *
	 * A pending resize is applied first, so the blur is computed at the final size. The image is
	 * scaled down to a quarter, blurred `$range` times, scaled up to half size and blurred
	 * 25 more times, then scaled back to full size. Smoothing and a small brightness increase
	 * are applied on each pass, so blurred images come out slightly brighter.
	 *
	 * @param int $range The number of blur passes on the quarter-size copy (default 5).
	 *
	 * @return self Returns the current NanoImage instance.
	 *
	 * @throws LogicException If no image is loaded.
	 * @throws RuntimeException If memory runs out or a GD operation fails.
	 */
	public function blur(int $range = 5): self
	{
		$this->requireImage();
		$this->commitResize();

		$width = $this->width;
		$height = $this->height;
		$smallWidth = max(1, intdiv($width, 4));
		$smallHeight = max(1, intdiv($height, 4));
		$mediumWidth = max(1, intdiv($width, 2));
		$mediumHeight = max(1, intdiv($height, 2));

		if (
			!Util::canAllocate($smallWidth, $smallHeight) 
			|| !Util::canAllocate($mediumWidth, $mediumHeight)
		) {
			throw new RuntimeException('Not enough memory to blur the image.');
		}

		$imageSmall = false;
		$imageMedium = false;

		try {
			$imageSmall = Util::createCanvas($smallWidth, $smallHeight);
			$imageMedium = Util::createCanvas($mediumWidth, $mediumHeight);

			if (!Util::isImage($imageSmall) || !Util::isImage($imageMedium)) {
				throw new RuntimeException('Failed to allocate memory to blur the image.');
			}

			/* Scale to 25% and apply the blur */
			if (!imagecopyresampled(
				$imageSmall, $this->imageData,
				0, 0, 0, 0,
				$smallWidth, $smallHeight,
				$width, $height
			)) {
				throw new RuntimeException('Failed to scale the image for blurring.');
			}

			Util::applyGaussianBlur($imageSmall, $range);

			/* Scale the result to 50% and blur again */
			if (!imagecopyresampled(
				$imageMedium, $imageSmall,
				0, 0, 0, 0,
				$mediumWidth, $mediumHeight,
				$smallWidth, $smallHeight
			)) {
				throw new RuntimeException('Failed to scale the image for blurring.');
			}

			Util::applyGaussianBlur($imageMedium, 25);

			/* Scale the result back to the full size of the image */
			if (!imagecopyresampled(
				$this->imageData, 
				$imageMedium,
				0, 0, 0, 0,
				$width, 
				$height,
				$mediumWidth, 
				$mediumHeight
			)) {
				throw new RuntimeException('Failed to scale the blurred image back to full size.');
			}
		} finally {
			Util::destroy($imageSmall);
			Util::destroy($imageMedium);
		}

		return $this;
	}

	/**
	 * Crop the image to a rectangle, immediately.
	 *
	 * A pending resize is applied first, so the coordinates refer to the resized image.
	 * Transparency is preserved.
	 *
	 * @param int $x Left edge of the area, in pixels (0 or more).
	 * @param int $y Top edge of the area, in pixels (0 or more).
	 * @param int $width Width of the area (greater than 0).
	 * @param int $height Height of the area (greater than 0).
	 *
	 * @return self Return the image class instance.
	 *
	 * @throws LogicException If no image is loaded.
	 * @throws InvalidArgumentException If the area is empty or extends beyond the image.
	 * @throws RuntimeException If memory runs out or a GD operation fails.
	 */
	public function crop(int $x, int $y, int $width, int $height): self
	{
		$this->requireImage();

		if ($x < 0 || $y < 0) {
			throw new InvalidArgumentException('Crop position cannot be negative.');
		}

		Util::assertPositive($width, 'Crop width');
		Util::assertPositive($height, 'Crop height');

		$this->commitResize();

		if ($x + $width > $this->width || $y + $height > $this->height) {
			throw new InvalidArgumentException(
				"Crop area {$width}x{$height} at {$x},{$y} exceeds the image size {$this->width}x{$this->height}."
			);
		}

		$canvas = Util::newCanvas($width, $height);

		try {
			if (!imagecopy($canvas, $this->imageData, 0, 0, $x, $y, $width, $height)) {
				throw new RuntimeException('Failed to crop the image.');
			}
		} catch (Throwable $e) {
			Util::destroy($canvas);
			throw $e;
		}

		$this->replaceImage($canvas, $width, $height);
		$this->boxWidth = 0;
		$this->boxHeight = 0;

		return $this;
	}

	/**
	 * Scale the image by a factor, keeping its proportions, immediately.
	 *
	 * A pending resize is applied first. The result is never smaller than 1x1 pixel.
	 *
	 * @param float $factor Scale factor greater than 0: 0.5 halves each side, 2 doubles them.
	 *
	 * @return self Return the image class instance.
	 *
	 * @throws LogicException If no image is loaded.
	 * @throws InvalidArgumentException If `$factor` is not a finite number greater than 0.
	 * @throws RuntimeException If memory runs out or a GD operation fails.
	 */
	public function scale(float $factor): self
	{
		$this->requireImage();

		if (!is_finite($factor) || $factor <= 0) {
			throw new InvalidArgumentException('Scale factor must be greater than 0.');
		}

		$this->commitResize();

		$width = max(1, (int) round($this->width * $factor));
		$height = max(1, (int) round($this->height * $factor));

		$this->resampleTo($width, $height);
		$this->boxWidth = 0;
		$this->boxHeight = 0;

		return $this;
	}

	/**
	 * Rotate the image counter-clockwise, immediately.
	 *
	 * A pending resize is applied first. Rotating by a multiple of 90 degrees keeps every
	 * pixel; other angles enlarge the canvas and fill the corners with `$background`.
	 *
	 * @param float $degrees Angle in degrees, counter-clockwise (GD's convention). Use a
	 *                       negative angle (e.g. -90) to rotate clockwise.
	 * @param string|null $background Corner fill colour as hex (`#rrggbb`, `#rgb`, with or without
	 *                                the `#`). Null (default) leaves the corners transparent; for
	 *                                formats without transparency (JPEG, BMP) they are saved white.
	 *
	 * @return self Return the image class instance.
	 *
	 * @throws LogicException If no image is loaded.
	 * @throws InvalidArgumentException If `$degrees` is not finite or `$background` is not a hex colour.
	 * @throws RuntimeException If memory runs out or a GD operation fails.
	 */
	public function rotate(float $degrees, ?string $background = null): self
	{
		$this->requireImage();

		if (!is_finite($degrees)) {
			throw new InvalidArgumentException('Rotation angle must be a finite number.');
		}

		$rgb = ($background === null) ? null : Util::parseColor($background);

		$angle = fmod($degrees, 360.0);

		if ($angle < 0) {
			$angle += 360.0;
		}

		if ($angle == 0.0) {
			return $this;
		}

		$this->commitResize();

		$radians = deg2rad($angle);
		$estWidth = (int) ceil(abs($this->width * cos($radians)) + abs($this->height * sin($radians)));
		$estHeight = (int) ceil(abs($this->width * sin($radians)) + abs($this->height * cos($radians)));

		if (!Util::canAllocate(max(1, $estWidth), max(1, $estHeight))) {
			throw new RuntimeException('Not enough memory to rotate the image.');
		}

		$color = ($rgb === null)
			? imagecolorallocatealpha($this->imageData, 0, 0, 0, 127)
			: imagecolorallocatealpha($this->imageData, $rgb[0], $rgb[1], $rgb[2], 0);

		$rotated = @imagerotate($this->imageData, $angle, $color);

		if (!Util::isImage($rotated)) {
			throw new RuntimeException('Failed to rotate the image.');
		}

		imagealphablending($rotated, false);
		imagesavealpha($rotated, true);

		$this->replaceImage($rotated, imagesx($rotated), imagesy($rotated));
		$this->boxWidth = 0;
		$this->boxHeight = 0;

		if ($rgb === null) {
			$this->maybeTransparent = true;
		}

		return $this;
	}

	/**
	 * Flip (mirror) the image in place, immediately.
	 *
	 * @param int $mode NanoImage::FLIP_HORIZONTAL (default), NanoImage::FLIP_VERTICAL or NanoImage::FLIP_BOTH.
	 *
	 * @return self Return the image class instance.
	 *
	 * @throws LogicException If no image is loaded.
	 * @throws InvalidArgumentException If `$mode` is not one of the flip constants.
	 * @throws RuntimeException If GD fails to flip the image.
	 */
	public function flip(int $mode = self::FLIP_HORIZONTAL): self
	{
		$this->requireImage();

		if ($mode !== self::FLIP_HORIZONTAL && $mode !== self::FLIP_VERTICAL && $mode !== self::FLIP_BOTH) {
			throw new InvalidArgumentException(
				'Flip mode must be NanoImage::FLIP_HORIZONTAL, FLIP_VERTICAL or FLIP_BOTH.'
			);
		}

		if (!imageflip($this->imageData, $mode)) {
			throw new RuntimeException('Failed to flip the image.');
		}

		return $this;
	}

	/**
	 * Save the image to a new file without any metadata.
	 *
	 * GD never copies EXIF or other metadata, so every image NanoImage saves is already
	 * stripped. This method is a clearly named shortcut for `save($filename, NanoImage::DEFAULT, 100)`;
	 * pending resize and filters are applied like for any other save.
	 *
	 * @param string $filename The path where the new image will be saved.
	 *
	 * @return bool True if the image was written, false otherwise.
	 */
	public function removeExif(string $filename): bool
	{
		return $this->save($filename, self::DEFAULT, 100);
	}

	/**
	 * Copy the source file's EXIF data (plus `$data`) into the saved image.
	 *
	 * Requires the `exif` extension to read the source and an `exif_write_data()` function to
	 * write it. PHP itself does not ship `exif_write_data()`, so on a standard installation this
	 * method returns false. Only images opened from a file have EXIF data to copy.
	 *
	 * @param string $filename The path where the updated image will be saved.
	 * @param array $data Extra EXIF values; `DateTime` defaults to the current time.
	 *
	 * @return bool True if the image and its EXIF data were written, false otherwise.
	 */
	public function addExif(string $filename, array $data = []): bool
	{
		$exif = $this->getExifData(false);

		if ($exif === []) {
			return false;
		}

		unset($exif['FILE'], $exif['COMPUTED']);

		if (!isset($data['DateTime'])) {
			$data['DateTime'] = date('Y:m:d H:i:s');
		}

		return $this->setExif($filename, array_merge($exif, $data));
	}

	/**
	 * Save the image and write the given EXIF data into the saved file.
	 *
	 * PHP itself does not ship `exif_write_data()`. Unless a third-party extension or user
	 * function with that name exists, this method returns false without saving anything.
	 *
	 * @param string $filename The path where the updated image will be saved.
	 * @param array $data The EXIF metadata to write.
	 *
	 * @return bool True if the image and its EXIF data were written, false otherwise.
	 */
	public function setExif(string $filename, array $data = []): bool
	{
		if (!function_exists('exif_write_data')) {
			return false;
		}

		if (!$this->save($filename, self::DEFAULT, 100)) {
			return false;
		}

		return (bool) @exif_write_data($data, $this->savedPath);
	}

	/**
	 * Send the image to the browser with a matching `Content-Type` header.
	 *
	 * The image is fully encoded before anything is sent, so on failure no header or
	 * partial output is produced. The format is that of the source image.
	 *
	 * @param int $quality Output quality, 0-100 (default 100). Ignored for GIF and BMP;
	 *                     for PNG it maps to the compression level (lossless either way).
	 *
	 * @return bool True on success, false on failure.
	 */
	public function display(int $quality = 100): bool
	{
		$data = $this->toString($quality);

		if ($data === '') {
			return false;
		}

		if (!headers_sent()) {
			header('Content-Type: ' . $this->imageMime);
		}

		echo $data;

		return true;
	}

	/**
	 * Encode the image and write the bytes to the current output, without any header.
	 *
	 * Use toString() to capture the bytes instead. The format is that of the source image.
	 *
	 * @param int $quality Output quality, 0-100 (default 100). See display().
	 *
	 * @return bool True if the image was written to the output, false otherwise.
	 */
	public function get(int $quality = 100): bool
	{
		return $this->build(null, $quality);
	}

	/**
	 * Encode the image and return its bytes as a string.
	 *
	 * The format is that of the source image. The instance stays usable afterwards.
	 *
	 * @param int $quality The image encoding quality, from 0 to 100 (default 100).
	 *
	 * @return string The encoded image, or an empty string if encoding fails.
	 */
	public function toString(int $quality = 100): string
	{
		$bufferLevel = ob_get_level();

		if (!ob_start()) {
			return '';
		}

		$output = '';

		try {
			$success = $this->build(null, $quality);
			$captured = ob_get_contents();

			if ($success && is_string($captured)) {
				$output = $captured;
			}
		} finally {
			Util::clearOutput($bufferLevel);
		}

		return $output;
	}

	/**
	 * Saves the image to a file.
	 *
	 * The output format is determined by the file extension (`jpg`, `jpeg`, `png`,
	 * `gif`, `webp`, `bmp`, or `avif`). If the filename has no extension, the source
	 * format is retained and the appropriate extension is appended.
	 *
	 * The image is written to a temporary file before being moved to the destination
	 * to prevent partial output and preserve existing files if saving fails. Missing
	 * directories are created automatically. The instance remains usable after saving.
	 *
	 * @param string $filename The destination file path. An empty string uses the
	 *                         default location (`images/nano-image.jpeg` next to this file).
	 * @param int $mode The filename handling mode:
	 *                  - `NanoImage::DEFAULT` uses the filename as given and overwrites
	 *                    an existing file.
	 *                  - `NanoImage::THUMBNAIL` appends `-WIDTHxHEIGHT`, using the
	 *                    dimensions passed to `resize()` or the output dimensions.
	 *                  - `NanoImage::TIMESTAMP` appends the date and time.
	 * @param int $quality The output quality from 0 to 100 (default: 100). Controls
	 *                     lossy quality for JPEG, WebP, and AVIF, and compression
	 *                     level for PNG. Ignored for GIF and BMP.
	 *
	 * @return bool Returns true if the image is saved successfully, or false if saving
	 *              fails due to an unloaded image, unsupported format, missing GD
	 *              encoder, unwritable destination, or failed image processing.
	 *              Use `getSavedPath()` to retrieve the final file path.
	 */
	public function save(string $filename, int $mode = self::DEFAULT, int $quality = 100): bool
	{
		if (!$this->fileinfo($filename)) {
			return false;
		}

		return $this->commit($mode, $quality);
	}

	/**
	 * Save the image to a file in a chosen format.
	 *
	 * Works like save(), but the format is taken from `$extension` instead of from `$filename`.
	 * Any extension in `$filename` is replaced, e.g. `saveAs('out/photo.png', 0, 90, 'webp')`
	 * writes `out/photo.webp`.
	 *
	 * @param string $filename The path where the image should be saved.
	 * @param int $mode NanoImage::DEFAULT, NanoImage::THUMBNAIL or NanoImage::TIMESTAMP (see save()).
	 * @param int $quality Quality 0-100 (default 90).
	 * @param string|null $extension Format/extension to save as, e.g. 'jpg' or NanoImage::PNG
	 *                               (default NanoImage::JPEG). Pass null to use the extension of `$filename`.
	 *
	 * @return bool True on success, false on failure (see save()).
	 */
	public function saveAs(
		string $filename,
		int $mode = self::DEFAULT,
		int $quality = 90,
		?string $extension = self::JPEG
	): bool
	{
		if (!$this->fileinfo($filename, $extension)) {
			return false;
		}

		return $this->commit($mode, $quality);
	}

	/**
	 * Save the image over an existing file (or create it).
	 *
	 * Equivalent to `save($filename, NanoImage::DEFAULT, $quality)` with a default quality of 90.
	 *
	 * @param string $filename The path of the file to replace.
	 * @param int $quality Quality 0-100 (default 90).
	 *
	 * @return bool True if the file was written, false otherwise.
	 */
	public function replace(string $filename, int $quality = 90): bool
	{
		return $this->save($filename, self::DEFAULT, $quality);
	}

	/**
	 * Delete the file this image was opened from.
	 *
	 * Only applies to images loaded with open() (or the constructor with a file path); images
	 * loaded from a string have no file and nothing is deleted. The image stays in memory.
	 *
	 * @return self Return the image class instance.
	 */
	public function remove(): self
	{
		if ($this->imagePath !== '' && is_file($this->imagePath)) {
			@unlink($this->imagePath);
		}

		return $this;
	}

	/**
	 * Free the memory held by the image and reset the instance.
	 *
	 * Output methods do not free the image, so you can save several versions from one
	 * instance. Call this when you are done with a large image.
	 *
	 * @return void
	 */
	public function free(): void
	{
		Util::destroy($this->imageData);
		$this->reset();
	}

	/**
	 * Parse a freshly decoded image and reset all state for it.
	 *
	 * @param GdImage|resource $image The decoded image.
	 * @param int $width Image width.
	 * @param int $height Image height.
	 * @param int $type `IMAGETYPE_*` value.
	 * @param string $mime MIME type.
	 * @param string $path Source file path, or an empty string for data loaded from a string.
	 *
	 * @return self
	 *
	 * @throws UnsupportedImageException If the image could not be prepared for editing.
	 */
	private function parse(
		mixed $image, 
		int $width, 
		int $height, 
		int $type, 
		string $mime, 
		string $path = ''
	): self
	{
		$image = Util::normalize($image);

		if (!Util::isImage($image)) {
			throw new UnsupportedImageException(
				'Failed to prepare the image for editing (out of memory?).'
			);
		}

		Util::destroy($this->imageData);
		$this->reset();

		$this->imageData = $image;
		$this->imagePath = $path;
		$this->width = $width;
		$this->height = $height;
		$this->newWidth = $width;
		$this->newHeight = $height;
		$this->imageIntType = $type;
		$this->imageMime = $mime;
		$this->maybeTransparent = Util::isAlphaSupported($type);

		return $this;
	}

	/**
	 * Reset every property to its initial value (does not free the image).
	 *
	 * @return void
	 */
	private function reset(): void
	{
		$this->imagePath = '';
		$this->imageData = false;
		$this->imageMime = '';
		$this->imageIntType = 0;
		$this->extension = '';
		$this->dirname = '';
		$this->filename = '';
		$this->finalPath = '';
		$this->savedPath = '';
		$this->height = 0;
		$this->width = 0;
		$this->filters = [];
		$this->newWidth = 0;
		$this->newHeight = 0;
		$this->boxHeight = 0;
		$this->boxWidth = 0;
		$this->useRatio = false;
		$this->isResize = false;
		$this->maybeTransparent = false;
	}

	/**
	 * Make sure an image is loaded before an operation that needs one.
	 *
	 * @return void
	 *
	 * @throws LogicException If no image is loaded.
	 */
	private function requireImage(): void
	{
		if (!Util::isImage($this->imageData)) {
			throw new LogicException('No image loaded. Call open() or load() first.');
		}
	}

	/**
	 * Replace the held image with a new one and update the dimensions.
	 *
	 * @param GdImage|resource $image The new image.
	 * @param int $width Its width.
	 * @param int $height Its height.
	 *
	 * @return void
	 */
	private function replaceImage(mixed $image, int $width, int $height): void
	{
		Util::destroy($this->imageData);

		$this->imageData = $image;
		$this->width = $width;
		$this->height = $height;
		$this->newWidth = $width;
		$this->newHeight = $height;
		$this->useRatio = false;
	}

	/**
	 * Resample the held image to a new size, immediately.
	 *
	 * @param int $width New width.
	 * @param int $height New height.
	 *
	 * @return void
	 *
	 * @throws RuntimeException If memory runs out or GD fails.
	 */
	private function resampleTo(int $width, int $height): void
	{
		if ($width === $this->width && $height === $this->height) {
			return;
		}

		$canvas = Util::newCanvas($width, $height);

		if (!imagecopyresampled(
			$canvas, 
			$this->imageData, 
			0, 0, 0, 0, 
			$width, 
			$height, 
			$this->width, 
			$this->height
		)) {
			Util::destroy($canvas);
			throw new RuntimeException('Failed to resize the image.');
		}

		$this->replaceImage($canvas, $width, $height);
	}

	/**
	 * Apply a pending resize()/setWidth()/setHeight() to the held image.
	 *
	 * Called before operations that depend on the final size (crop, scale, rotate, blur).
	 *
	 * @return void
	 *
	 * @throws RuntimeException If memory runs out or GD fails.
	 */
	private function commitResize(): void
	{
		[$width, $height] = $this->calculateSize();

		if ($width <= 0 || $height <= 0) {
			return;
		}

		$this->resampleTo($width, $height);
	}

	/**
	 * Calculate size from the loaded size, the requested size and the aspect-ratio setting.
	 *
	 * The result is recomputed from the request each time and never fed back into it, so
	 * repeated calls always return the same size.
	 *
	 * @return int[] Two values: width and height in pixels (at least 1 each).
	 */
	private function calculateSize(): array
	{
		$width = $this->newWidth;
		$height = $this->newHeight;

		if ($this->useRatio && $this->width > 0 && $this->height > 0 && $width > 0 && $height > 0) {
			$aspectRatio = $this->width / $this->height;

			if ($width / $height > $aspectRatio) {
				$fitWidth = $height * $aspectRatio;
				$fitHeight = $height;
			} else {
				$fitWidth = $width;
				$fitHeight = $width / $aspectRatio;
			}

			$width = max(1, (int) round($fitWidth));
			$height = max(1, (int) round($fitHeight));
		}

		return [(int) $width, (int) $height];
	}

	/**
	 * Default location to save image.
	 *
	 * @return string
	 */
	private static function defaultLocation(): string
	{
		return __DIR__
			. DIRECTORY_SEPARATOR
			. 'images'
			. DIRECTORY_SEPARATOR
			. 'nano-image.'
			. self::JPEG;
	}

	/**
	 * Build the output image and encode it.
	 *
	 * The held image is never modified or destroyed, so this can be called repeatedly.
	 *
	 * @param string|null $path Destination file, or null to send the bytes to the output.
	 * @param int $quality Encoding quality, 0-100.
	 * @param int $type `IMAGETYPE_*` format to encode as; 0 uses the source format.
	 *
	 * @return bool True if the image was encoded, false otherwise.
	 */
	private function build(?string $path = null, int $quality = 100, int $type = 0): bool
	{
		if (!Util::isImage($this->imageData)) {
			return false;
		}

		[$width, $height] = $this->calculateSize();

		if ($width <= 0 || $height <= 0 || $this->width <= 0 || $this->height <= 0) {
			return false;
		}

		$type = ($type > 0) ? $type : $this->imageIntType;
		$keepAlpha = Util::isAlphaSupported($type);

		if (
			empty($this->filters)
			&& $width === $this->width
			&& $height === $this->height
			&& ($keepAlpha || !$this->maybeTransparent)
		) {
			return Util::encode(
				$this->imageData, 
				$path, 
				$quality, 
				$type, 
				$this->maybeTransparent
			);
		}

		if (!Util::canAllocate($width, $height)) {
			return false;
		}

		$image = $keepAlpha 
			? Util::createCanvas($width, $height) 
			: Util::createFlatCanvas($width, $height);

		if (!Util::isImage($image)) {
			return false;
		}

		try {
			if (!imagecopyresampled(
				$image,
				$this->imageData,
				0,
				0,
				0,
				0,
				$width,
				$height,
				$this->width,
				$this->height
			)) {
				return false;
			}

			if (!$this->applyFilters($image)) {
				return false;
			}

			return Util::encode($image, $path, $quality, $type, $this->maybeTransparent);
		} finally {
			Util::destroy($image);
		}
	}

	/**
	 * Apply all queued filters to a GD image.
	 *
	 * @param GdImage|resource $image The image to modify.
	 *
	 * @return bool False if GD rejected a filter, true otherwise.
	 */
	private function applyFilters(mixed $image): bool
	{
		foreach ($this->filters as $filter) {
			try {
				$result = @imagefilter($image, ...$filter);
			} catch (Throwable) {
				return false;
			}

			if (!$result) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Write the output image to disk using the settings from fileinfo().
	 *
	 * The image is written to a temporary file in the target directory and then renamed, so
	 * an existing file is only replaced once the new one is complete.
	 *
	 * @param int $mode NanoImage::DEFAULT, NanoImage::THUMBNAIL or NanoImage::TIMESTAMP.
	 * @param int $quality Quality, 0 to 100.
	 *
	 * @return bool True if the image was saved, false otherwise.
	 */
	private function commit(int $mode = self::DEFAULT, $quality = 90)
	{
		if (!Util::isImage($this->imageData)) {
			return false;
		}

		$type = Util::typeFromExtension($this->extension);

		if ($type === 0 || !Util::encoderAvailable($type)) {
			return false;
		}

		if (
			!is_dir($this->dirname) 
			&& !@mkdir($this->dirname, 0755, true) 
			&& !is_dir($this->dirname)
		) {
			return false;
		}

		$target = $this->finalPath;

		if ($mode === self::THUMBNAIL) {
			[$outWidth, $outHeight] = $this->calculateSize();
			$nameWidth = ($this->boxWidth > 0) ? $this->boxWidth : $outWidth;
			$nameHeight = ($this->boxHeight > 0) ? $this->boxHeight : $outHeight;

			$target = $this->dirname
				. DIRECTORY_SEPARATOR
				. $this->filename
				. "-{$nameWidth}x{$nameHeight}.{$this->extension}";
		} elseif ($mode === self::TIMESTAMP) {
			$stamp = date('d-m-y-H-i-s');
			$counter = 0;

			do {
				$target = $this->dirname
					. DIRECTORY_SEPARATOR
					. "{$this->filename}-{$stamp}"
					. ($counter > 0 ? "-{$counter}" : '')
					. ".{$this->extension}";
				$counter++;
			} while (file_exists($target));
		}

		$temp = $this->dirname
			. DIRECTORY_SEPARATOR
			. '.nanoimage-'
			. str_replace('.', '', uniqid('', true))
			. '.tmp';

		if (!$this->build($temp, $quality, $type)) {
			@unlink($temp);
			return false;
		}

		if (!@rename($temp, $target)) {
			@unlink($temp);
			return false;
		}

		$this->finalPath = $target;
		$this->savedPath = $target;

		return true;
	}

	/**
	 * Work out the save target (directory, name, extension) from a path.
	 *
	 * @param string $filename Full path of the image to save; empty uses the default location.
	 * @param string|null $extension Extension to use instead of the one in the path.
	 *
	 * @return bool False if the extension is not a supported output format.
	 */
	private function fileinfo(string $filename, ?string $extension = null): bool
	{
		if (!Util::isImage($this->imageData) || strpos($filename, "\0") !== false) {
			return false;
		}

		$info = pathinfo($filename !== '' ? $filename : self::defaultLocation());

		if ($extension === null || $extension === '') {
			$extension = isset($info['extension']) ? $info['extension'] : '';
		}

		if ($extension === '' && !isset($info['extension'])) {
			$extension = Util::extensionForType($this->imageIntType);
		}

		$extension = strtolower(ltrim((string) $extension, '.'));

		if (Util::typeFromExtension($extension) === 0) {
			return false;
		}

		$this->extension = $extension;
		$this->dirname = isset($info['dirname']) ? $info['dirname'] : '.';
		$this->filename = isset($info['filename']) ? $info['filename'] : '';
		$this->finalPath = $this->dirname
			. DIRECTORY_SEPARATOR
			. $this->filename
			. ".{$this->extension}";

		return true;
	}
}
