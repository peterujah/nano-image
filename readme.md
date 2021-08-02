## Nano Image

NanoImage is a simple php image resize class. It can resize image and display it in browser or save image in a directory


## Basic Usage

```php
$img = NanoBlockTech\NanoImage();
```
Initalize nano image class for use


Open image from path 

```php
$img->open(__DIR__ . "/path/to/assets/image.jpg");
```

Resize image with exact width and height passed, example 200x200 

```php
$img->resize(200, 200, false);
```
Resize image using aspect ratio

```php
$img->resize(200, 200, true);
```

Display image in browser, and pass qaulity of image

```php
$img->display(70);
```

Save image to directory
```php
$img->save(__DIR__ . "/path/to/assets/new-image.jpg", "thumbnail", 70);
    
