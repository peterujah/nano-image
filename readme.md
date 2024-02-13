## Nano Image

NanoImage is a simple PHP image resize class. It can resize images and display them in the browser or save images in a directory

## Installation

Installation is super-easy via Composer:
```md
composer require peterujah/nano-image
```

## Basic Usage

```php
$image = new NanoImage();
try{
  $image = $imagine->open("path/to/save/edit-image-size.jpg");

  $image->resize(360, 200, false);
  $image->save("path/to/save/new-image.jpg", NanoImage::THUMBNAIL, 80);

  $image->resize(116, 80, false);
  $image->saveAs("path/to/save/new-image.jpeg", NanoImage::THUMBNAIL, 100, NanoImage::JPEG);

  $image->free();
}catch(UnsupportedImageException $e){
  echo $e->getMessage();
}
```

```php
$img = new Peterujah\NanoBlock\NanoImage();
```
Initialize nano image class for use


Open and load any image from a directory path & file name

```php
$img->open(__DIR__ . "/path/to/assets/image.jpg");
```
Or load string containing the image data.

```php
$img->load($image_data);
```

Resize an image with the exact width and height passed, for example, 200x200. To resize an image using the aspect ratio set the third parameter to true

```php
$img->resize(200, 200, false||true);
```

Blur image 
```php
$img->blur(20);
```

Once image manipulation is done, display the output image on the browser. Pass quality of the image

```php
$img->display($quality);
```

Save an image to a directory, the first parameter specifies the path, the second sets the image naming option, and the third is image quality (90 by default)
```php
$img->save(__DIR__ . "/path/to/assets/new-image.jpg", NanoImage::DEFAULT, $quality);
```

Save image as

```php
$img->saveAs($to,  NanoImage::DEFAULT || NanoImage::THUMBNAIL || NanoImage::TIMESTAMP, $quality, self::JPEG)
```

Replace the existing image with a new one

```php
$img->replace($to, $quality)
```

Remove temp image after editing and free memory

```php
$img->remove()
```

Free memory

```php
$img->free()
```
