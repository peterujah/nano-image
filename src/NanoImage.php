<?php
/**
 * @author      Peter Chigozie(NG) peterujah
 * @copyright   Copyright (c), 2019 Peter(NG) peterujah
 * @license     MIT public license
 */
namespace Peterujah\NanoBlock;

/**
 * Class NanoImage.
 */
class NanoImage{
	public const JPG = "jpg";
	public const JPEG = "jpeg";
	public const PNG = "png";
	public const GIF = "gif";
	public const WEBP = "webp";
	public const BMP = "bmp";
	public const THUMBNAIL = 1;
	public const TIMESTAMP = 2;

	private $imagePath;
	private $imageData;
	private $new_height;
	private $new_width;
	private $crop_width;
	private $crop_height;
	private $height;
	private $width;
	private $imageType;
	private $extension;
	private $dirname;
	private $filename;
	private $finalPath;

	public function __construct(){

	}

	/**
	* Open image from url or path.
	*
	* @param string $url The string image url
	* @return object class instance
	*/
	public function open($url){
		$this->imagePath = $url;
		list($width, $height, $imageType, $mime) = getimagesize($this->imagePath);
		$this->height = $height;
		$this->width = $width;
		$this->new_width = $width;
		$this->new_height = $height;
		$this->imageType = $imageType;

		if ($imageType === IMAGETYPE_JPEG) {
			$this->imageData = @imagecreatefromjpeg($this->imagePath);
		} elseif ($imageType === IMAGETYPE_PNG) {
			$this->imageData = @imagecreatefrompng($this->imagePath);
		} elseif ($imageType === IMAGETYPE_GIF) {
			$this->imageData = imagecreatefromgif($this->imagePath);
		} elseif ($imageType === IMAGETYPE_WEBP) {
			$this->imageData = imagecreatefromwebp($this->imagePath);
		} elseif ($mime === 'image/bmp') {
			$this->imageData = imagecreatefrombmp($this->imagePath);
		} else {
			trigger_error('Unsupported image format');
		}
		return $this;
		
	}

	/**
	* Load image from string.
	* @param string $image_data The string image data
	* @return object class instance
	*/
	public function load($image_data){
		$this->imageData = imagecreatefromstring($image_data);
		list($width, $height, $imageType, $mime) = getimagesizefromstring($image_data);
		$this->height = $height;
		$this->width = $width;
		$this->new_width = $width;
		$this->new_height = $height;
		$this->imageType = $imageType;
		return $this;
	}

	/**
	* get image width.
	* @return int image width
	*/
	public function getWidth(){
		return $this->width;
	}

	/**
	* get image height.
	* @return int image height
	*/
	public function getHeight(){
		return $this->height;
	}

	/**
	* Set image height.
	*
	* @param int $height The original height of image
	*
	* @return object class instance
	*/
	public function setHeight($height){
		$this->height = $height;
		$this->new_height = $height;
		return $this;
	}
 
	/**
	* Set image width.
	*
	* @param int $width The original width of image
	*
	* @return object class instance
	*/
	public function setWidth($width){
		$this->width = $width;
		$this->new_width = $width;
		return $this;
	}

	/**
	* Resize image algorithm.
	*
	* @param int $width The request width to set image
	* @param int $height The request height to set image
	* @param bool $ratio Auto calculate image ratio
	* 
	* @return object class instance
	*/
	public function resize($width, $height, $ratio = false){
		if($ratio){
		    if($this->width > $this->height){
				$newHeight = $height;
				$newWidth = ($width / $this->height) * $this->width;
		    }else{
				$newWidth = $width;
				$newHeight = ($height / $this->width) * $this->height;
		    }
		    $this->new_width = $newWidth;
		    $this->new_height = $newHeight;
		}else{
		    $this->new_width = $width;
		    $this->new_height = $height;
		}
		$this->crop_width = $width;
		$this->crop_height = $height;
		return $this;
	}

	private function localPath(){
		return __DIR__ . DIRECTORY_SEPARATOR . "images" . DIRECTORY_SEPARATOR . "nano-image" . self::JPEG;
	}

	/**
	* Build image in browser.
	*
	* @param int $quality The require quality to set image
	* @param string $path Optional path, set to null to ignore saving image or supply path to save a copy
	* @return image resource identifier on success, false on errors.
	*/

