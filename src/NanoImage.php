<?php
/**
 * @author      Peter Chigozie(NG) peterujah
 * @copyright   Copyright (c), 2019 Peter(NG) peterujah
 * @license     MIT public license
 */
namespace Peterujah\NanoBlock;

use Peterujah\NanoBlock\UnsupportedImageException;
use \GdImage;
/**
 * Class NanoImage.
 */
class NanoImage
{
	/**
	* Save image type of JPG
	* @var string JPG 
	*/
	public const JPG = "jpg";

	/**
	* Save image type of JPEG
	* @var string JPEG 
	*/
	public const JPEG = "jpeg";

	/**
	* Save image type of PNG
	* @var string PNG 
	*/
	public const PNG = "png";

	/**
	* Save image type of GIF
	* @var string GIF 
	*/
	public const GIF = "gif";

	/**
	* Save image type of WEBP
	* @var string WEBP 
	*/
	public const WEBP = "webp";

	/**
	* Save image type of BMP
	* @var string BMP 
	*/
	public const BMP = "bmp";

	/**
	* Save image as thumbnail height-width
	* @var int THUMBNAIL 
	*/
	public const THUMBNAIL = 1;

	/**
	* Save image with timestamp
	* @var int TIMESTAMP 
	*/
	public const TIMESTAMP = 2;

	/**
	* Save image with name
	* @var int DEFAULT 
	*/
	public const DEFAULT = 0;

	/**
	* Image path location
	* @var string $imagePath 
	*/
	private string $imagePath = '';

	/**
	* Image resource
	* @var GdImage|bool $imageData 
	*/
	private GdImage|bool $imageData = false;

	/**
	* Image final height
	* @var int $new_height 
	*/
	private int $new_height = 0;

	/**
	* Image final width
	* @var int $new_width 
	*/
	private int $new_width = 0;

	/**
	* Image cropped width
	* @var int $crop_width 
	*/
	private int $crop_width = 0;

	/**
	* Image cropped height
	* @var int $crop_height 
	*/
	private int $crop_height = 0;

	/**
	* Image height
	* @var int $height 
	*/
	private int $height = 0;

	/**
	* Image width
	* @var int $width 
	*/
	private int $width = 0;

	/**
	* Image mime type
	*
	* @var string $imageType 
	*/
	private string $imageMime = '';

	/**
	* Image ini type
	*
	* @var int $imageIntType 
	*/
	private int $imageIntType = 0;

	/**
	* Image extension type
	* @var string $extension 
	*/
	private string $extension = '';

	/**
	* Image save directory name
	* @var string $dirname 
	*/
	private string $dirname = '';

	/**
	* Image file name
	* @var string $filename 
	*/
	private string $filename = '';

	/**
	* Image save location
	* @var string $finalPath 
	*/
	private string $finalPath = '';

	/**
	* Image aspect ration
	* @var bool $useRatio 
	*/
	private bool $useRatio = false;

	/**
	* Image resize method called
	* @var bool $isResize 
	*/
	private bool $isResize = false;

	/**
	 * Initialize NanoImage class
	*/
	public function __construct(){}

	/**
	* Load image from url or path.
	*
	* @param string $imageLocation The string image url
	*
	* @return NanoImage $this class instance
	* @throws InvalidArgumentException
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
     * Load image from string.
     *
     * @param string $imageString The string image data
     *
     * @return NanoImage $this class instance
     * @throws UnsupportedImageException If the image type is not supported
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
	* get image width.
	* @return int image width
	*/
	public function getWidth(): int 
	{
		return $this->width;
	}

	/**
	* get image height.
	* @return int image height
	*/
	public function getHeight(): int 
	{
		return $this->height;
	}

	/**
	* Set image height.
	*
	* @param int $height The original height of image
	*
	* @return NanoImage $this class instance
	*/
	public function setHeight(int $height): self 
	{
		$this->new_height = $height;
		return $this;
	}
 
	/**
	* Set image width.
	*
	* @param int $width The original width of image
	*
	* @return NanoImage $this class instance
	*/
	public function setWidth(int $width): self 
	{
		$this->new_width = $width;
		return $this;
	}

	/**
	* Set resize image with aspect ratio.
	*
	* @param bool $ratio 
	*
	* @return NanoImage $this class instance
	*/
	public function aspectRatio(bool $ratio): self 
	{
		$this->useRatio = $ratio;

		return $this;
	}

	/**
	* Resize image calculate aspect ratio if specified.
	*
	* @param int $width The request width to set image
	* @param int $height The request height to set image
	* @param bool $ratio Auto calculate image ratio
	* 
	* @return NanoImage $this class instance
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
	* calculate image aspect ratio if specified.
	*
	* @param int $width The request width to set image
	* @param int $height The request height to set image
	* 
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
	* Blur image.
	*
	* @param int $range The blur range
	* @param int $argument Blur argument
	* 
	* @return NanoImage $this class instance
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
	* Apply blur to image.
	*
	* @param GdImage $image The image to blur
	* @param int $range The blur range
	* @param int $argument Blur argument
	* 
	* @return GdImage $image image resource
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
	* Default location to save image
	* 
	* @return string 
	*/
	private function defaultLocation(): string
	{
		return __DIR__ . DIRECTORY_SEPARATOR . "images" . DIRECTORY_SEPARATOR . "nano-image" . self::JPEG;
	}

