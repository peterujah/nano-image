Hi

```
<?php 
  //Initalize nano image class
  $img = NanoBlockTech\NanoImage();

  //Open image from path
  $img->open(__DIR__ . "/path/to/assets/image.jpg");

  //Resize image with exact 200x200
  $img->resize(200, 200, false);

  //Resize image using aspect ratio
  $img->resize(200, 200, true);

  //Display image in browser with qaulity of 80 from original image
  $img->display(70);

  //Save image to directory
  $img->save(__DIR__ . "/path/to/assets/new-image.jpg", "thumbnail", 70);
?>
```
