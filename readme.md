## Nano Image

NanoImage is a simple php image resize class. It can resize image and display it in browser or save image in a directory

## Installation

Installation is super-easy via Composer:
```md
composer require peterujah/nano-imag
```

## Basic Usage

```php
$img = Peterujah\NanoBlock\NanoImage();
```
Initalize nano image class for use


Open and load image from by passing the directory path & file name

```php
$img->open(__DIR__ . "/path/to/assets/image.jpg");
```
Or load string containing the image data.

```php
$img->load($image_data);
```

Resize image with exact width and height passed, example 200x200. To resize image using aspect ratio set the thrid parameter to true

```php
$img->resize(200, 200, false);
```

Once image manipulation is done display the output image on browser. Pass qaulity of image

```php
$img->display(70);
```

Save image to directory, first parameter specify the path, second default is null while quality is 90 by default
```php
$img->save(__DIR__ . "/path/to/assets/new-image.jpg", NanoImage::THUMBNAIL, 70);
```

Save image as

```php
$img->saveAs($to, $image_type = null, $quality=90, $ext = self::JPEG)
```

Replace existing image with new one

```php
$img->replace($to, $quality=90)
```

Remove temp image after editing and free momory

```php
$img->remove()
```

Free memory

```php
$img->free()
```
