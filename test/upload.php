```php
<?php
  if(isset($_FILES['image'])){
    $ImageTemp = $_FILES['image']['tmp_name'];
    $fileName = 'old-image.jpg'; 
    $filePathAsset = __DIR__ . '/assets/img/nano/';
    
    if(!is_dir($filePathAsset)){
      @mkdir($urlPathAsset, 0777, true);
      @chmod($urlPathAsset, 0755);
    }

    if(file_exists($filePathAsset . $fileName)){
      @unlink($filePathAsset . $fileName);
    }

    if (@move_uploaded_file($ImageTemp, $filePathAsset . $fileName)) {
      $imagine = new  Peterujah\NanoBlock\NanoImage();
      $image = $imagine->open($logoPath . $fileName);

      $image->resize(360, 200, false);
      $image->save($filePathAsset . $imageName, false, 80, $imagine::JPEG);

      $image->resize(116, 80, false);
      $image->save($filePathAsset . $imageName, true, 100, $imagine::JPEG);

      $image->free();
    }
  }