	private function build($path, $quality, $extension){
		$createImage = @imagecreatetruecolor($this->new_width, $this->new_height);
		if ($createImage !== false) {
			$white = imagecolorallocate($createImage, 255, 255, 255);
			imagefilledrectangle($createImage, 0, 0, $this->new_width, $this->new_height, $white);
			if (imagecopyresampled($createImage, $this->imageData, 0, 0, 0, 0, $this->new_width, $this->new_height, $this->width, $this->height)) {
				return $this->writeImage($createImage, $path, $extension, $quality);
			}
			return $createImage;
		}
		return false;
	}
	

	/**
	* Save image data 
	* @param data $image image resource 
	* @param string $path image new path  
	* @param string $extension image extension 
	* @param int $quality image quality 
	* @return image||bool image resource identifier or false
	*/
	private function writeImage($image, $path = null, $extension = "jpg", $quality = 100) {
		$imageResource = false;
		if ($extension == self::PNG) {
			$imageResource = imagepng($image, $path, $quality);
		} else if ($extension == self::GIF) {
			$imageResource = imagegif($image, $path, $quality);
		} else if ($extension == self::WEBP) {
			$imageResource = imagewebp($image, $path, $quality);
		} else if ($extension == self::BMP) {
			if (function_exists('imagebmp')) {
				$imageResource = imagebmp($image, $path);
			} else {
				$imageResource = $this->image_bmp($image, $path);
			}
		} else {
			$imageResource = imagejpeg($image, $path, $quality);
		}
		imagedestroy($this->imageData);
		return $imageResource;
	}
	

	/**
	* Remove image exif data
	* @param int $to Path to save new image
	* @return bool true
	*/
	public function removeExif($to){
		return $this->writeImage($this->imageData, $to, $this->imageType, 100);
	}

	/**
	* Add image exif data
	* @param string||path $to path to save image
	* @param array $addExif exif meta data
	* @return image resource identifier on success, false on errors.
	*/
	public function addExif($to, $addExif = array()){
		// Read the Exif data from the source image
		$readExif = exif_read_data($this->imagePath);
		$readExif['DateTime'] = date('Y:m:d H:i:s');
		$exifData = array_merge($readExif, $addExif);
		$exifThumbnail = exif_thumbnail($this->imagePath, $width, $height, $type);
		//$exifThumbnail = exif_thumbnail($this->imagePath, $this->width, $this->height, $this->imageType);
		$exifData['Thumbnail'] = $exifThumbnail;
		exif_write_data($exifData, $to);
		$this->writeImage($this->imageData, $to, $this->imageType, 100);
		$imageResource = $this->writeImage($this->imageData, $to, $this->imageType, 100);
		if(!$imageResource){
			imagedestroy($imageResource);
		}
		return true;
	}

	/**
	* Set and replace image exif data
	* @param string||path $to path to save image
	* @param array $exifData exif meta data
	* @return image resource identifier on success, false on errors.
	*/
	public function setExif($to, $exifData = array()){
		$exifThumbnail = exif_thumbnail($this->imagePath, $width, $height, $type);
		$exifData['Thumbnail'] = $exifThumbnail;
		exif_write_data($exifData, $to);
		$imageResource = $this->writeImage($this->imageData, $to, $this->imageType, 100);
		if(!$imageResource){
			imagedestroy($imageResource);
		}
		return true;
	}

	/**
	* Read image exif data
	* @param string||path $from path to image
	* @return array||bool image exif data or false 
	*/
	public function readExif($from = null){
		$exifData = exif_read_data(!empty($from) ? $from : $this->imagePath);
		if ($exifData !== false) {
			return $exifData;
		}
		return false;
	}

	/**
	* Display image in browser.
	* @param int $quality The require quality to set image
	* @return image resource identifier on success, false on errors.
	*/

	public function display($quality){
		$extension = strtolower(image_type_to_extension($this->imageType, false));
		header('Content-Type: ' . $this->imageType);
		$imageResource = $this->build(null, $quality, $extension);
		if(!$imageResource){
			imagedestroy($imageResource);
		}
	}

