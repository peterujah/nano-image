<?php
/**
 * Class NanoImage.
 * 
 * @author      Peter Chigozie(NG) peterujah
 * @copyright   Copyright (c), 2019 Peter(NG) peterujah
 * @license     MIT public license
 */
namespace Peterujah\NanoBlock;

use Peterujah\NanoBlock\UnsupportedImageException;
use \GdImage;

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
	 * Final image height.
	 *
	 * @var int $new_height 
	 */
	private int $new_height = 0;

	/**
	 * Final image width.
	 *
	 * @var int $new_width 
	 */
	private int $new_width = 0;

	/**
	 * Cropped image width.
	 *
	 * @var int $crop_width 
	 */
	private int $crop_width = 0;

	/**
	 * Cropped image height.
	 *
	 * @var int $crop_height 
	 */
	private int $crop_height = 0;

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
	 * Initialize the NanoImage class.
	 */
	public function __construct(){}

	/**
	 * Load an image from a file path or URL.
	 *
	 * @param string $imageLocation Path or URL to the image.
	 * 
	 * @return self The current class instance.
	 * @throws InvalidArgumentException If the image is not valid or unsupported.
	 */
	public function open(string $imageLocation): self
	{
		$this->imagePath = $imageLocation;
		$info = getimagesize($this->imagePath);
		[$width, $height, $imageType, $mime] = $info;
		if (!$width || !$height || !$imageType || !$mime) {
			throw new UnsupportedImageException('Invalid or unsupported image file');
		}

		$this->height = (int) $height;
		$this->width = (int) $width;
		$this->new_width = (int) $width;
		$this->new_height = (int) $height;
		$this->imageIntType = $imageType;
		$this->imageMime = $info['mime'];

		switch ($imageType) {
			case IMAGETYPE_JPEG:
				$this->imageData = imagecreatefromjpeg($this->imagePath);
				break;
			case IMAGETYPE_PNG:
				$this->imageData = imagecreatefrompng($this->imagePath);
				break;
			case IMAGETYPE_GIF:
				$this->imageData = imagecreatefromgif($this->imagePath);
				break;
			case IMAGETYPE_WEBP:
				$this->imageData = imagecreatefromwebp($this->imagePath);
				break;
			default:
				throw new UnsupportedImageException('Unsupported image format');
		}

		if (!$this->imageData) {
			throw new UnsupportedImageException('Image is invalid or could not be processed');
		}

		return $this;
	}

	/**
	 * Load an image from a string.
	 *
	 * @param string $imageString The image data as a string.
	 * 
	 * @return self The current class instance.
	 * @throws UnsupportedImageException If the image type is unsupported.
	 */
	public function load(string $imageString): self 
	{
		$this->imageData = imagecreatefromstring($imageString);

		if (!$this->imageData) {
			throw new UnsupportedImageException('Image is invalid or could not be processed');
		}

		$info = getimagesizefromstring($imageString);
		[$width, $height, $imageType] = $info;

		$this->height = (int) $height;
		$this->width = (int) $width;
		$this->new_width = (int) $width;
		$this->new_height = (int) $height;
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
	 * @return self The current class instance.
	 */
	public function setHeight(int $height): self
	{
		$this->new_height = $height;
		return $this;
	}

	/**
	 * Set a new image width.
	 *
	 * @param int $width The desired width for the image.
	 * 
	 * @return self The current class instance.
	 */
	public function setWidth(int $width): self
	{
		$this->new_width = $width;
		return $this;
	}

	/**
	 * Set whether to maintain aspect ratio during resizing.
	 *
	 * @param bool $ratio Whether to maintain aspect ratio (true or false).
	 * 
	 * @return self The current class instance.
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
	 * @return self The current class instance.
	 */
	public function resize(int $width, int $height, bool $ratio = false): self
	{
		if ($ratio) {
			$this->calculateAspectRatio($width, $height);
		} else {
			$this->new_width = $width;
			$this->new_height = $height;
			$this->crop_width = (int) $width;
			$this->crop_height = (int) $height;
		}

		$this->isResize = true;
		
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
		if ($this->useRatio && !$this->isResize) {
			$this->calculateAspectRatio($this->new_width, $this->new_height);
		}

		$size = [
			'sm'=> ['w'=>intval($this->new_width/4), 'h'=>intval($this->new_height/4)],
			'md'=> ['w'=>intval($this->new_width/2), 'h'=>intval($this->new_height/2)]
		]; 

		$imageSmall = imagecreatetruecolor($size['sm']['w'],$size['sm']['h']);

		/* Scale by 25% and apply Gaussian blur */
		imagecopyresampled($imageSmall, $this->imageData, 0, 0, 0, 0, $size['sm']['w'], $size['sm']['h'], $this->new_width, $this->new_height);

		$imageSmall = $this->applyBlur($imageSmall, $range, $argument);

		/* Scale result by 200% and blur again */
		$imageMedium = imagecreatetruecolor($size['md']['w'], $size['md']['h']);
		imagecopyresampled($imageMedium, $imageSmall, 0, 0, 0, 0, $size['md']['w'], $size['md']['h'], $size['sm']['w'], $size['sm']['h']);
		imagedestroy($imageSmall);

		$imageMedium = $this->applyBlur($imageMedium, 25, $argument);

		/* Scale result back to original size */
		imagecopyresampled($this->imageData, $imageMedium, 0, 0, 0, 0, $this->new_width, $this->new_height, $size['md']['w'], $size['md']['h']);
		imagedestroy($imageMedium); 

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
		$write = $this->writeImage($this->imageData, $saveTo, 100);
		return is_string($write) ? true : $write;
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
		$readExif = exif_read_data($this->imagePath);
		$readExif['DateTime'] = date('Y:m:d H:i:s');
		$exifData = array_merge($readExif, $data);
		$write = $this->setExif($saveTo, $exifData);
		return $write;
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
		$exifThumbnail = exif_thumbnail($this->imagePath, $width, $height, $type);
		$data['Thumbnail'] = $exifThumbnail;
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
		header('Content-Type: ' . $this->imageMime);
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
	 * Save the image to the specified path with optional formatting and quality.
	 *
	 * @param string $saveTo The path where the image should be saved.
	 * @param int $type Type of save (NanoImage::THUMBNAIL, NanoImage::TIMESTAMP, or NanoImage::DEFAULT).
	 * @param int $quality The quality of the saved image (1-100, defaults to 100).
	 * 
	 * 
	 * @return bool True on success, false on failure.
	 * > **Note:** the `$type` is used to specify how image should be saved, `NanoImage::DEFAULT` will delete existing image from directory
	 * > While `NanoImage::THUMBNAIL` will rename image using height and width
	 */
	public function save(string $saveTo, int $type = self::DEFAULT, int $quality = 100): bool 
	{
		$this->fileinfo($saveTo);
		return  $this->execute($type, $quality);
	}

	/**
	 * Save the image to the specified path with optional formatting, quality and extension.
	 *
	 * @param string $saveTo The path where the image should be saved.
	 * @param int $type Type of save (NanoImage::THUMBNAIL, NanoImage::TIMESTAMP, or NanoImage::DEFAULT).
	 * @param int $quality The quality of the saved image (1-100, defaults to 100).
	 * @param string|null $format Optional format to save the image (e.g., 'jpg', 'png').
	 * 
	 * @return bool True on success, false on failure.
	 * 
	 * > **Note:** the `$type` is used to specify how image should be saved, `NanoImage::DEFAULT` will delete existing image from directory
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
	 * @return self The current class instance.
	 */
	public function remove(): self 
	{
		unlink($this->imagePath);
		return $this;
	}

	/**
	 * Free the memory associated with the image.
	 *
	 * @return void
	 */
	public function free(): void 
	{
		if ($this->imageData) {
			imagedestroy($this->imageData);
		}

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
		$this->new_width = 0;
		$this->new_height = 0;
		$this->crop_height = 0;
		$this->crop_width = 0;
		$this->useRatio = false;
		$this->isResize = false;
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
			$this->new_width = (int) ($height * $aspectRatio);
			$this->new_height = $height;
		} else {
			$this->new_width = $width;
			$this->new_height = (int) ($width / $aspectRatio);
		}

		$this->crop_width = (int) $width;
		$this->crop_height = (int) $height;
		$this->useRatio = true;
	}

	/**
	 * Default location to save image.
	 * 
	 * @return string 
	 */
	private function defaultLocation(): string
	{
		return __DIR__ . DIRECTORY_SEPARATOR . "images" . DIRECTORY_SEPARATOR . "nano-image" . self::JPEG;
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
		$write = false;
		if ($this->useRatio && !$this->isResize) {
			$this->calculateAspectRatio($this->new_width, $this->new_height);
		}

		$imageResource = imagecreatetruecolor($this->new_width, $this->new_height);
		if ($imageResource !== false) {
			$white = imagecolorallocate($imageResource, 255, 255, 255);
			imagefilledrectangle($imageResource, 0, 0, $this->new_width, $this->new_height, $white);
			if (imagecopyresampled($imageResource, $this->imageData, 0, 0, 0, 0, $this->new_width, $this->new_height, $this->width, $this->height)) {
				$write = $this->writeImage($imageResource, $path, $quality);
			}

			imagedestroy($imageResource);
		}

		return $write;
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
		switch ($this->imageIntType) {
			case IMAGETYPE_BMP:
				$result = function_exists('imagebmp') 
					? imagebmp($image, $file) 
					: $this->image_bmp($image, $file);
				break;
			case IMAGETYPE_PNG:
				$result = imagepng($image, $file, $quality);
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
		
		imagedestroy($this->imageData);
		
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
		if(!is_dir($this->dirname)){
			mkdir($this->dirname, 0755, true);
		}

		if(file_exists($this->finalPath)){
			$deleteFile = true;
		    if($nameFormat !== self::DEFAULT){
				if($nameFormat === self::THUMBNAIL){
					$thumbnailPath = $this->dirname . DIRECTORY_SEPARATOR . $this->filename . "-" . $this->crop_width . 'x' . $this->crop_height . "." . $this->extension;
					$deleteFile = file_exists($thumbnailPath);
					$this->finalPath = $thumbnailPath;
				}else if($nameFormat === self::TIMESTAMP){
					$deleteFile = false;
					$this->finalPath = $this->dirname . DIRECTORY_SEPARATOR . $this->filename . "-" . date("d-m-y h:m:s") . "." . $this->extension;
				}
		    }
			
			if($deleteFile){
				unlink($this->finalPath);
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
		$info = pathinfo((!empty($saveTo) ? $saveTo : $this->defaultLocation()) );
		$this->extension = ($extension === null ? strtolower($info['extension']) : $extension);
		$this->dirname = $info['dirname'] ?? '';
		$this->filename = $info['filename'] ?? '';
		$this->finalPath = $this->dirname . DIRECTORY_SEPARATOR . $this->filename . "." . $this->extension;
	}

	/**
	 * Bitmap image function write image to file or output to browser.
	 *
	 * @param GdImage $image image resource.
	 * @param string|null $file optional image name and path to save.
	 *
	 * @return string|bool Return true if file path is specified and images is written, otherwise return false.
	 */
	private function image_bmp(GdImage $image, ?string $file = null, int $quality = -1): bool
	{
		if (!$image || ($quality !== -1 && ($quality < 1 || $quality > 100))) {
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
		$bmpData .= pack('V', 0); // Compression method (0 = uncompressed)
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

			$bmpData .= str_repeat("\x00", $width % 4); // Padding to ensure row length is multiple of 4 bytes
		}

		// Free memory of scaled image
		if($quality !== -1){
			imagedestroy($image);
		}

		if ($file === null) {
			echo $bmpData;
			return true;
		}

		return file_put_contents($file, $bmpData) !== false;
	}
}
