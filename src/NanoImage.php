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
	public const JPG = ".jpg";
	public const JPEG = ".jpeg";
	public const PNG = ".png";
	public const GIF = ".gif";
	public const WEBP = ".webp";
	public const THUMBNAIL = "thumbnail";

	private $image_url;
	private $image_data;
	private $new_height;
	private $new_width;
	private $crop_width;
	private $crop_height;
	private $height;
	private $width;
	private $extension;
	private $save_extension;
	private $dirname;
	private $filename;
	private $full_path;

	public function __construct(){

	}

	/**
	* Open image from url or path.
	*
	* @param string $url The string image url
	*
	* @return object class instance
	*/
	public function open($url){
		$this->image_url = $url;
		$info = pathinfo( $this->image_url);
		$this->extension = strtolower($info['extension']);
		list($width, $height) = getimagesize($this->image_url);
		$this->height = $height;
		$this->width = $width;
		$this->new_width = $width;
		$this->new_height = $height;
		if ($this->extension == 'jpeg' OR $this->extension == 'jpg'){ 
		    $this->image_data = @imagecreatefromjpeg($this->image_url);
		}else if ($this->extension == 'gif'){ 
		    $this->image_data = @imagecreatefromgif($this->image_url);
		}else if ($this->extension == 'png'){ 
		    $this->image_data = @imagecreatefrompng($this->image_url);
		}else if ($this->extension == 'webp'){ 
		    $this->image_data = @imagecreatefromwebp($this->image_url);
		}
		return $this;
	}

	/**
	* Load image from string.
	*
	* @param string $image_data The string image data
	*
	* @return object class instance
	*/
	public function load($image_data){
		$this->image_data = imagecreatefromstring($image_data);
		$this->height = @imagesy( $this->image_data );
		$this->width = @imagesx( $this->image_data );
		$this->new_width = $this->width;
		$this->new_height = $this->height;
		return $this;
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
	* 
	* @return image resource identifier on success, false on errors.
	*/

	private function build($path, $quality){
		if($createImage = @imagecreatetruecolor($this->new_width, $this->new_height)){
			if(!$createImage){
				$white = @imagecolorallocate($createImage, 255, 255, 255);
				@imagefilledrectangle($createImage,0,0,$this->new_width,$this->new_height,$white);
				if(@imagecopyresampled($createImage, $this->image_data, 0, 0, 0, 0, $this->new_width, $this->new_height, $this->width, $this->height)){
					@imagejpeg($createImage, $path, $quality); 
					@imagedestroy($createImage);
					return true;
				}
			}
		}
		return false;
	}

	/**
	* Display image in browser.
	*
	* @param int $quality The require quality to set image
	* 
	* @return image resource identifier on success, false on errors.
	*/

	public function display($quality){
		return $this->build(null, $quality);;
	}

	/**
	* Execute image edit and save to directory.
	*
	* @param string $to Full directory to save image
	* @param string $image_type Specify how image should be saved NULL will delete existing image from directory
	* While passing thumbnail will rename image using height and width
	* @param int $quality The require quality to set image
	* 
	*/
	private function execute($to, $image_type = null, $quality = 90){
		if(!is_dir($this->dirname)){
		    mkdir($this->dirname, 0777, true);
		    chmod($this->dirname, 0755);
		}
		if(file_exists($this->full_path)){
		    if(!empty($image_type)){
				if($image_type == self::THUMBNAIL){
					$this->full_path = $this->dirname . DIRECTORY_SEPARATOR . $this->filename . "-" . $this->crop_width . 'x' . $this->crop_height . "." . $this->save_extension;
					if(file_exists($this->full_path)){
						unlink($this->full_path);
					}
				}else{
					$this->full_path = $this->dirname . DIRECTORY_SEPARATOR . $this->filename . "-" . date("d-m-y h:m:s") . "." . $this->save_extension;
				}
		    }else{
				unlink($this->full_path);
		    }
		}
		return $this->build($this->full_path, $quality);
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
		$this->save_extension = (!empty($ext) ? $ext : strtolower($info['extension']));
		$this->dirname = $info['dirname']??null;
		$this->filename = $info['filename']??null;
		$this->full_path = $this->dirname . DIRECTORY_SEPARATOR . $this->filename . "." . $this->save_extension;
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
		return $this->execute($to, $image_type, $quality);
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
		return $this->execute($to, $image_type, $quality);
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
		return $this->execute($to, null, $quality);
	}
	
	/**
	* Remove original image 
	*/
	public function remove(){
		unlink($this->image_url);
		return $this;
	}

	/**
	* Free image instance.
	*/
	public function free(){
		$this->image_url = null;
		$this->image_data = null;
		$this->extension = null;
		$this->save_extension = null;
		$this->dirname = null;
		$this->filename = null;
		$this->full_path = null;
		$this->height = 0;
		$this->width = 0;
		$this->new_width = 0;
		$this->new_height = 0;
		$this->crop_height = 0;
		$this->crop_width = 0;
	}
}
