<?php
require_once __DIR__ . "/autoload.php";

use Peterujah\NanoBlock\NanoImage;
use Peterujah\NanoBlock\UnsupportedImageException;
if(isset($_FILES['image'])){
    $ImageTemp = $_FILES['image']['tmp_name'];
    $fileName = 'old-image.jpg'; 
    $filePathAsset = __DIR__ . '/assets/img/nano/';

    if(!is_dir($filePathAsset)){
        mkdir($urlPathAsset, 0755, true);
    }

    if(file_exists($filePathAsset . $fileName)){
        unlink($filePathAsset . $fileName);
    }

    if (@move_uploaded_file($ImageTemp, $filePathAsset . $fileName)) {
        $imagine = new NanoImage();
        try{
            $image = $imagine->open($logoPath . $fileName);

            $image->resize(360, 200, false);
            $image->save($filePathAsset . $imageName, NanoImage::THUMBNAIL, 80);

            $image->resize(116, 80, false);
            $image->saveAs($filePathAsset . $imageName, NanoImage::THUMBNAIL, 100, $imagine::JPEG);

            $image->free();
        }catch(UnsupportedImageException $e){
            echo $e->getMessage();
        }
    }
}