	/**
	* Execute image edit and save to directory.
	*
	* @param string $to Full directory to save image
	* @param int $nameFormat Specify how image should be saved 0 will delete existing image from directory
	* While passing thumbnail will rename image using height and width and timestamp will use timestamp to save the image
	* @param int $quality The require quality to set image
	* 
	*/
	private function execute($to, $nameFormat = 0, $quality = 90){
		if(!is_dir($this->dirname)){
			mkdir($this->dirname, 0755, true);
		}
		if(file_exists($this->finalPath)){
			$deleteFile = true;
		    if(!empty($nameFormat)){
				if($nameFormat == self::THUMBNAIL){
					$thumbnailPath = $this->dirname . DIRECTORY_SEPARATOR . $this->filename . "-" . $this->crop_width . 'x' . $this->crop_height . "." . $this->extension;
					$deleteFile = file_exists($thumbnailPath);
					$this->finalPath = $thumbnailPath;
				}else if($nameFormat == self::TIMESTAMP){
					$deleteFile = false;
					$this->finalPath = $this->dirname . DIRECTORY_SEPARATOR . $this->filename . "-" . date("d-m-y h:m:s") . "." . $this->extension;
				}
		    }
			
			if($deleteFile){
				unlink($this->finalPath);
		    }
		}
		return $this->build($this->finalPath, $quality, $this->extension);
	}

	/**
	* Set image fileinfo.
	*
	* @param string $to Full directory to save image
	* @param string $ext Save image with extension
	* 
	*/
	public function fileinfo($to, $ext = null){
		$info = pathinfo( (!empty($to) ? $to : $this->localPath()) );
		$this->extension = (!empty($ext) ? $ext : strtolower($info['extension']));
		$this->dirname = $info['dirname']??null;
		$this->filename = $info['filename']??null;
		$this->finalPath = $this->dirname . DIRECTORY_SEPARATOR . $this->filename . "." . $this->extension;
	}

	/**
	* Save image to directory.
	*
	* @param string $to Full directory to save image
	* @param string $image_type Specify how image should be saved NULL will delete existing image from directory
	* While passing thumbnail will rename image using height and width
	* @param int $quality The require quality to set image
	* 
	*/
	public function save($to, $image_type = null, $quality=90){
		$this->fileinfo($to);
		$imageResource = $this->execute($to, $image_type, $quality);
		if(!$imageResource){
			imagedestroy($imageResource);
			return true;
		}
		return false;
	}

	/**
	* Save image to directory using specific extension.
	*
	* @param string $to Full directory to save image
	* @param string $image_type Specify how image should be saved NULL will delete existing image from directory
	* While passing thumbnail will rename image using height and width
	* @param int $quality The require quality to set image
	* @param string $ext Save image with extension
	* 
	*/
	public function saveAs($to, $image_type = null, $quality=90, $ext = self::JPEG){
		$this->fileinfo($to, $ext);
		$imageResource = $this->execute($to, $image_type, $quality);
		if(!$imageResource){
			imagedestroy($imageResource);
			return true;
		}
		return false;
	}

	/**
	* Save image to directory by replacing old image.
	*
	* @param string $to Full directory to save image
	* @param int $quality The require quality to set image
	* 
	*/
	public function replace($to, $quality=90){
		$this->fileinfo($to);
		$imageResource = $this->execute($to, null, $quality);
		if(!$imageResource){
			imagedestroy($imageResource);
			return true;
		}
		return false;
	}
	
	/**
	* Remove original image 
	*/
	public function remove(){
		unlink($this->imagePath);
		return $this;
	}

	/**
	* Free image instance.
	*/
	public function free(){
		$this->imagePath = null;
		$this->imageData = null;
		$this->imageType = null;
		$this->extension = null;
		$this->dirname = null;
		$this->filename = null;
		$this->finalPath = null;
		$this->height = 0;
		$this->width = 0;
		$this->new_width = 0;
		$this->new_height = 0;
		$this->crop_height = 0;
		$this->crop_width = 0;
	}


	/**
	* Bitmap image function 
	* @param data $image image resource 
	* @param string $filename image name and path to sve
	* @return image||bool image resource identifier or false
	*/
	public function image_bmp($image, $filename = false) {
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

		if ($filename !== false) {
			file_put_contents($filename, $bmpData);
			return false;
		} else {
			return $bmpData;
		}
	}
}