	/**
	* Build image 
	*
	* @param string $path Optional path, set to null to ignore saving image or supply path to save a copy
	* @param int $quality Set image quality
	*
	* @return string|bool string resource or bool.
	*/
	private function build(?string $path = null, int $quality = 100): string|bool
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
	* @return string|bool image resource identifier or bool
	*/
	private function writeImage(GdImage $image, ?string $file = null, int $quality = 100): string|bool
	{
		$result = false;
		switch ($this->imageIntType) {
			case IMAGETYPE_BMP:
				if (function_exists('imagebmp')) {
					$result = imagebmp($image, $file);
				} else {
					$result = $this->image_bmp($image, $file);
				}
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
	* Remove image exif data
	* @param string $saveTo Path to save new image
	*
	* @return bool
	*/
	public function removeExif(string $saveTo): bool 
	{
		$write = $this->writeImage($this->imageData, $saveTo, 100);
		return is_string($write) ? true : $write;
	}

	/**
	* Add image exif data
	*
	* @param string $saveTo path to save image
	* @param array $data exif meta data
	*
	* @return bool 
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
	* Set and replace image exif data
	*
	* @param string $path path to save image
	* @param array $exifData exif meta data
	*
	* @return bool
	*/
	public function setExif(string $saveTo, array $data = []): bool 
	{
		$exifThumbnail = exif_thumbnail($this->imagePath, $width, $height, $type);
		$data['Thumbnail'] = $exifThumbnail;
		exif_write_data($data, $saveTo);

		$write = $this->writeImage($this->imageData, $saveTo, 100);

		return is_string($write) ? true : $write;
	}

	/**
	* Display image in browser.
	*
	* @param int $quality The require quality to set image
	*
	* @return void
	*/
	public function display(int $quality = 100): void 
	{
		$write = $this->get($quality);
		header('Content-Type: ' . $this->imageMime);

		if(is_string($write)){
			echo $write;
		}
	}

	/**
	* Display image in browser.
	*
	* @param int $quality The require quality to set image
	*
	* @return void
	*/
	public function get(int $quality = 100): string|bool 
	{
		return $this->build(null, $quality);
	}

	/**
	* Execute image edit and save to directory.
	*
	* @param int $nameFormat Specify how image should be saved NanoImage::DEFAULT will delete existing image from directory
	* While passing thumbnail will rename image using height and width and timestamp will use timestamp to save the image
	* @param int $quality The require quality to set image
	*
	* @return string|bool
	*/
	private function execute(int $nameFormat = self::DEFAULT, int $quality = 90): mixed 
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
		$info = pathinfo( (!empty($saveTo) ? $saveTo : $this->defaultLocation()) );
		$this->extension = ($extension === null ? strtolower($info['extension']) : $extension);
		$this->dirname = $info['dirname'] ?? '';
		$this->filename = $info['filename'] ?? '';
		$this->finalPath = $this->dirname . DIRECTORY_SEPARATOR . $this->filename . "." . $this->extension;
	}

	/**
	* Save image to directory.
	*
	* @param string $saveTo Full directory to save image
	* @param int $type Specify how image should be saved 0 will delete existing image from directory
	* While passing thumbnail will rename image using height and width
	* @param int $quality The require quality to set image
	* 
	* @return bool
	*/
	public function save(string $saveTo, int $type = self::DEFAULT, $quality = 90): bool 
	{
		$this->fileinfo($saveTo);
		$write = $this->execute($type, $quality);
		return is_string($write) ? true : $write;
	}

	/**
	* Save image to directory using specific extension.
	*
	* @param string $saveTo Full directory to save image
	* @param int $type Specify how image should be saved 0 will delete existing image from directory
	* While passing thumbnail will rename image using height and width
	* @param int $quality The require quality to set image
	* @param string $extension Save image with extension
	* 
	* @return bool
	*/
	public function saveAs(string $saveTo, int $type = self::DEFAULT, int $quality = 90, string $extension = self::JPEG): bool 
	{
		$this->fileinfo($saveTo, $extension);
		$write = $this->execute($type, $quality);
		return is_string($write) ? true : $write;
	}

	/**
	* Save image to directory by replacing old image.
	*
	* @param string $saveTo Full directory to save image
	* @param int $quality The require quality to set image
	*
	* @return bool
	*/
	public function replace(string $saveTo, int $quality = 90): bool 
	{
		$this->fileinfo($saveTo);
		$write = $this->execute(self::DEFAULT, $quality);
		return is_string($write) ? true : $write;
	}
	
	/**
	* Remove original image 
	*
	* @return NanoImage $this
	*/
	public function remove(): self 
	{
		unlink($this->imagePath);
		return $this;
	}


	/**
	* Bitmap image function 
	* @param GdImage $image image resource 
	* @param string|null $file optional image name and path to sve
	*
	* @return string|bool  true or false
	*/
	public function image_bmp(GdImage $image, ?string $file = null): string|bool
	{
		if ($image === null) {
			return false;
		}

		$width = imagesx($image);
		$height = imagesy($image);

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

		if($file === null){
			return $bmpData;
		}
	
		if(file_put_contents($file, $bmpData) === false){
			return false;
		}

		return true;
	}

	/**
	* Free image instance.
	*
	* @return void
	*/
	public function free(): void 
	{
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
}
