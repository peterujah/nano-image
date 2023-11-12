## Nano Image

NanoImage is a simple php image resize class. It can resize image and display it in browser or save image in a directory

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

Resize image with exact width and height passed, example 200x200. To resize image using aspect ratio set the thrid parameter to true

```php
$img->resize(200, 200, false||true);
```

Once image manipulation is done display the output image on browser. Pass qaulity of image

```php
$img->display($quality);
```

Save image to directory, first parameter specify the path, second default is null while quality is 90 by default
```php
$img->save(__DIR__ . "/path/to/assets/new-image.jpg", NanoImage::THUMBNAIL || null, $quality);
```

Save image as

```php
$img->saveAs($to, NanoImage::THUMBNAIL || NanoImage::TIMESTAMP, $quality, self::JPEG)
```

Replace existing image with new one

```php
$img->replace($to, $quality)
```

Remove temp image after editing and free momory

```php
$img->remove()
```

Free memory

```php
$img->free()
```
