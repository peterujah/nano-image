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

	private $image_url;
	private $image_data;
	private $new_height;
	private $new_width;
	private $crop_width;
	private $crop_height;
	private $height;
	private $width;
	private $extension;
	
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
        $createImage = @imagecreatetruecolor($this->new_width, $this->new_height);
	$white = @imagecolorallocate($createImage, 255, 255, 255);
	@imagefilledrectangle($createImage,0,0,$this->new_width,$this->new_height,$white);

	@imagecopyresampled($createImage, $this->image_data, 0, 0, 0, 0, $this->new_width, $this->new_height, $this->width, $this->height);
	@imagejpeg($createImage, $path, $quality); 
	@imagedestroy($createImage);
        return $createImage;
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
     * Save image to directory.
     *
     * @param string $to Full directory to save image
     * @param string $copy_algo Specify how image should be saved NULL will delete existing image from directory
     * While passing thumbnail will rename image using height and width
     * @param int $quality The require quality to set image
     * 
     */
    public function save($to, $copy_algo=null, $quality=90){
        $info = pathinfo( (!empty($to) ? $to : $this->localPath()) );
        $extension = strtolower($info['extension']);
        $dirname = $info['dirname']??null;
        $filename = $info['filename']??null;
        $full_path = $dirname . DIRECTORY_SEPARATOR . $filename . "." . $extension;

        if(!is_dir($dirname)){
            mkdir($dirname, 0777, true);
            chmod($dirname, 0755);
        }
        if(file_exists($full_path)){
            if(!empty($copy_algo)){
                if($copy_algo == "thumbnail"){
                    $full_path = $dirname . DIRECTORY_SEPARATOR . $filename . "-" . $this->crop_width . 'x' . $this->crop_height . "." . $extension;
                    if(file_exists($full_path)){
                        unlink($full_path);
                    }
                }else{
                    $full_path = $dirname . DIRECTORY_SEPARATOR . $filename . "-" . date("d-m-y h:m:s") . "." . $extension;
                }
            }else{
                unlink($full_path);
            }
        }
        $this->build($full_path, $quality);
    }

    /**
     * Free image instance.
     */
    public function free(){
        $this->image_url = null;
        $this->image_data = null;
        $this->extension = null;
        $this->height = 0;
        $this->width = 0;
        $this->new_width = 0;
        $this->new_height = 0;
        $this->crop_height = 0;
        $this->crop_width = 0;
    }
}
