<?php
/**
 * Class NanoImage.
 * 
 * @author      Peter Chigozie(NG) peterujah
 * @copyright   Copyright (c), 2019 Peter(NG) peterujah
 * @license     MIT public license
 */
namespace Peterujah\Media;

use \GdImage;
use Peterujah\Media\UnsupportedImageException;

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
	 * Save image as a thumbnail.
	 *
	 * @var int THUMBNAIL 
	 */
	public const THUMBNAIL = 1;

	/**
	 * Save image with a timestamp.
	 *
	 * @var int TIMESTAMP 
	 */
	public const TIMESTAMP = 2;

	/**
	 * Save image with its original name.
	 *
	 * @var int DEFAULT 
	 */
	public const DEFAULT = 0;

	/**
	 * Image path location.
	 *
	 * @var string $imagePath 
	 */
	private string $imagePath = '';

	/**
	 * Image resource.
	 *
	 * @var GdImage|bool $imageData 
	 */
	private GdImage|bool $imageData = false;

	/**
	 * Apply filters to image.
	 * 
	 * @var array $filters
	 */
	private array $filters = [];

	/**
	 * Final image height.
	 *
	 * @var int $newHeight 
	 */
	private int $newHeight = 0;

	/**
	 * Final image width.
	 *
	 * @var int $newWidth 
	 */
	private int $newWidth = 0;

	/**
	 * Cropped image width.
	 *
	 * @var int $cropWidth 
	 */
	private int $cropWidth = 0;

	/**
	 * Cropped image height.
	 *
	 * @var int $cropHeight 
	 */
	private int $cropHeight = 0;

	/**
	 * Original image height.
	 *
	 * @var int $height 
	 */
	private int $height = 0;

	/**
	 * Original image width.
	 *
	 * @var int $width 
	 */
	private int $width = 0;

	/**
	 * Image MIME type.
	 *
	 * @var string $imageMime 
	 */
	private string $imageMime = '';

	/**
	 * Internal image type constant.
	 *
	 * @var int $imageIntType 
	 */
	private int $imageIntType = 0;

	/**
	 * Image extension type.
	 *
	 * @var string $extension 
	 */
	private string $extension = '';

	/**
	 * Image directory name.
	 *
	 * @var string $dirname 
	 */
	private string $dirname = '';

	/**
	 * Image file name.
	 *
	 * @var string $filename 
	 */
	private string $filename = '';

	/**
	 * Final image save location.
	 *
	 * @var string $finalPath 
	 */
	private string $finalPath = '';

	/**
	 * Whether to maintain the aspect ratio during resizing.
	 *
	 * @var bool $useRatio 
	 */
	private bool $useRatio = false;

	/**
	 * Indicates if the image has been resized.
	 *
	 * @var bool $isResize 
	 */
	private bool $isResize = false;

	/**
	 * Initialize the Nano Image class.
	 * 
	 * @param string|null $source Image source, filename or data.
	 */
	public function __construct(?string $source = null)
	{
		if($source === null && $source === ''){
			return;
		}

		if(is_file($source)){
			$this->open($source);
			return;
		}

		$this->load($source);
	}

	/**
	 * Load an image from a file path or URL.
	 *
	 * @param string $filename Path or URL to the image.
	 * 
	 * @return self Return the image class instance.
	 * @throws UnsupportedImageException If the image is not valid or unsupported.
	 */
	public function open(string $filename): self
	{
		$info = null;
		$size = getimagesize($filename, $info);

		if($size === false){
			throw new UnsupportedImageException("Failed to load image from: {$filename}.");
		}

		$width  = (int) ($size[0] ?? 0);
		$height = (int) ($size[1] ?? 0);
		$type   = (int) ($size[2] ?? 0);

		if ($width <= 0 || $height <= 0) {
			throw new UnsupportedImageException('Invalid or unsupported image file');
		}

		switch ($type) {
			case IMAGETYPE_JPEG:
			case IMAGETYPE_JPEG2000:
				$this->imageData = imagecreatefromjpeg($filename);
				break;
			case IMAGETYPE_PNG:
				$this->imageData = imagecreatefrompng($filename);
				break;
			case IMAGETYPE_GIF:
				$this->imageData = imagecreatefromgif($filename);
				break;
			case IMAGETYPE_WEBP:
				$this->imageData = imagecreatefromwebp($filename);
				break;
			case IMAGETYPE_AVIF:
				if (!function_exists('imagecreatefromavif')) {
					throw new UnsupportedImageException(
						'AVIF image extention not supported.'
					);
				}
				
				$this->imageData = imagecreatefromavif($filename);
				break;
			default:
				throw new UnsupportedImageException(
					"Unsupported image type: {$type}, {$size['mime']}."
				);
		}

		if (!$this->imageData instanceof GdImage) {
			throw new UnsupportedImageException(
				"Filed to create image from: {$filename}"
			);
		}

		$this->imagePath = $filename;
		$this->height = $height;
		$this->width = $width;
		$this->newWidth = $width;
		$this->newHeight = $height;
		$this->imageIntType = $type;
		$this->imageMime = $size['mime'];

		return $this;
	}

	/**
	 * Load an image from a string.
	 *
	 * @param string $data The image data as a string.
	 * 
	 * @return self Return the image class instance.
	 * @throws UnsupportedImageException If the image type is unsupported.
	 */
	public function load(string $data): self 
	{
		if($data === ''){
			throw new UnsupportedImageException('Image data cannot be an empty string.');
		}

		$this->imageData = imagecreatefromstring($data);

		if ($this->imageData === false) {
			throw new UnsupportedImageException(
				'Failed to create image from data'
			);
		}

		$info = getimagesizefromstring($data);

		if ($info === false) {
			throw new UnsupportedImageException('Error while processing image data');
		}

		[$width, $height, $imageType] = $info;

		$this->height = (int) $height;
		$this->width = (int) $width;
		$this->newWidth = (int) $width;
		$this->newHeight = (int) $height;
		$this->imageMime = $info['mime'];
		$this->imageIntType = $imageType;

		return $this;
	}

	/**
	 * Get the image's width.
	 *
	 * @return int The width of the image.
	 */
	public function getWidth(): int
	{
		return $this->width;
	}

	/**
	 * Get the image's height.
	 *
	 * @return int The height of the image.
	 */
	public function getHeight(): int
	{
		return $this->height;
	}

	/**
	 * Get the image MIME type (e.g., 'image/jpeg', 'image/png').
	 *
	 * @return string The MIME type of the image.
	 */
	public function getMimeType(): string
	{
		return $this->imageMime;
	}

	/**
	 * Get the image extension (e.g., 'jpg', 'png').
	 *
	 * @return string The file extension of the image.
	 */
	public function getExtension(): string
	{
		return $this->extension;
	}

	/**
	 * Check if the image has been resized.
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
	 * @param int $height The desired height for the image.
	 * 
	 * @return self Return the image class instance.
	 */
	public function setHeight(int $height): self
	{
		$this->newHeight = $height;
		return $this;
	}

	/**
	 * Set a new image width.
	 *
	 * @param int $width The desired width for the image.
	 * 
	 * @return self Return the image class instance.
	 */
	public function setWidth(int $width): self
	{
		$this->newWidth = $width;
		return $this;
	}

	/**
	 * Set whether to maintain aspect ratio during resizing.
	 *
	 * @param bool $ratio Whether to maintain aspect ratio (true or false).
	 * 
	 * @return self Return the image class instance.
	 */
	public function aspectRatio(bool $ratio): self
	{
		$this->useRatio = $ratio;
		return $this;
	}

	/**
	 * Resize the image with optional aspect ratio calculation.
	 *
	 * @param int $width The desired width for resizing.
	 * @param int $height The desired height for resizing.
	 * @param bool $ratio Whether to auto-calculate aspect ratio.
	 * 
	 * @return self Return the image class instance.
	 */
	public function resize(int $width, int $height, bool $ratio = false): self
	{
		if ($ratio) {
			$this->calculateAspectRatio($width, $height);
			$this->isResize = true;
			return $this;
		}

		$this->newWidth = $width;
		$this->newHeight = $height;
		$this->cropWidth = (int) $width;
		$this->cropHeight = (int) $height;
		$this->isResize = true;
		
		return $this;
	}

	/**
	 * Adds a single filter to be applied to the image.
	 *
	 * This method allows you to apply a single image filter with optional parameters.
	 * Filters should be one of the predefined `IMG_FILTER_*` constants.
	 * 
	 * @param int   $filter The image filter constant (e.g., `IMG_FILTER_GRAYSCALE`).
	 * @param mixed ...$args Optional additional parameters for certain filters.
	 * 
	 * @return self Return the image class instance.
	 *
	 * @example - Apply a single image filter.
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
	 * Adds multiple filters to be applied to the image.
	 *
	 * This method allows you to apply multiple filters at once. Each filter should be
	 * an array where the first element is the `IMG_FILTER_*` constant, followed by any
	 * additional parameters the filter requires.
	 * 
	 * @param array $filters An array of filters, where each filter is an array containing
	 *                       the filter constant and its optional parameters.
	 * 
	 * @return self Return the image class instance.
	 *
	 * @example - Applying filters.
	 * 
	 * ```php
	 * $image->filters([
	 *     [IMG_FILTER_BRIGHTNESS, 20],
	 *     [IMG_FILTER_GAUSSIAN_BLUR],
	 *     [IMG_FILTER_COLORIZE, 100, 50, 0], // Add a color overlay
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
	 * Applies a blur effect to the image.
	 * The image is first scaled down, blurred, then scaled back up, applying the effect multiple times.
	 *
	 * @param int $range     The number of times to apply the blur effect.
	 * @param int $argument  The blur strength; defaults to a high value for a noticeable effect.
	 *
	 * @return self Returns the current NanoImage instance.
	 */
	public function blur(int $range = 5, int $argument = 999): self
	{
		if(!$this->imageData instanceof GdImage){
			return $this;
		}

		$imageSmall = false;
		$imageMedium = false;

		try{
			if ($this->useRatio && !$this->isResize) {
				$this->calculateAspectRatio($this->newWidth, $this->newHeight);
			}

			$size = [
				'sm'=> ['w'=>intval($this->newWidth/4), 'h'=>intval($this->newHeight/4)],
				'md'=> ['w'=>intval($this->newWidth/2), 'h'=>intval($this->newHeight/2)]
			]; 

			$imageSmall = imagecreatetruecolor($size['sm']['w'], $size['sm']['h']);

			if($imageSmall === false){
				return $this;
			}

			/* Scale by 25% and apply Gaussian blur */
			imagecopyresampled(
				$imageSmall, 
				$this->imageData,
				0, 0, 0, 0, 
				$size['sm']['w'], 
				$size['sm']['h'],
				$this->newWidth, 
				$this->newHeight
			);

			$imageSmall = $this->applyBlur($imageSmall, $range, $argument);

			/* Scale result by 200% and blur again */
			$imageMedium = imagecreatetruecolor($size['md']['w'], $size['md']['h']);

			if($imageMedium === false){
				return $this;
			}

			imagecopyresampled(
				$imageMedium, 
				$imageSmall, 
				0, 0, 0, 0, 
				$size['md']['w'], 
				$size['md']['h'], 
				$size['sm']['w'], 
				$size['sm']['h']
			);

			$imageMedium = $this->applyBlur($imageMedium, 25, $argument);

			/* Scale result back to original size */
			imagecopyresampled(
				$this->imageData, 
				$imageMedium, 
				0, 0, 0, 0, 
				$this->newWidth, 
				$this->newHeight, 
				$size['md']['w'], 
				$size['md']['h']
			);
		} finally {
			$this->destroy($imageSmall);
			$this->destroy($imageMedium);

			$imageSmall = null;
			$imageMedium = null;
		}

		return $this;
	}

	/**
     * Applies a Gaussian blur filter to the provided image resource.
     *
     * @param GdImage $image  The image resource to apply the blur on.
     * @param int     $range  The number of blur passes.
     * @param int     $argument  The blur strength argument.
     *
     * @return GdImage The modified image resource with the blur applied.
     */
	public function applyBlur(GdImage $image, int $range = 5, int $argument = 999): GdImage 
	{
		for ($i = 0; $i < $range; $i++) {
            imagefilter($image, IMG_FILTER_GAUSSIAN_BLUR, $argument);
        }

		imagefilter($image, IMG_FILTER_SMOOTH, 99);
    	imagefilter($image, IMG_FILTER_BRIGHTNESS, 10); 

		return $image;
	}

	/**
	 * Remove EXIF data from the image and save the updated image.
	 *
	 * @param string $saveTo The path where the new image will be saved.
	 *
	 * @return bool True if the EXIF data was successfully removed, false otherwise.
	 */
	public function removeExif(string $saveTo): bool 
	{
		return $this->writeImage($this->imageData, $saveTo, 100);
	}

	/**
	 * Add EXIF metadata to the image and save it to a specified path.
	 *
	 * @param string $saveTo The path where the updated image will be saved.
	 * @param array $data Additional EXIF metadata to be added (optional).
	 *
	 * @return bool True if the EXIF data was successfully added, false otherwise.
	 */
	public function addExif(string $saveTo, array $data = []): bool 
	{
		$exif = exif_read_data($this->imagePath);

		if($exif === false){
			return false;
		}

		$data['DateTime'] ??= date('Y:m:d H:i:s');
	
		return $this->setExif(
			$saveTo, 
			array_merge($exif, $data)
		);
	}

	/**
	 * Set or replace EXIF metadata for the image and save it.
	 *
	 * @param string $saveTo The path where the updated image will be saved.
	 * @param array $data The EXIF metadata to be applied to the image (optional).
	 *
	 * @return bool True if the EXIF data was successfully written, false otherwise.
	 */
	public function setExif(string $saveTo, array $data = []): bool 
	{
		if(!function_exists('exif_write_data')){
			return false;
		}

		$thumbnail = exif_thumbnail($this->imagePath, $width, $height, $type);

		if($thumbnail === false){
			return false;
		}

		$data['Thumbnail'] = $thumbnail;

		exif_write_data($data, $saveTo);

		return $this->writeImage($this->imageData, $saveTo, 100);
	}

	/**
	 * Output the image directly to the browser.
	 *
	 * @param int $quality The quality of the output (1-100, defaults to 100).
	 * @return bool True on success, false on failure.
	 */
	public function display(int $quality = 100): bool 
	{
		if (!headers_sent()) {
			header('Content-Type: ' . $this->imageMime);
		}

		return $this->build(null, $quality);
	}

	/**
	 * Output the image as a string without setting the content-type header.
	 *
	 * @param int $quality The quality of the output image (1-100, defaults to 100).
	 *
	 * @return bool True if the image was successfully output, false otherwise.
	 */
	public function get(int $quality = 100): bool 
	{
		return $this->build(null, $quality);
	}

	/**
	 * Encode the image and return its contents as a string.
	 *
	 * @param int $quality The image encoding quality, from 0 to 100.
	 *
	 * @return string Returns the encoded image contents, or an empty string
	 *                if encoding fails.
	 */
	public function toString(int $quality = 100): string
	{
		$bufferLevel = ob_get_level();

		if (!ob_start()) {
			return '';
		}

		try {
			$success = $this->build(null, $quality);
			$output = ob_get_contents();

			if ($success && is_string($output) && $output !== '') {
				return $output;
			}
		} finally {
			self::clearOutput($bufferLevel);
		}

		return '';
	}

	/**
	 * Clear output buffers created above the specified buffer level.
	 *
	 * @param int $bufferLevel The output buffer level to preserve.
	 *
	 * @return void
	 */
	private static function clearOutput(int $bufferLevel = 0): void
	{
		while (ob_get_level() > $bufferLevel) {
			if (!ob_end_clean()) {
				break;
			}
		}
	}

	/**
	 * Save the image to the specified path with optional formatting and quality.
	 *
	 * @param string $saveTo The path where the image should be saved.
	 * @param int $type Type of save (NanoImage::THUMBNAIL, NanoImage::TIMESTAMP, or NanoImage::DEFAULT).
	 * @param int $quality The quality of the saved image (1-100, defaults to 100).
	 * 
	 * 
	 * @return bool True on success, false on failure.
	 * > **Note:** 
	 * > The `$type` is used to specify how image should be saved, `NanoImage::DEFAULT` 
	 * > will delete existing image from directory
	 * > While `NanoImage::THUMBNAIL` will rename image using height and width
	 */
	public function save(string $saveTo, int $type = self::DEFAULT, int $quality = 100): bool 
	{
		$this->fileinfo($saveTo);
		return $this->execute($type, $quality);
	}

	/**
	 * Save the image to the specified path with optional formatting, quality and extension.
	 *
	 * @param string $saveTo The path where the image should be saved.
	 * @param int $type Type of save (NanoImage::THUMBNAIL, NanoImage::TIMESTAMP, or NanoImage::DEFAULT).
	 * @param int $quality The quality of the saved image (1-100, defaults to 100).
	 * @param string|null $extension Optional extension to save the image (e.g., 'jpg', 'png').
	 * 
	 * @return bool True on success, false on failure.
	 * 
	 * > **Note:** 
	 * > The `$type` is used to specify how image should be saved, `NanoImage::DEFAULT` 
	 * >will delete existing image from directory
	 * > While `NanoImage::THUMBNAIL` will rename image using height and width
	 */
	public function saveAs(
		string $saveTo, 
		int $type = self::DEFAULT, 
		int $quality = 90, 
		string|null $extension = self::JPEG
	): bool 
	{
		$this->fileinfo($saveTo, $extension);
		return $this->execute($type, $quality);
	}

	/**
	 * Replace an existing image by saving a new one to the specified directory.
	 *
	 * @param string $saveTo The full path to the directory where the image will be saved.
	 * @param int $quality The quality of the saved image (1-100, defaults to 90).
	 *
	 * @return bool True if the image was successfully replaced, false otherwise.
	 */
	public function replace(string $saveTo, int $quality = 90): bool
	{
		$this->fileinfo($saveTo);
		return $this->execute(self::DEFAULT, $quality);
	}
	
	/**
	 * Remove original image.
	 *
	 * @return self Return the image class instance.
	 */
	public function remove(): self 
	{
		is_file($this->imagePath) 
			&& unlink($this->imagePath);

		return $this;
	}

	/**
	 * Free the memory associated with the image.
	 *
	 * @return void
	 */
	public function free(): void 
	{
		$this->destroy($this->imageData);
		$this->imagePath = '';
		$this->imageData = false;
		$this->imageMime = '';
		$this->imageIntType = 0;
		$this->extension = '';
		$this->dirname = '';
		$this->filename = '';
		$this->finalPath = '';
		$this->height = 0;
		$this->width = 0;
		$this->filters = [];
		$this->newWidth = 0;
		$this->newHeight = 0;
		$this->cropHeight = 0;
		$this->cropWidth = 0;
		$this->useRatio = false;
		$this->isResize = false;
	}

	/**
	 * Image destroy.
	 * 
	 * @param GdImage|bool $image
	 * 
	 * @return void
	 */
	private function destroy(GdImage|bool $image): void 
	{
		if ($image instanceof GdImage && PHP_VERSION_ID < 80500) {
			@imagedestroy($image);
		}
	}

	/**
	 * Calculate the image's aspect ratio and resize it accordingly.
	 *
	 * @param int $width The target width.
	 * @param int $height The target height.
	 * @return void
	 */
	private function calculateAspectRatio(int $width, int $height): void
	{
		$aspectRatio = $this->width / $this->height;

		if ($width / $height > $aspectRatio) {
			$this->newWidth = (int) ($height * $aspectRatio);
			$this->newHeight = $height;
		} else {
			$this->newWidth = $width;
			$this->newHeight = (int) ($width / $aspectRatio);
		}

		$this->cropWidth = (int) $width;
		$this->cropHeight = (int) $height;
		$this->useRatio = true;
	}

	/**
	 * Default location to save image.
	 * 
	 * @return string 
	 */
	private function defaultLocation(): string
	{
		return __DIR__ 
			. DIRECTORY_SEPARATOR 
			. 'images' 
			. DIRECTORY_SEPARATOR 
			. 'nano-image' 
			. self::JPEG;
	}

	/**
	 * Build image.
	 *
	 * @param string $path Optional path, set to null to ignore saving image or supply path to save a copy.
	 * @param int $quality Set image quality.
	 *
	 * @return bool Return true if images was successfully created, false otherwise.
	 */
	private function build(?string $path = null, int $quality = 100): bool
	{
		if ($this->useRatio && !$this->isResize) {
			$this->calculateAspectRatio($this->newWidth, $this->newHeight);
		}

		$image = imagecreatetruecolor($this->newWidth, $this->newHeight);

		if ($image === false) {
			return false;
		}

		try{
			$white = imagecolorallocate($image, 255, 255, 255);

			if($white === false){
				return false;
			}

			imagefilledrectangle($image, 0, 0, $this->newWidth, $this->newHeight, $white);
			
			if (imagecopyresampled(
				$image, 
				$this->imageData, 
				0, 0, 0, 0, 
				$this->newWidth, 
				$this->newHeight, 
				$this->width, 
				$this->height
			)) {
				return $this->writeImage(
					$this->applyFilters($image),
					$path, 
					$quality
				);
			}
		} finally {
			$this->destroy($image);
			$image = null;
		}

		return false;
	}

	/**
	 * Applies all queued image filters to the given GD image resource.
	 *
	 * This method iterates through the stored filters and applies them one by one.
	 * Each filter is either an array (with parameters) or a single constant.
	 *
	 * @param GdImage $image The GD image resource to apply filters to.
	 * @param int $applied (reference) The count of successfully applied filters.
	 * 
	 * @return GdImage The modified GD image with applied filters.
	 */
	private function applyFilters(GdImage $image, int &$applied = 0): GdImage 
	{
		foreach ($this->filters as $filter) {
			$ok = is_array($filter) 
				? imagefilter($image, ...$filter)
				: imagefilter($image, $filter);

			if($ok){
				$applied++;
			}
		}

		return $image;
	}
	
	/**
	 * Save image as specified type, quality and size 
	 *
	 * @param GdImage $image image resource.
	 * @param string|null $file image new file.
	 * @param int $quality image quality.
	 *
	 * @return bool Return true if image was successfully created, otherwise false.
	 */
	private function writeImage(GdImage $image, ?string $file = null, int $quality = 100): bool
	{
		$result = false;
		$quality = max(0, min(100, $quality));

		try{
			switch ($this->imageIntType) {
				case IMAGETYPE_BMP:
					$result = function_exists('imagebmp') 
						? imagebmp($image, $file) 
						: self::image_bmp($image, $file);
					break;
				case IMAGETYPE_PNG:
					$result = imagepng($image, $file, min(9, (int)($quality / 10)));
					break;
				case IMAGETYPE_GIF:
					$result = imagegif($image, $file);
					break;
				case IMAGETYPE_WEBP:
					$result = imagewebp($image, $file, $quality);
					break;
				default:
					$result = imagejpeg($image, $file, $quality);
			}
		} finally {
			$this->destroy($this->imageData);
			$this->imageData = false;
		}
		
		return $result;
	}

	/**
	 * Execute image edit and save to directory.
	 *
	 * @param int $nameFormat Specify how image should be saved NanoImage::DEFAULT will delete existing image from directory
	 * While passing thumbnail will rename image using height and width and timestamp will use timestamp to save the image
	 * @param int $quality The require quality to set image
	 *
	 * @return bool Return true if images was successfully otherwise false.
	 */
	private function execute(int $nameFormat = self::DEFAULT, int $quality = 90): bool 
	{
		if(!is_dir($this->dirname) && !mkdir($this->dirname, 0755, true)){
			return false;
		}

		if(is_file($this->finalPath)){
			$deleteFile = true;

		    if($nameFormat !== self::DEFAULT){
				if($nameFormat === self::THUMBNAIL){
					$this->finalPath = $this->dirname 
						. DIRECTORY_SEPARATOR 
						. $this->filename 
						. "-{$this->cropWidth}x{$this->cropHeight}.{$this->extension}";
				}else if($nameFormat === self::TIMESTAMP){
					$deleteFile = false;
					$this->finalPath = $this->dirname 
						. DIRECTORY_SEPARATOR 
						. "{$this->filename}-" 
						. date("d-m-y h:m:s") 
						. ".{$this->extension}";
				}
		    }
			
			if($deleteFile && is_file($this->finalPath)){
				@unlink($this->finalPath);
		    }
		}

		return $this->build($this->finalPath, $quality);
	}

	/**
	 * Set image fileinfo.
	 *
	 * @param string $saveTo Full directory to save image
	 * @param string $extension Save image with extension
	 * 
	 * @return void
	 */
	private function fileinfo(string $saveTo, ?string $extension = null): void 
	{
		$info = pathinfo(!empty($saveTo) 
			? $saveTo 
			: $this->defaultLocation() 
		);

		$this->extension = ($extension === null) 
			? strtolower($info['extension']) 
			: strtolower($extension);

		$this->dirname = $info['dirname'] ?? '';
		$this->filename = $info['filename'] ?? '';
		$this->finalPath = $this->dirname 
			. DIRECTORY_SEPARATOR 
			. $this->filename
			. ".{$this->extension}";
	}

	/**
	 * Bitmap image function write image to file or output to browser.
	 *
	 * @param GdImage $image image resource.
	 * @param string|null $file optional image name and path to save.
	 *
	 * @return string|bool Return true if file path is specified and images is written, otherwise return false.
	 */
	public static function image_bmp(
		GdImage $image, 
		?string $file = null, 
		bool $compressed = true,
		int $quality = -1
	): bool
	{
		if ($quality !== -1 && ($quality < 1 || $quality > 100)) {
			return false;
		}

		$width = imagesx($image);
		$height = imagesy($image);

		// Create a new image with scaled dimensions
		if($quality !== -1){
			// Map quality (1 to 100) to a scaling factor (0.01 to 1.0)
			$scaleFactor = max(0.01, min($quality / 100, 1.0));

			// Scale image based on quality (100 is original size, 50 would halve both width and height)
			$width = (int) ($width * $scaleFactor);
			$height = (int) ($height * $scaleFactor);

			$image = imagescale($image, $width, $height);

			if ($image === false) {
				return false;
			}
		}

		$imageHeaderSize = 54;
		$imageDataSize = ($width * 3 + ($width % 4)) * $height;
		$fileSize = $imageHeaderSize + $imageDataSize;

		$bmpData = '';

		// Bitmap File Header
		$bmpData .= 'BM'; // Bitmap identifier
		$bmpData .= pack('V', $fileSize); // File size
		$bmpData .= pack('v', 0); // Reserved (unused)
		$bmpData .= pack('v', 0); // Reserved (unused)
		$bmpData .= pack('V', $imageHeaderSize); // Offset to image data

		// Bitmap Info Header
		$bmpData .= pack('V', 40); // Header size
		$bmpData .= pack('l', $width); // Image width (signed integer)
		$bmpData .= pack('l', -$height); // Image height (negative for top-down image)
		$bmpData .= pack('v', 1); // Number of color planes (must be 1)
		$bmpData .= pack('v', 24); // Bits per pixel (RGB)
		$bmpData .= pack('V', $compressed ? 1 : 0); // Compression method (0 = uncompressed)
		$bmpData .= pack('V', $imageDataSize); // Image data size (including padding)
		$bmpData .= pack('l', 2835); // Horizontal resolution (pixels per meter, 72 DPI)
		$bmpData .= pack('l', 2835); // Vertical resolution (pixels per meter, 72 DPI)
		$bmpData .= pack('V', 0); // Number of colors in the palette (0 = no palette)
		$bmpData .= pack('V', 0); // Number of important colors (0 = all colors are important)

		// Image Data (bottom-up, BGR color order)
		for ($y = $height - 1; $y >= 0; $y--) {
			for ($x = 0; $x < $width; $x++) {
				$rgb = imagecolorat($image, $x, $y);
				$r = ($rgb >> 16) & 0xFF;
				$g = ($rgb >> 8) & 0xFF;
				$b = $rgb & 0xFF;
				$bmpData .= pack('C', $b) . pack('C', $g) . pack('C', $r);
			}

			// Padding to ensure row length is multiple of 4 bytes
			$bmpData .= str_repeat("\x00", $width % 4);
		}

		// Free memory of scaled image
		// if($quality !== -1){
		// 	$this->destroy($image);
		// 	$image = null;
		// }

		if ($file === null) {
			echo $bmpData;
			return true;
		}

		return file_put_contents($file, $bmpData) !== false;
	}
}
