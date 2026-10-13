<?php
/**
 * Tests for Peterujah\Media\NanoImage.
 *
 * Written to run on PHPUnit 6.5 - 9.6 (PHP 7.0+): no setUp()/tearDown() signatures, no
 * assertions that were renamed or removed between those versions, and no PHP 7.1+ syntax.
 * Tests marked "Regression" reproduce a defect that existed in the original implementation.
 */
namespace Peterujah\Media\Tests;

use PHPUnit\Framework\TestCase;
use Peterujah\Media\NanoImage;
use Peterujah\Media\UnsupportedImageException;

class NanoImageTest extends TestCase
{
	/** @var string|null Temporary directory for the running test. */
	private $dir = null;

	/**
	 * Delete the temporary directory after every test.
	 *
	 * @after
	 */
	public function removeTemporaryFiles()
	{
		if ($this->dir !== null) {
			self::removeDirectory($this->dir);
			$this->dir = null;
		}
	}

	// ------------------------------------------------------------------
	// Helpers
	// ------------------------------------------------------------------

	private static function removeDirectory($dir)
	{
		if (!is_dir($dir)) {
			return;
		}

		foreach (scandir($dir) as $item) {
			if ($item === '.' || $item === '..') {
				continue;
			}

			$path = $dir . DIRECTORY_SEPARATOR . $item;
			is_dir($path) ? self::removeDirectory($path) : @unlink($path);
		}

		@rmdir($dir);
	}

	private function tmp()
	{
		if ($this->dir === null) {
			$this->dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'nanoimage-test-' . str_replace('.', '', uniqid('', true));
			mkdir($this->dir, 0777, true);
		}

		return $this->dir;
	}

	private function path($name)
	{
		return $this->tmp() . DIRECTORY_SEPARATOR . $name;
	}

	private function formatAvailable($format)
	{
		switch ($format) {
			case 'jpeg':
				return function_exists('imagejpeg') && function_exists('imagecreatefromjpeg');
			case 'png':
				return function_exists('imagepng') && function_exists('imagecreatefrompng');
			case 'gif':
				return function_exists('imagegif') && function_exists('imagecreatefromgif');
			case 'webp':
				return defined('IMAGETYPE_WEBP') && function_exists('imagewebp') && function_exists('imagecreatefromwebp');
			case 'bmp':
				return function_exists('imagebmp') && function_exists('imagecreatefrombmp');
		}

		return false;
	}

	private function requireFormat($format)
	{
		if (!$this->formatAvailable($format)) {
			$this->markTestSkipped("GD in this PHP build cannot read and write {$format}.");
		}
	}

	private function imageTypeOf($format)
	{
		$types = [
			'jpeg' => IMAGETYPE_JPEG,
			'jpg' => IMAGETYPE_JPEG,
			'png' => IMAGETYPE_PNG,
			'gif' => IMAGETYPE_GIF,
			'bmp' => IMAGETYPE_BMP,
			'webp' => defined('IMAGETYPE_WEBP') ? IMAGETYPE_WEBP : 18,
		];

		return $types[$format];
	}

	private function encodeGd($im, $format)
	{
		ob_start();

		switch ($format) {
			case 'jpeg':
				imagejpeg($im, null, 100);
				break;
			case 'png':
				imagepng($im);
				break;
			case 'gif':
				imagegif($im);
				break;
			case 'webp':
				imagewebp($im, null, 100);
				break;
			case 'bmp':
				imagebmp($im);
				break;
		}

		return ob_get_clean();
	}

	/**
	 * Left half red, right half blue (or transparent when `$transparent` is true).
	 */
	private function makeImage($format = 'png', $width = 40, $height = 20, $transparent = false)
	{
		$im = imagecreatetruecolor($width, $height);

		if ($transparent) {
			imagealphablending($im, false);
			imagesavealpha($im, true);
			imagefill($im, 0, 0, imagecolorallocatealpha($im, 0, 0, 0, 127));
			$red = imagecolorallocatealpha($im, 255, 0, 0, 0);
		} else {
			imagefill($im, 0, 0, imagecolorallocate($im, 0, 0, 255));
			$red = imagecolorallocate($im, 255, 0, 0);
		}

		imagefilledrectangle($im, 0, 0, max(0, intdiv($width, 2) - 1), $height - 1, $red);

		return $this->encodeGd($im, $format);
	}

	/**
	 * Four coloured quadrants: top-left red, top-right green, bottom-left blue, bottom-right yellow.
	 */
	private function makeQuadrants($format = 'png', $width = 100, $height = 80)
	{
		$im = imagecreatetruecolor($width, $height);
		$halfW = intdiv($width, 2);
		$halfH = intdiv($height, 2);

		imagefilledrectangle($im, 0, 0, $halfW - 1, $halfH - 1, imagecolorallocate($im, 255, 0, 0));
		imagefilledrectangle($im, $halfW, 0, $width - 1, $halfH - 1, imagecolorallocate($im, 0, 255, 0));
		imagefilledrectangle($im, 0, $halfH, $halfW - 1, $height - 1, imagecolorallocate($im, 0, 0, 255));
		imagefilledrectangle($im, $halfW, $halfH, $width - 1, $height - 1, imagecolorallocate($im, 255, 255, 0));

		return $this->encodeGd($im, $format);
	}

	private function writeFile($name, $bytes)
	{
		$path = $this->path($name);
		file_put_contents($path, $bytes);

		return $path;
	}

	private function size($bytes)
	{
		$info = getimagesizefromstring($bytes);
		$this->assertTrue($info !== false, 'Output is not a valid image.');

		return [$info[0], $info[1], $info[2]];
	}

	/**
	 * @return int[] red, green, blue, alpha (alpha 0 = opaque, 127 = transparent)
	 */
	private function pixel($bytes, $x, $y)
	{
		$im = imagecreatefromstring($bytes);
		$this->assertTrue($im !== false, 'Output could not be decoded.');
		$c = imagecolorat($im, $x, $y);

		if (!imageistruecolor($im)) {
			$c = imagecolorsforindex($im, $c);
			return [$c['red'], $c['green'], $c['blue'], $c['alpha']];
		}

		return [($c >> 16) & 255, ($c >> 8) & 255, $c & 255, ($c >> 24) & 127];
	}

	private function assertColor(array $expected, array $actual, $tolerance = 40)
	{
		for ($i = 0; $i < 3; $i++) {
			$this->assertTrue(
				abs($expected[$i] - $actual[$i]) <= $tolerance,
				'Expected colour ' . implode(',', $expected) . ' but got ' . implode(',', array_slice($actual, 0, 3))
			);
		}
	}

	private function captureWarnings($callback)
	{
		$messages = [];
		set_error_handler(function ($number, $message) use (&$messages) {
			$messages[] = $message;
			return true;
		});

		try {
			$callback();
		} finally {
			restore_error_handler();
		}

		return $messages;
	}

	public function formatProvider()
	{
		return [
			'jpeg' => ['jpeg'],
			'png' => ['png'],
			'gif' => ['gif'],
			'webp' => ['webp'],
			'bmp' => ['bmp'],
		];
	}

	// ------------------------------------------------------------------
	// Loading
	// ------------------------------------------------------------------

	/**
	 * @dataProvider formatProvider
	 */
	public function testOpenReadsSizeAndMimeType($format)
	{
		$this->requireFormat($format);
		$path = $this->writeFile("in.{$format}", $this->makeImage($format, 40, 20));

		$image = new NanoImage();
		$result = $image->open($path);

		$this->assertSame($image, $result);
		$this->assertSame(40, $image->getWidth());
		$this->assertSame(20, $image->getHeight());
		$this->assertSame(image_type_to_mime_type($this->imageTypeOf($format)), $image->getMimeType());
	}

	/**
	 * @dataProvider formatProvider
	 */
	public function testLoadReadsImageDataForEachFormat($format)
	{
		$this->requireFormat($format);

		$image = new NanoImage();
		$image->load($this->makeImage($format, 30, 12));

		$this->assertSame(30, $image->getWidth());
		$this->assertSame(12, $image->getHeight());
		$this->assertSame(image_type_to_mime_type($this->imageTypeOf($format)), $image->getMimeType());
	}

	public function testConstructorAcceptsPathOrBinaryData()
	{
		$path = $this->writeFile('in.png', $this->makeImage('png', 16, 8));

		$fromPath = new NanoImage($path);
		$this->assertSame(16, $fromPath->getWidth());

		// Regression: binary data contains NUL bytes, which made is_file() fail on PHP < 8.1.
		$fromData = new NanoImage($this->makeImage('png', 18, 9));
		$this->assertSame(18, $fromData->getWidth());

		$empty = new NanoImage();
		$this->assertSame(0, $empty->getWidth());
		$this->assertSame(0, (new NanoImage(''))->getHeight());
	}

	public function testConstructorRejectsNonStringSource()
	{
		$this->expectException(\InvalidArgumentException::class);
		new NanoImage(123);
	}

	public function testOpenMissingFileThrows()
	{
		$this->expectException(UnsupportedImageException::class);
		(new NanoImage())->open($this->path('does-not-exist.png'));
	}

	public function testOpenEmptyFileNameThrows()
	{
		$this->expectException(UnsupportedImageException::class);
		(new NanoImage())->open('');
	}

	public function testOpenFileNameWithNulByteThrows()
	{
		$this->expectException(UnsupportedImageException::class);
		(new NanoImage())->open("image\0.png");
	}

	public function testOpenTextFileThrows()
	{
		$path = $this->writeFile('notes.png', 'this is not an image');

		$this->expectException(UnsupportedImageException::class);
		(new NanoImage())->open($path);
	}

	public function testOpenCorruptImageThrows()
	{
		$bytes = $this->makeImage('png', 20, 20);
		$path = $this->writeFile('corrupt.png', substr($bytes, 0, 40)); // header only

		$this->expectException(UnsupportedImageException::class);
		(new NanoImage())->open($path);
	}

	public function testUnsupportedImageFormatThrows()
	{
		// A PSD header: getimagesize() recognises it, but NanoImage cannot decode it.
		$psd = '8BPS' . pack('n', 1) . str_repeat("\0", 6) . pack('n', 3) . pack('N', 10) . pack('N', 10) . pack('n', 8) . pack('n', 3);
		$path = $this->writeFile('image.psd', $psd);

		try {
			(new NanoImage())->open($path);
			$this->fail('open() should reject a PSD file.');
		} catch (UnsupportedImageException $e) {
			$this->assertSame(1, preg_match('/Unsupported image type/', $e->getMessage()));
		}

		try {
			(new NanoImage())->load($psd);
			$this->fail('load() should reject PSD data.');
		} catch (UnsupportedImageException $e) {
			$this->assertSame(1, preg_match('/Unsupported image type/', $e->getMessage()));
		}
	}

	public function testLoadEmptyStringThrows()
	{
		$this->expectException(UnsupportedImageException::class);
		(new NanoImage())->load('');
	}

	public function testLoadGarbageThrowsWithoutLeakingPhpWarnings()
	{
		// Regression: imagecreatefromstring() warnings leaked out of load().
		$thrown = false;

		$warnings = $this->captureWarnings(function () use (&$thrown) {
			try {
				(new NanoImage())->load('definitely not an image');
			} catch (UnsupportedImageException $e) {
				$thrown = true;
			}
		});

		$this->assertTrue($thrown);
		$this->assertSame([], $warnings);
	}

	public function testOpenReplacesPreviousImageAndResetsPendingState()
	{
		$first = $this->writeFile('a.png', $this->makeImage('png', 40, 40));
		$second = $this->writeFile('b.png', $this->makeImage('png', 10, 6));

		$image = new NanoImage($first);
		$image->resize(20, 20)->filter(9999); // a pending resize and a filter GD rejects
		$image->open($second);

		$this->assertSame(10, $image->getWidth());
		$this->assertFalse($image->isResized());

		$out = $image->toString();
		$this->assertNotSame('', $out, 'Settings of the previous image must not leak into the new one.');
		$this->assertSame([10, 6], array_slice($this->size($out), 0, 2));
	}

	public function testPaletteImagesAreLoadedWithTheirColours()
	{
		$im = imagecreate(10, 10); // palette image
		imagecolorallocate($im, 255, 0, 0);
		ob_start();
		imagepng($im);
		$bytes = ob_get_clean();

		$image = new NanoImage($bytes);
		$out = $image->toString();

		$this->assertColor([255, 0, 0], $this->pixel($out, 5, 5), 2);
	}

	public function testOpenRefusesImagesThatDoNotFitInTheMemoryLimit()
	{
		$big = imagecreatetruecolor(1500, 1500);
		ob_start();
		imagepng($big);
		$bigPath = $this->writeFile('big.png', ob_get_clean());
		unset($big);
		$smallPath = $this->writeFile('small.png', $this->makeImage('png', 10, 10));

		$previous = ini_get('memory_limit');
		$limit = memory_get_usage(true) + 4 * 1048576;

		if (@ini_set('memory_limit', (string) $limit) === false) {
			$this->markTestSkipped('memory_limit cannot be changed at runtime.');
		}

		$tooBig = false;
		$smallOk = false;

		try {
			try {
				new NanoImage($bigPath);
			} catch (UnsupportedImageException $e) {
				$tooBig = true;
			}

			$smallOk = (new NanoImage($smallPath))->getWidth() === 10;
		} finally {
			ini_set('memory_limit', $previous);
		}

		$this->assertTrue($tooBig, 'A 1500x1500 image should be refused instead of exhausting memory.');
		$this->assertTrue($smallOk, 'Small images must still load under the same limit.');
	}

	public function testRemoveDeletesOnlyTheOpenedFile()
	{
		$path = $this->writeFile('victim.png', $this->makeImage('png', 10, 10));

		// Regression: after load(), remove() still deleted the file from an earlier open().
		$image = new NanoImage($path);
		$image->load($this->makeImage('png', 20, 20));
		$image->remove();
		$this->assertTrue(is_file($path), 'Data loaded from a string has no file to remove.');

		$opened = new NanoImage($path);
		$opened->remove();
		$this->assertFalse(is_file($path));
		$this->assertSame(10, $opened->getWidth(), 'The image stays in memory after remove().');
	}

	// ------------------------------------------------------------------
	// Resizing
	// ------------------------------------------------------------------

	/**
	 * @dataProvider formatProvider
	 */
	public function testResizeToExactSize($format)
	{
		$this->requireFormat($format);

		$image = new NanoImage($this->makeImage($format, 80, 60));
		$out = $image->resize(50, 30)->toString();

		$this->assertSame([50, 30], array_slice($this->size($out), 0, 2));
		$this->assertSame($this->imageTypeOf($format), $this->size($out)[2], 'Output keeps the source format.');
		$this->assertTrue($image->isResized());
	}

	public function aspectProvider()
	{
		return [
			'landscape into square box' => [800, 600, 200, 200, 200, 150],
			'wide image, tall box' => [1000, 333, 300, 333, 300, 100],
			'portrait into square box' => [600, 800, 300, 300, 225, 300],
			'enlarge' => [100, 50, 400, 400, 400, 200],
			'same ratio' => [200, 100, 100, 50, 100, 50],
			'extreme ratio never reaches zero' => [1, 1000, 10, 10, 1, 10],
			'one pixel' => [10, 10, 1, 1, 1, 1],
		];
	}

	/**
	 * @dataProvider aspectProvider
	 */
	public function testResizeWithAspectRatioFitsInsideTheBox($srcW, $srcH, $boxW, $boxH, $expectedW, $expectedH)
	{
		$image = new NanoImage($this->makeImage('png', $srcW, $srcH));
		$out = $image->resize($boxW, $boxH, true)->toString();

		$this->assertNotSame('', $out);
		$this->assertSame([$expectedW, $expectedH], array_slice($this->size($out), 0, 2));
	}

	public function testAspectRatioOutputIsStableAcrossRepeatedOutputs()
	{
		// Regression: the fitted size was fed back into the requested size, so the width
		// drifted (300 -> 297) every time the image was written.
		$image = new NanoImage($this->makeImage('png', 1000, 333));
		$image->aspectRatio(true)->setWidth(300)->setHeight(333);

		$first = $this->size($image->toString());
		$second = $this->size($image->toString());
		$third = $this->size($image->toString());

		$this->assertSame([300, 100], array_slice($first, 0, 2));
		$this->assertSame($first, $second);
		$this->assertSame($first, $third);
	}

	public function testSetWidthAndHeightWithoutRatioStretches()
	{
		$image = new NanoImage($this->makeImage('png', 40, 20));
		$out = $image->setWidth(10)->setHeight(30)->toString();

		$this->assertSame([10, 30], array_slice($this->size($out), 0, 2));
	}

	public function invalidSizeProvider()
	{
		return [
			'zero width' => [0, 10],
			'zero height' => [10, 0],
			'negative width' => [-5, 10],
			'negative height' => [10, -5],
			'both zero with ratio' => [0, 0],
		];
	}

	/**
	 * @dataProvider invalidSizeProvider
	 */
	public function testResizeRejectsNonPositiveSizes($width, $height)
	{
		// Regression: resize(0, 0, true) died with a DivisionByZeroError.
		$image = new NanoImage($this->makeImage('png'));

		foreach ([false, true] as $ratio) {
			try {
				$image->resize($width, $height, $ratio);
				$this->fail('resize() must reject sizes that are not greater than 0.');
			} catch (\InvalidArgumentException $e) {
				$this->assertTrue(true);
			}
		}
	}

	public function testSettersRejectNonPositiveSizes()
	{
		$image = new NanoImage($this->makeImage('png'));

		foreach (['setWidth', 'setHeight'] as $method) {
			try {
				$image->$method(0);
				$this->fail("{$method}(0) must throw.");
			} catch (\InvalidArgumentException $e) {
				$this->assertTrue(true);
			}
		}
	}

	public function testSeveralOutputsFromOneInstance()
	{
		// Regression: every output method destroyed the image, so the second one died with a TypeError
		// (this is exactly the usage shown in the original README).
		$image = new NanoImage($this->writeFile('src.png', $this->makeImage('png', 80, 60)));
		$image->resize(40, 30);

		$this->assertTrue($image->save($this->path('one.png')));
		$this->assertTrue($image->save($this->path('two.jpg')));
		$this->assertNotSame('', $image->toString());

		ob_start();
		$printed = $image->get();
		$bytes = ob_get_clean();
		$this->assertTrue($printed);
		$this->assertSame([40, 30], array_slice($this->size($bytes), 0, 2));

		$image->resize(20, 15);
		$this->assertTrue($image->saveAs($this->path('three.png'), NanoImage::DEFAULT, 90, NanoImage::PNG));

		$this->assertSame([40, 30], array_slice($this->size(file_get_contents($this->path('one.png'))), 0, 2));
		$this->assertSame([40, 30], array_slice($this->size(file_get_contents($this->path('two.jpg'))), 0, 2));
		$this->assertSame([20, 15], array_slice($this->size(file_get_contents($this->path('three.png'))), 0, 2));
	}

	// ------------------------------------------------------------------
	// Saving
	// ------------------------------------------------------------------

	public function extensionProvider()
	{
		return [
			'jpg' => ['jpg', 'jpeg'],
			'jpeg' => ['jpeg', 'jpeg'],
			'png' => ['png', 'png'],
			'gif' => ['gif', 'gif'],
			'webp' => ['webp', 'webp'],
			'bmp' => ['bmp', 'bmp'],
			'upper case' => ['PNG', 'png'],
		];
	}

	/**
	 * @dataProvider extensionProvider
	 */
	public function testSaveWritesTheFormatOfTheFileExtension($extension, $format)
	{
		// Regression: the encoder was chosen from the source image, so saving a PNG as ".jpg"
		// wrote PNG data into a file named .jpg.
		$this->requireFormat($format);
		$image = new NanoImage($this->makeImage('png', 40, 20));
		$target = $this->path("out.{$extension}");

		$this->assertTrue($image->save($target));

		$expectedFile = $this->path('out.' . strtolower($extension));
		$this->assertTrue(is_file($expectedFile));
		$info = getimagesize($expectedFile);
		$this->assertSame($this->imageTypeOf($format), $info[2]);
		$this->assertSame([40, 20], [$info[0], $info[1]]);
	}

	public function testSaveAsUsesTheExtensionArgument()
	{
		$image = new NanoImage($this->makeImage('png', 40, 20));

		// Regression: the default extension (jpeg) produced a .jpeg file full of PNG data.
		$this->assertTrue($image->saveAs($this->path('default.png')));
		$this->assertTrue(is_file($this->path('default.jpeg')));
		$this->assertFalse(is_file($this->path('default.png')));
		$this->assertSame(IMAGETYPE_JPEG, getimagesize($this->path('default.jpeg'))[2]);

		$this->assertTrue($image->saveAs($this->path('explicit.jpg'), NanoImage::DEFAULT, 90, NanoImage::PNG));
		$this->assertSame(IMAGETYPE_PNG, getimagesize($this->path('explicit.png'))[2]);

		$this->assertTrue($image->saveAs($this->path('keep.png'), NanoImage::DEFAULT, 90, null));
		$this->assertSame(IMAGETYPE_PNG, getimagesize($this->path('keep.png'))[2]);
	}

	public function testSaveAsReplacesTheExtensionOfThePath()
	{
		$this->requireFormat('webp');
		$image = new NanoImage($this->makeImage('png', 40, 20));

		$this->assertTrue($image->saveAs($this->path('photo.png'), NanoImage::DEFAULT, 80, NanoImage::WEBP));
		$this->assertTrue(is_file($this->path('photo.webp')));
		$this->assertFalse(is_file($this->path('photo.png')));
	}

	public function testSaveWithoutExtensionKeepsTheSourceFormat()
	{
		$image = new NanoImage($this->makeImage('png', 40, 20));

		// Regression: a path without an extension raised "Undefined index" and deprecation warnings.
		$result = null;
		$warnings = $this->captureWarnings(function () use ($image, &$result) {
			$result = $image->save($this->path('noext'));
		});

		$this->assertSame([], $warnings);
		$this->assertTrue($result);
		$this->assertTrue(is_file($this->path('noext.png')));
		$this->assertSame(IMAGETYPE_PNG, getimagesize($this->path('noext.png'))[2]);
	}

	public function testSaveRejectsUnsupportedExtensions()
	{
		$image = new NanoImage($this->makeImage('png', 40, 20));

		$this->assertFalse($image->save($this->path('out.xyz')));
		$this->assertFalse($image->save($this->path('out.tiff')));
		$this->assertFalse($image->saveAs($this->path('out.png'), NanoImage::DEFAULT, 90, 'pdf'));
		$this->assertSame([], glob($this->tmp() . DIRECTORY_SEPARATOR . '*'));
		$this->assertSame('', $image->getSavedPath());
	}

	public function testSaveCreatesMissingDirectories()
	{
		$image = new NanoImage($this->makeImage('png', 40, 20));
		$target = $this->path('a/b/c/out.png');

		$this->assertTrue($image->save($target));
		$this->assertTrue(is_file($target));
	}

	public function testSaveFailsWhenTheDirectoryCannotBeCreated()
	{
		$blocker = $this->writeFile('blocker', 'a regular file');
		$image = new NanoImage($this->makeImage('png', 40, 20));

		$this->assertFalse($image->save($blocker . DIRECTORY_SEPARATOR . 'sub' . DIRECTORY_SEPARATOR . 'out.png'));
	}

	public function testSaveOverwritesAnExistingFileAndLeavesNoTemporaryFiles()
	{
		$target = $this->writeFile('out.png', 'old contents');
		$image = new NanoImage($this->makeImage('png', 40, 20));

		$this->assertTrue($image->save($target));
		$this->assertSame([40, 20], array_slice($this->size(file_get_contents($target)), 0, 2));
		$this->assertSame(['out.png'], array_map('basename', glob($this->tmp() . DIRECTORY_SEPARATOR . '*')));
		$this->assertSame([], glob($this->tmp() . DIRECTORY_SEPARATOR . '.nanoimage-*'));
	}

	public function testFailedSaveKeepsTheExistingFile()
	{
		// Regression: the existing file was deleted before the new image had been built.
		$target = $this->writeFile('out.png', 'precious existing contents');
		$image = new NanoImage($this->makeImage('png', 40, 20));
		$image->filter(9999); // GD rejects this filter, so the save must fail

		$this->assertFalse($image->save($target));
		$this->assertSame('precious existing contents', file_get_contents($target));
		$this->assertSame([], glob($this->tmp() . DIRECTORY_SEPARATOR . '.nanoimage-*'));
	}

	public function testThumbnailNamingAppendsTheRequestedSize()
	{
		// Regression: the THUMBNAIL name was only applied when the target file already existed.
		$image = new NanoImage($this->makeImage('png', 800, 600));
		$image->resize(360, 200);

		$this->assertTrue($image->save($this->path('thumb.png'), NanoImage::THUMBNAIL));
		$this->assertTrue(is_file($this->path('thumb-360x200.png')));
		$this->assertFalse(is_file($this->path('thumb.png')));
		$this->assertSame($this->path('thumb-360x200.png'), $image->getSavedPath());

		$image->resize(116, 80);
		$this->assertTrue($image->saveAs($this->path('thumb.jpeg'), NanoImage::THUMBNAIL, 100, NanoImage::JPEG));
		$this->assertTrue(is_file($this->path('thumb-116x80.jpeg')));
	}

	public function testThumbnailNameUsesTheRequestedBoxEvenWithAspectRatio()
	{
		$image = new NanoImage($this->makeImage('png', 800, 600));
		$image->resize(200, 200, true); // output is 200x150

		$this->assertTrue($image->save($this->path('box.png'), NanoImage::THUMBNAIL));
		$this->assertTrue(is_file($this->path('box-200x200.png')));
		$this->assertSame([200, 150], array_slice($this->size(file_get_contents($this->path('box-200x200.png'))), 0, 2));
	}

	public function testThumbnailNameFallsBackToTheOutputSize()
	{
		$image = new NanoImage($this->makeImage('png', 80, 60));
		$image->setWidth(40)->setHeight(30);

		$this->assertTrue($image->save($this->path('t.png'), NanoImage::THUMBNAIL));
		$this->assertTrue(is_file($this->path('t-40x30.png')));

		$plain = new NanoImage($this->makeImage('png', 80, 60));
		$this->assertTrue($plain->save($this->path('p.png'), NanoImage::THUMBNAIL));
		$this->assertTrue(is_file($this->path('p-80x60.png')));
	}

	public function testTimestampNamingNeverOverwrites()
	{
		// Regression: the format used the month where minutes were meant, contained spaces and
		// colons, and was only applied when the target already existed.
		$image = new NanoImage($this->makeImage('png', 40, 20));

		$this->assertTrue($image->save($this->path('ts.png'), NanoImage::TIMESTAMP));
		$first = $image->getSavedPath();
		$this->assertTrue($image->save($this->path('ts.png'), NanoImage::TIMESTAMP));
		$second = $image->getSavedPath();

		$this->assertNotSame($first, $second);
		$this->assertTrue(is_file($first));
		$this->assertTrue(is_file($second));
		$this->assertFalse(is_file($this->path('ts.png')));
		$this->assertSame(1, preg_match('/^ts-\d{2}-\d{2}-\d{2}-\d{2}-\d{2}-\d{2}(-\d+)?\.png$/', basename($first)));
	}

	public function testReplaceOverwritesTheFile()
	{
		$target = $this->writeFile('replace.png', 'old');
		$image = new NanoImage($this->makeImage('png', 40, 20));

		$this->assertTrue($image->replace($target));
		$this->assertSame([40, 20], array_slice($this->size(file_get_contents($target)), 0, 2));
	}

	public function testEmptySaveLocationUsesTheDefaultFileName()
	{
		// Regression: the default path was built as "nano-imagejpeg" (missing dot).
		$method = new \ReflectionMethod(NanoImage::class, 'defaultLocation');
		$method->setAccessible(true);

		$this->assertSame('nano-image.jpeg', basename($method->invoke(new NanoImage())));
	}

	public function testGetExtensionAndSavedPath()
	{
		$image = new NanoImage($this->makeImage('png', 40, 20));
		$this->assertSame('png', $image->getExtension());
		$this->assertSame('', $image->getSavedPath());

		$image->save($this->path('x.JPG'));
		$this->assertSame('jpg', $image->getExtension());
		$this->assertSame($this->path('x.jpg'), $image->getSavedPath());
	}

	public function testOutputMethodsFailCleanlyWithoutAnImage()
	{
		$image = new NanoImage();

		$this->assertFalse($image->save($this->path('none.png')));
		$this->assertFalse($image->saveAs($this->path('none.png')));
		$this->assertFalse($image->replace($this->path('none.png')));
		$this->assertFalse($image->removeExif($this->path('none.png')));
		$this->assertSame('', $image->toString());

		ob_start();
		$this->assertFalse($image->get());
		$this->assertFalse($image->display());
		$this->assertSame('', ob_get_clean());
		$this->assertSame([], glob($this->tmp() . DIRECTORY_SEPARATOR . '*'));
	}

	public function testFreeReleasesTheImageAndResetsTheInstance()
	{
		$image = new NanoImage($this->makeImage('png', 40, 20));
		$image->resize(10, 10);
		$image->free();

		$this->assertSame(0, $image->getWidth());
		$this->assertSame(0, $image->getHeight());
		$this->assertSame('', $image->getMimeType());
		$this->assertFalse($image->isResized());
		$this->assertFalse($image->save($this->path('freed.png')));

		$image->load($this->makeImage('png', 8, 4)); // reusable after free()
		$this->assertSame(8, $image->getWidth());
	}

	// ------------------------------------------------------------------
	// Quality
	// ------------------------------------------------------------------

	public function testJpegQualityChangesTheFileSize()
	{
		$noise = imagecreatetruecolor(120, 120);

		for ($i = 0; $i < 4000; $i++) {
			imagesetpixel($noise, ($i * 7) % 120, ($i * 13) % 120, imagecolorallocate($noise, ($i * 31) % 256, ($i * 57) % 256, ($i * 91) % 256));
		}

		$image = new NanoImage($this->encodeGd($noise, 'png'));

		$this->assertTrue($image->save($this->path('low.jpg'), NanoImage::DEFAULT, 10));
		$this->assertTrue($image->save($this->path('high.jpg'), NanoImage::DEFAULT, 95));
		$this->assertLessThan(filesize($this->path('high.jpg')), filesize($this->path('low.jpg')));
	}

	public function testOutOfRangeQualityIsClampedNotRejected()
	{
		$image = new NanoImage($this->makeImage('png', 40, 20));

		$this->assertTrue($image->save($this->path('over.jpg'), NanoImage::DEFAULT, 500));
		$this->assertTrue($image->save($this->path('under.jpg'), NanoImage::DEFAULT, -20));
		$this->assertTrue($image->save($this->path('over.png'), NanoImage::DEFAULT, 500));
	}

	public function testPngQualityIsLossless()
	{
		$image = new NanoImage($this->makeImage('png', 40, 20));
		$image->save($this->path('q0.png'), NanoImage::DEFAULT, 0);
		$image->save($this->path('q100.png'), NanoImage::DEFAULT, 100);

		$this->assertSame($this->pixel(file_get_contents($this->path('q0.png')), 3, 3), $this->pixel(file_get_contents($this->path('q100.png')), 3, 3));
		$this->assertSame([255, 0, 0, 0], $this->pixel(file_get_contents($this->path('q0.png')), 3, 3));
	}

	// ------------------------------------------------------------------
	// Transparency
	// ------------------------------------------------------------------

	public function testPngTransparencyIsPreservedWithAndWithoutResizing()
	{
		// Regression: every output was painted onto a white background, losing the alpha channel.
		$source = $this->makeImage('png', 40, 20, true);

		$unchanged = (new NanoImage($source))->toString();
		$this->assertSame(127, $this->pixel($unchanged, 35, 10)[3], 'transparent area (unchanged image)');
		$this->assertSame([255, 0, 0, 0], $this->pixel($unchanged, 5, 10), 'opaque area (unchanged image)');

		$resized = (new NanoImage($source))->resize(20, 10)->toString();
		$this->assertSame(127, $this->pixel($resized, 17, 5)[3], 'transparent area (resized image)');
		$this->assertSame(0, $this->pixel($resized, 2, 5)[3], 'opaque area (resized image)');

		$this->assertTrue((new NanoImage($source))->save($this->path('alpha.png')));
		$this->assertSame(127, $this->pixel(file_get_contents($this->path('alpha.png')), 35, 10)[3]);
	}

	public function testWebpTransparencyIsPreserved()
	{
		$this->requireFormat('webp');
		$image = new NanoImage($this->makeImage('png', 40, 20, true));

		$this->assertTrue($image->resize(20, 10)->save($this->path('alpha.webp')));
		$out = file_get_contents($this->path('alpha.webp'));

		$this->assertSame(127, $this->pixel($out, 17, 5)[3]);
		$this->assertSame(0, $this->pixel($out, 2, 5)[3]);
	}

	public function testTransparencyIsFlattenedToWhiteForFormatsWithoutAlpha()
	{
		$image = new NanoImage($this->makeImage('png', 40, 20, true));

		$this->assertTrue($image->save($this->path('flat.jpg')));
		$this->assertTrue($image->save($this->path('flat.bmp')));

		foreach (['flat.jpg', 'flat.bmp'] as $name) {
			$out = file_get_contents($this->path($name));
			$this->assertColor([255, 255, 255], $this->pixel($out, 35, 10), 12);
			$this->assertColor([255, 0, 0], $this->pixel($out, 5, 10), 40);
		}
	}

	public function testGifTransparencyIsPreserved()
	{
		$this->requireFormat('gif');
		$gif = imagecreate(10, 10);
		$background = imagecolorallocate($gif, 255, 0, 255);
		imagecolortransparent($gif, $background);
		imagefilledrectangle($gif, 5, 0, 9, 9, imagecolorallocate($gif, 200, 0, 0));
		$bytes = $this->encodeGd($gif, 'gif');

		$image = new NanoImage($bytes);
		$this->assertTrue($image->save($this->path('t.gif')));
		$out = imagecreatefromgif($this->path('t.gif'));

		$this->assertTrue(imagecolortransparent($out) >= 0, 'The GIF must keep a transparent colour.');
		$this->assertSame(imagecolortransparent($out), imagecolorat($out, 1, 5), 'Background pixel is transparent.');
		$this->assertTrue(imagecolorat($out, 8, 5) !== imagecolortransparent($out), 'Opaque pixel stays opaque.');
	}

	public function testOpaqueGifKeepsAllPixelsOpaque()
	{
		$this->requireFormat('gif');
		$image = new NanoImage($this->makeImage('gif', 20, 10));

		$out = imagecreatefromstring($image->resize(10, 5)->toString());
		$this->assertSame(-1, imagecolortransparent($out));
	}

	// ------------------------------------------------------------------
	// Output methods
	// ------------------------------------------------------------------

	/**
	 * @dataProvider formatProvider
	 */
	public function testToStringReturnsAValidImageInTheSourceFormat($format)
	{
		$this->requireFormat($format);

		$out = (new NanoImage($this->makeImage($format, 32, 16)))->toString();

		$this->assertSame([32, 16, $this->imageTypeOf($format)], $this->size($out));
	}

	public function testGetWritesTheImageToTheOutputWithoutHeaders()
	{
		$image = new NanoImage($this->makeImage('png', 32, 16));

		ob_start();
		$ok = $image->get(90);
		$bytes = ob_get_clean();

		$this->assertTrue($ok);
		$this->assertSame([32, 16, IMAGETYPE_PNG], $this->size($bytes));
	}

	public function testDisplaySendsTheImage()
	{
		$image = new NanoImage($this->makeImage('png', 32, 16));

		ob_start();
		$ok = $image->display();
		$bytes = ob_get_clean();

		$this->assertTrue($ok);
		$this->assertSame([32, 16, IMAGETYPE_PNG], $this->size($bytes));
	}

	public function testFailedEncodingOutputsNothing()
	{
		$image = new NanoImage($this->makeImage('png', 32, 16));
		$image->filter(9999);

		ob_start();
		$displayed = $image->display();
		$got = $image->get();
		$output = ob_get_clean();

		$this->assertFalse($displayed);
		$this->assertFalse($got);
		$this->assertSame('', $output, 'No partial image data may be written when encoding fails.');
		$this->assertSame('', $image->toString());
	}

	public function testToStringLeavesOutputBuffersAsItFoundThem()
	{
		$image = new NanoImage($this->makeImage('png', 32, 16));
		$level = ob_get_level();

		$image->toString();
		$this->assertSame($level, ob_get_level());

		$image->filter(9999);
		$image->toString();
		$this->assertSame($level, ob_get_level());
	}

	// ------------------------------------------------------------------
	// Filters
	// ------------------------------------------------------------------

	public function testGrayscale()
	{
		$out = (new NanoImage($this->makeImage('png', 40, 20)))->grayscale()->toString();
		$pixel = $this->pixel($out, 5, 5);

		$this->assertTrue($pixel[0] === $pixel[1] && $pixel[1] === $pixel[2], 'R, G and B are equal in grayscale.');
		$this->assertTrue($pixel[0] > 0 && $pixel[0] < 255);
	}

	private function grayImage()
	{
		$im = imagecreatetruecolor(20, 10);
		imagefill($im, 0, 0, imagecolorallocate($im, 100, 100, 100));
		imagefilledrectangle($im, 10, 0, 19, 9, imagecolorallocate($im, 150, 150, 150));

		return $this->encodeGd($im, 'png');
	}

	public function testBrightness()
	{
		$brighter = (new NanoImage($this->grayImage()))->brightness(50)->toString();
		$darker = (new NanoImage($this->grayImage()))->brightness(-50)->toString();

		$this->assertColor([150, 150, 150], $this->pixel($brighter, 2, 2), 2);
		$this->assertColor([50, 50, 50], $this->pixel($darker, 2, 2), 2);
	}

	public function testContrastIncreasesWithPositiveLevels()
	{
		$more = (new NanoImage($this->grayImage()))->contrast(50)->toString();
		$less = (new NanoImage($this->grayImage()))->contrast(-50)->toString();

		$gapMore = $this->pixel($more, 17, 2)[0] - $this->pixel($more, 2, 2)[0];
		$gapLess = $this->pixel($less, 17, 2)[0] - $this->pixel($less, 2, 2)[0];

		$this->assertGreaterThan(50, $gapMore, 'The 100/150 gap widens with contrast(50).');
		$this->assertLessThan(50, $gapLess, 'The 100/150 gap narrows with contrast(-50).');
	}

	public function levelProvider()
	{
		return [
			'brightness too high' => ['brightness', 256],
			'brightness too low' => ['brightness', -256],
			'contrast too high' => ['contrast', 101],
			'contrast too low' => ['contrast', -101],
		];
	}

	/**
	 * @dataProvider levelProvider
	 */
	public function testFilterShortcutsRejectOutOfRangeLevels($method, $level)
	{
		$this->expectException(\InvalidArgumentException::class);
		(new NanoImage($this->makeImage('png')))->$method($level);
	}

	public function testFilterWithArgumentsAndFiltersList()
	{
		$out = (new NanoImage($this->grayImage()))
			->filter(IMG_FILTER_BRIGHTNESS, 30)
			->toString();
		$this->assertColor([130, 130, 130], $this->pixel($out, 2, 2), 2);

		$out = (new NanoImage($this->grayImage()))
			->filters([
				[IMG_FILTER_BRIGHTNESS, 20],
				[IMG_FILTER_COLORIZE, 50, 0, 0],
				IMG_FILTER_NEGATE,
			])
			->toString();

		// 100 + 20 = 120; red +50 = 170 / green 120 / blue 120; negated = 85 / 135 / 135.
		$this->assertColor([85, 135, 135], $this->pixel($out, 2, 2), 3);
	}

	public function testFiltersAreNotAccumulatedAcrossOutputs()
	{
		$image = (new NanoImage($this->grayImage()))->brightness(30);

		$first = $this->pixel($image->toString(), 2, 2)[0];
		$second = $this->pixel($image->toString(), 2, 2)[0];

		$this->assertSame(130, $first);
		$this->assertSame(130, $second, 'Filters run on a copy; the source must not be brightened twice.');
	}

	public function testRejectedFilterMakesTheOutputFailInsteadOfBeingSkipped()
	{
		// Regression: an unknown filter was silently ignored, and a filter missing its arguments
		// threw an ArgumentCountError out of the middle of the output.
		$unknown = new NanoImage($this->makeImage('png'));
		$unknown->filter(9999);
		$this->assertSame('', $unknown->toString());
		$this->assertFalse($unknown->save($this->path('unknown.png')));

		$missingArguments = new NanoImage($this->makeImage('png'));
		$missingArguments->filter(IMG_FILTER_COLORIZE);
		$this->assertSame('', $missingArguments->toString());
	}

	// ------------------------------------------------------------------
	// Blur
	// ------------------------------------------------------------------

	public function testBlurSoftensEdgesAndKeepsTheSize()
	{
		$image = new NanoImage($this->makeImage('png', 80, 40));
		$image->blur(3);
		$out = $image->toString();

		$this->assertSame([80, 40], array_slice($this->size($out), 0, 2));
		$left = $this->pixel($out, 38, 20);
		$right = $this->pixel($out, 41, 20);

		$this->assertLessThan(250, $left[0], 'Red bleeds across the edge.');
		$this->assertGreaterThan(5, $left[2], 'Blue bleeds across the edge.');
		$this->assertLessThan(250, $right[2]);
		$this->assertGreaterThan(5, $right[0]);
	}

	public function testBlurAfterResizeBlursTheWholeImage()
	{
		// Regression: blur() after resize() worked on a corner of the original instead of the
		// resized image, so the edge in the middle stayed perfectly sharp.
		$image = new NanoImage($this->makeImage('png', 800, 600));
		$image->resize(200, 150)->blur(3);
		$out = $image->toString();

		$this->assertSame([200, 150], array_slice($this->size($out), 0, 2));
		$left = $this->pixel($out, 98, 75);
		$right = $this->pixel($out, 101, 75);

		$this->assertGreaterThan(5, $left[2], 'The edge in the middle must be blurred.');
		$this->assertGreaterThan(5, $right[0]);
	}

	public function testBlurWorksOnTinyImages()
	{
		// Regression: images under 4 pixels wide made blur() call imagecreatetruecolor() with a zero size.
		foreach ([[1, 1], [3, 3], [2, 50], [50, 3]] as $size) {
			$image = new NanoImage($this->makeImage('png', $size[0], $size[1]));
			$image->blur(1);

			$this->assertSame($size, array_slice($this->size($image->toString()), 0, 2));
		}
	}

	public function testBlurKeepsTransparencyOfTransparentAreas()
	{
		$image = new NanoImage($this->makeImage('png', 80, 40, true));
		$out = $image->blur(1)->toString();

		$this->assertSame(127, $this->pixel($out, 75, 20)[3]);
	}

	public function testBlurWithoutImageThrows()
	{
		$this->expectException(\LogicException::class);
		(new NanoImage())->blur();
	}

	public function testApplyBlurIgnoresNonImages()
	{
		$this->assertSame('nope', (new NanoImage())->applyBlur('nope'));
	}

	// ------------------------------------------------------------------
	// Crop
	// ------------------------------------------------------------------

	public function cropProvider()
	{
		return [
			'top-left quadrant' => [0, 0, 50, 40, [255, 0, 0]],
			'top-right quadrant' => [50, 0, 50, 40, [0, 255, 0]],
			'bottom-left quadrant' => [0, 40, 50, 40, [0, 0, 255]],
			'bottom-right quadrant' => [50, 40, 50, 40, [255, 255, 0]],
			'single pixel' => [75, 60, 1, 1, [255, 255, 0]],
			'whole image' => [0, 0, 100, 80, [255, 0, 0]],
		];
	}

	/**
	 * @dataProvider cropProvider
	 */
	public function testCropReturnsTheRequestedRegion($x, $y, $width, $height, array $color)
	{
		$image = new NanoImage($this->makeQuadrants('png', 100, 80));
		$result = $image->crop($x, $y, $width, $height);

		$this->assertSame($image, $result);
		$this->assertSame($width, $image->getWidth());
		$this->assertSame($height, $image->getHeight());

		$out = $image->toString();
		$this->assertSame([$width, $height], array_slice($this->size($out), 0, 2));
		$this->assertColor($color, $this->pixel($out, 0, 0), 2);
	}

	public function cropInvalidProvider()
	{
		return [
			'negative x' => [-1, 0, 10, 10],
			'negative y' => [0, -1, 10, 10],
			'zero width' => [0, 0, 0, 10],
			'zero height' => [0, 0, 10, 0],
			'negative width' => [0, 0, -5, 10],
			'wider than the image' => [0, 0, 101, 10],
			'taller than the image' => [0, 0, 10, 81],
			'starts beyond the right edge' => [95, 0, 10, 10],
			'starts beyond the bottom edge' => [0, 75, 10, 10],
			'starts outside the image' => [200, 200, 1, 1],
		];
	}

	/**
	 * @dataProvider cropInvalidProvider
	 */
	public function testCropRejectsInvalidAreas($x, $y, $width, $height)
	{
		$image = new NanoImage($this->makeQuadrants('png', 100, 80));

		try {
			$image->crop($x, $y, $width, $height);
			$this->fail('crop() must reject this area.');
		} catch (\InvalidArgumentException $e) {
			$this->assertSame(100, $image->getWidth(), 'A rejected crop leaves the image unchanged.');
			$this->assertSame(80, $image->getHeight());
		}
	}

	public function testCropUsesTheCoordinatesOfAPendingResize()
	{
		$image = new NanoImage($this->makeQuadrants('png', 200, 160));
		$image->resize(100, 80)->crop(50, 40, 50, 40);
		$out = $image->toString();

		$this->assertSame([50, 40], array_slice($this->size($out), 0, 2));
		$this->assertColor([255, 255, 0], $this->pixel($out, 10, 10), 2);
	}

	public function testCropPreservesTransparency()
	{
		$image = new NanoImage($this->makeImage('png', 40, 20, true));
		$out = $image->crop(10, 0, 30, 20)->toString();

		$this->assertSame(0, $this->pixel($out, 2, 5)[3], 'red area stays opaque');
		$this->assertSame(127, $this->pixel($out, 25, 5)[3], 'background stays transparent');
	}

	public function testCropWithoutImageThrows()
	{
		$this->expectException(\LogicException::class);
		(new NanoImage())->crop(0, 0, 1, 1);
	}

	public function testCroppedImageCanBeSavedWithAThumbnailName()
	{
		$image = new NanoImage($this->makeQuadrants('png', 100, 80));
		$image->resize(50, 40)->crop(0, 0, 20, 20);

		$this->assertTrue($image->save($this->path('c.png'), NanoImage::THUMBNAIL));
		$this->assertTrue(is_file($this->path('c-20x20.png')), 'The name reflects the cropped size.');
	}

	// ------------------------------------------------------------------
	// Scale
	// ------------------------------------------------------------------

	public function scaleProvider()
	{
		return [
			'half' => [0.5, 50, 40],
			'double' => [2, 200, 160],
			'one and a half' => [1.5, 150, 120],
			'identity' => [1, 100, 80],
			'tiny never reaches zero' => [0.001, 1, 1],
		];
	}

	/**
	 * @dataProvider scaleProvider
	 */
	public function testScaleKeepsProportions($factor, $width, $height)
	{
		$image = new NanoImage($this->makeQuadrants('png', 100, 80));
		$out = $image->scale($factor)->toString();

		$this->assertSame($width, $image->getWidth());
		$this->assertSame([$width, $height], array_slice($this->size($out), 0, 2));
	}

	public function testScaleRejectsInvalidFactors()
	{
		$image = new NanoImage($this->makeImage('png'));

		foreach ([0, -1, -0.5, NAN, INF] as $factor) {
			try {
				$image->scale($factor);
				$this->fail('scale() must reject ' . var_export($factor, true));
			} catch (\InvalidArgumentException $e) {
				$this->assertTrue(true);
			}
		}
	}

	public function testScaleWithoutImageThrows()
	{
		$this->expectException(\LogicException::class);
		(new NanoImage())->scale(2);
	}

	// ------------------------------------------------------------------
	// Rotate
	// ------------------------------------------------------------------

	public function rotateProvider()
	{
		// angle => expected size, then the colours now at top-left, top-right, bottom-right, bottom-left.
		// Source quadrants: TL red, TR green, BR yellow, BL blue. Rotation is counter-clockwise.
		$red = [255, 0, 0];
		$green = [0, 255, 0];
		$yellow = [255, 255, 0];
		$blue = [0, 0, 255];

		return [
			'90' => [90, 80, 100, $green, $yellow, $blue, $red],
			'180' => [180, 100, 80, $yellow, $blue, $red, $green],
			'270' => [270, 80, 100, $blue, $red, $green, $yellow],
			'-90 is clockwise' => [-90, 80, 100, $blue, $red, $green, $yellow],
			'450 equals 90' => [450, 80, 100, $green, $yellow, $blue, $red],
			'360 changes nothing' => [360, 100, 80, $red, $green, $yellow, $blue],
			'0 changes nothing' => [0, 100, 80, $red, $green, $yellow, $blue],
		];
	}

	/**
	 * @dataProvider rotateProvider
	 */
	public function testRotateByQuarterTurns($angle, $width, $height, array $tl, array $tr, array $br, array $bl)
	{
		$image = new NanoImage($this->makeQuadrants('png', 100, 80));
		$out = $image->rotate($angle)->toString();

		$this->assertSame([$width, $height], array_slice($this->size($out), 0, 2));
		$this->assertSame($width, $image->getWidth());
		$this->assertColor($tl, $this->pixel($out, 2, 2), 2);
		$this->assertColor($tr, $this->pixel($out, $width - 3, 2), 2);
		$this->assertColor($br, $this->pixel($out, $width - 3, $height - 3), 2);
		$this->assertColor($bl, $this->pixel($out, 2, $height - 3), 2);
	}

	public function testRotateByAnArbitraryAngleEnlargesTheCanvasWithTransparentCorners()
	{
		$image = new NanoImage($this->makeQuadrants('png', 100, 80));
		$out = $image->rotate(45)->toString();
		$size = $this->size($out);

		// 100*cos45 + 80*sin45 = 127.3
		$this->assertTrue(abs($size[0] - 127) <= 2, "width {$size[0]}");
		$this->assertTrue(abs($size[1] - 127) <= 2, "height {$size[1]}");
		$this->assertSame(127, $this->pixel($out, 1, 1)[3], 'corner is transparent');
		$this->assertSame(0, $this->pixel($out, intdiv($size[0], 2), intdiv($size[1], 2))[3], 'centre is opaque');
	}

	public function testRotateFillsCornersWithTheBackgroundColour()
	{
		$image = new NanoImage($this->makeQuadrants('png', 100, 80));
		$out = $image->rotate(45, '#00ff00')->toString();

		$corner = $this->pixel($out, 1, 1);
		$this->assertSame(0, $corner[3]);
		$this->assertColor([0, 255, 0], $corner, 2);

		$short = (new NanoImage($this->makeQuadrants('png', 100, 80)))->rotate(45, 'f00')->toString();
		$this->assertColor([255, 0, 0], $this->pixel($short, 1, 1), 2);
	}

	public function testRotatedTransparentCornersBecomeWhiteInJpeg()
	{
		$image = new NanoImage($this->makeQuadrants('png', 100, 80));
		$image->rotate(45);
		$this->assertTrue($image->save($this->path('rot.jpg')));

		$this->assertColor([255, 255, 255], $this->pixel(file_get_contents($this->path('rot.jpg')), 1, 1), 12);
	}

	public function testRotatePreservesExistingTransparency()
	{
		$image = new NanoImage($this->makeImage('png', 40, 20, true));
		$out = $image->rotate(90)->toString();

		$this->assertSame([20, 40], array_slice($this->size($out), 0, 2));
		$this->assertSame(0, $this->pixel($out, 2, 37)[3], 'red area (now bottom-left) stays opaque');
		$this->assertSame(127, $this->pixel($out, 2, 2)[3], 'background (now top) stays transparent');
	}

	public function testRotateAfterResizeRotatesTheResizedImage()
	{
		$image = new NanoImage($this->makeQuadrants('png', 100, 80));
		$out = $image->resize(200, 100)->rotate(90)->toString();

		$this->assertSame([100, 200], array_slice($this->size($out), 0, 2));
	}

	public function testRotateRejectsInvalidArguments()
	{
		$image = new NanoImage($this->makeImage('png'));

		foreach ([NAN, INF, -INF] as $angle) {
			try {
				$image->rotate($angle);
				$this->fail('rotate() must reject a non-finite angle.');
			} catch (\InvalidArgumentException $e) {
				$this->assertTrue(true);
			}
		}

		foreach (['red', '#12', '#1234567', 'gg0000', ''] as $background) {
			try {
				$image->rotate(45, $background);
				$this->fail("rotate() must reject the background '{$background}'.");
			} catch (\InvalidArgumentException $e) {
				$this->assertTrue(true);
			}
		}

		$this->assertSame(40, $image->getWidth(), 'Rejected calls leave the image unchanged.');
	}

	public function testRotateWithoutImageThrows()
	{
		$this->expectException(\LogicException::class);
		(new NanoImage())->rotate(90);
	}

	// ------------------------------------------------------------------
	// Flip
	// ------------------------------------------------------------------

	public function flipProvider()
	{
		$red = [255, 0, 0];
		$green = [0, 255, 0];
		$yellow = [255, 255, 0];
		$blue = [0, 0, 255];

		// mode => colours at top-left, top-right, bottom-right, bottom-left.
		return [
			'horizontal' => [NanoImage::FLIP_HORIZONTAL, $green, $red, $blue, $yellow],
			'vertical' => [NanoImage::FLIP_VERTICAL, $blue, $yellow, $green, $red],
			'both' => [NanoImage::FLIP_BOTH, $yellow, $blue, $red, $green],
		];
	}

	/**
	 * @dataProvider flipProvider
	 */
	public function testFlipMirrorsThePixels($mode, array $tl, array $tr, array $br, array $bl)
	{
		$image = new NanoImage($this->makeQuadrants('png', 100, 80));
		$out = $image->flip($mode)->toString();

		$this->assertSame([100, 80], array_slice($this->size($out), 0, 2));
		$this->assertColor($tl, $this->pixel($out, 2, 2), 2);
		$this->assertColor($tr, $this->pixel($out, 97, 2), 2);
		$this->assertColor($br, $this->pixel($out, 97, 77), 2);
		$this->assertColor($bl, $this->pixel($out, 2, 77), 2);
	}

	public function testFlipDefaultsToHorizontalAndTwiceRestoresTheImage()
	{
		$image = new NanoImage($this->makeQuadrants('png', 100, 80));
		$image->flip();
		$this->assertColor([0, 255, 0], $this->pixel($image->toString(), 2, 2), 2);

		$image->flip(NanoImage::FLIP_HORIZONTAL);
		$this->assertColor([255, 0, 0], $this->pixel($image->toString(), 2, 2), 2);
	}

	public function testFlipDoesNotDisturbAPendingResize()
	{
		$image = new NanoImage($this->makeQuadrants('png', 100, 80));
		$out = $image->resize(50, 40)->flip(NanoImage::FLIP_VERTICAL)->toString();

		$this->assertSame([50, 40], array_slice($this->size($out), 0, 2));
		$this->assertColor([0, 0, 255], $this->pixel($out, 2, 2), 2);
	}

	public function testFlipPreservesTransparency()
	{
		$image = new NanoImage($this->makeImage('png', 40, 20, true));
		$out = $image->flip(NanoImage::FLIP_HORIZONTAL)->toString();

		$this->assertSame(0, $this->pixel($out, 37, 10)[3], 'red area moved to the right edge');
		$this->assertSame(127, $this->pixel($out, 2, 10)[3]);
	}

	public function testFlipRejectsUnknownModes()
	{
		$image = new NanoImage($this->makeImage('png'));

		foreach ([0, 4, -1, 99] as $mode) {
			try {
				$image->flip($mode);
				$this->fail("flip({$mode}) must throw.");
			} catch (\InvalidArgumentException $e) {
				$this->assertTrue(true);
			}
		}
	}

	public function testFlipWithoutImageThrows()
	{
		$this->expectException(\LogicException::class);
		(new NanoImage())->flip();
	}

	// ------------------------------------------------------------------
	// Combining operations
	// ------------------------------------------------------------------

	public function testOperationsRunInTheOrderTheyAreCalled()
	{
		$image = new NanoImage($this->makeQuadrants('png', 200, 160));
		$image->resize(100, 80)->crop(0, 0, 50, 40)->rotate(90)->flip(NanoImage::FLIP_HORIZONTAL)->scale(2);

		$out = $image->toString();
		$this->assertSame([80, 100], array_slice($this->size($out), 0, 2));
		// Cropped top-left quadrant is solid red; rotation and flips do not change that.
		$this->assertColor([255, 0, 0], $this->pixel($out, 40, 50), 2);
	}

	public function testChainedMethodsReturnTheSameInstance()
	{
		$image = new NanoImage($this->makeImage('png', 40, 20));

		$this->assertSame($image, $image->resize(20, 10));
		$this->assertSame($image, $image->aspectRatio(false));
		$this->assertSame($image, $image->setWidth(10));
		$this->assertSame($image, $image->setHeight(10));
		$this->assertSame($image, $image->grayscale());
		$this->assertSame($image, $image->brightness(1));
		$this->assertSame($image, $image->contrast(1));
		$this->assertSame($image, $image->filter(IMG_FILTER_NEGATE));
		$this->assertSame($image, $image->filters([]));
		$this->assertSame($image, $image->remove());
	}

	// ------------------------------------------------------------------
	// EXIF
	// ------------------------------------------------------------------

	public function testRemoveExifWritesAnImageWithoutMetadata()
	{
		$jpeg = $this->makeImage('jpeg', 40, 20);
		$segment = "Exif\0\0" . "MM\0*\0\0\0\x08\0\0\0\0\0\0";
		$withExif = substr($jpeg, 0, 2) . "\xFF\xE1" . pack('n', strlen($segment) + 2) . $segment . substr($jpeg, 2);
		$path = $this->writeFile('photo.jpg', $withExif);

		$this->assertTrue(strpos(file_get_contents($path), "Exif\0\0") !== false, 'Test file contains EXIF data.');

		$image = new NanoImage($path);
		$this->assertTrue($image->removeExif($this->path('clean.jpg')));

		$clean = file_get_contents($this->path('clean.jpg'));
		$this->assertFalse(strpos($clean, "Exif\0\0"));
		$this->assertSame([40, 20], array_slice($this->size($clean), 0, 2));
	}

	public function testRemoveExifAppliesPendingResize()
	{
		$image = new NanoImage($this->makeImage('jpeg', 40, 20));
		$this->assertTrue($image->resize(20, 10)->removeExif($this->path('small.jpg')));

		$this->assertSame([20, 10], array_slice($this->size(file_get_contents($this->path('small.jpg'))), 0, 2));
	}

	public function testExifWritingReturnsFalseWhenNoWriterExists()
	{
		if (function_exists('exif_write_data')) {
			$this->markTestSkipped('An exif_write_data() implementation is installed; its behaviour is not covered here.');
		}

		$path = $this->writeFile('photo.jpg', $this->makeImage('jpeg', 40, 20));
		$image = new NanoImage($path);

		$this->assertFalse($image->setExif($this->path('out.jpg'), ['Artist' => 'Someone']));
		$this->assertFalse($image->addExif($this->path('out2.jpg'), ['Artist' => 'Someone']));
		$this->assertFalse(is_file($this->path('out.jpg')), 'Nothing is saved when EXIF cannot be written.');
		$this->assertFalse(is_file($this->path('out2.jpg')));
	}

	public function testAddExifWithoutSourceFileReturnsFalse()
	{
		$image = new NanoImage($this->makeImage('jpeg', 40, 20)); // loaded from data: no file
		$warnings = $this->captureWarnings(function () use ($image, &$result) {
			$result = $image->addExif($this->path('x.jpg'));
		});

		$this->assertSame([], $warnings);
		$this->assertFalse($result);
	}

	// ------------------------------------------------------------------
	// image_bmp() fallback encoder
	// ------------------------------------------------------------------

	private function parseBmp($bytes)
	{
		$header = unpack('a2magic/Vsize/v2reserved/Voffset/VheaderSize/lwidth/lheight/vplanes/vbpp/Vcompression/VdataSize', $bytes);
		$rowSize = (int) ((($header['width'] * 3) + 3) & ~3);
		$rows = [];

		for ($i = 0; $i < abs($header['height']); $i++) {
			$rows[] = substr($bytes, $header['offset'] + $i * $rowSize, $header['width'] * 3);
		}

		$header['rowSize'] = $rowSize;
		$header['rows'] = $rows; // file order: bottom row first when height is positive

		return $header;
	}

	public function bmpWidthProvider()
	{
		return ['1' => [1], '2' => [2], '3' => [3], '4' => [4], '5' => [5], '7' => [7], '8' => [8]];
	}

	/**
	 * @dataProvider bmpWidthProvider
	 */
	public function testImageBmpWritesAValidUncompressedBmp($width)
	{
		// Regression: the header declared RLE compression for uncompressed data and stored rows
		// top-down under a bottom-up header, so no program could read the files.
		$im = imagecreatetruecolor($width, 3);
		imagefill($im, 0, 0, imagecolorallocate($im, 0, 0, 255));
		imagefilledrectangle($im, 0, 0, $width - 1, 0, imagecolorallocate($im, 255, 0, 0)); // top row red
		$target = $this->path("fallback{$width}.bmp");

		$this->assertTrue(NanoImage::image_bmp($im, $target));

		$bytes = file_get_contents($target);
		$bmp = $this->parseBmp($bytes);
		$this->assertSame('BM', $bmp['magic']);
		$this->assertSame(strlen($bytes), $bmp['size']);
		$this->assertSame(54 + $bmp['rowSize'] * 3, strlen($bytes), 'Rows are padded to 4 bytes.');
		$this->assertSame(0, $bmp['compression'], 'BI_RGB: no compression.');
		$this->assertSame(24, $bmp['bpp']);
		$this->assertSame($width, $bmp['width']);
		$this->assertSame(3, $bmp['height'], 'Positive height: rows are stored bottom-up.');

		// Bottom-up: the first stored row is the bottom (blue) row, the last is the top (red) row. Pixels are B, G, R.
		$this->assertSame("\xFF\x00\x00", substr($bmp['rows'][0], 0, 3));
		$this->assertSame("\x00\x00\xFF", substr($bmp['rows'][2], 0, 3));

		$info = getimagesize($target);
		$this->assertSame([$width, 3, IMAGETYPE_BMP], [$info[0], $info[1], $info[2]]);

		if (function_exists('imagecreatefrombmp')) {
			$back = imagecreatefrombmp($target);
			$this->assertTrue($back !== false, 'GD can decode the file.');
			$this->assertSame(0xFF0000, imagecolorat($back, 0, 0) & 0xFFFFFF, 'Top-left pixel is red.');
			$this->assertSame(0x0000FF, imagecolorat($back, 0, 2) & 0xFFFFFF, 'Bottom-left pixel is blue.');
		}
	}

	public function testImageBmpScalesWithTheQualityArgument()
	{
		$im = imagecreatetruecolor(40, 20);
		$target = $this->path('scaled.bmp');

		$this->assertTrue(NanoImage::image_bmp($im, $target, true, 50));
		$info = getimagesize($target);
		$this->assertSame([20, 10], [$info[0], $info[1]]);
	}

	public function testImageBmpRejectsInvalidInput()
	{
		$im = imagecreatetruecolor(4, 4);

		$this->assertFalse(NanoImage::image_bmp($im, $this->path('a.bmp'), true, 0));
		$this->assertFalse(NanoImage::image_bmp($im, $this->path('b.bmp'), true, 101));
		$this->assertFalse(NanoImage::image_bmp('not an image', $this->path('c.bmp')));
		$this->assertFalse(NanoImage::image_bmp(null, $this->path('d.bmp')));
		$this->assertFalse(@NanoImage::image_bmp($im, $this->path('missing-dir/e.bmp')));
		$this->assertSame([], glob($this->tmp() . DIRECTORY_SEPARATOR . '*'));
	}

	public function testImageBmpCanWriteToTheOutput()
	{
		$im = imagecreatetruecolor(3, 2);

		ob_start();
		$ok = NanoImage::image_bmp($im);
		$bytes = ob_get_clean();

		$this->assertTrue($ok);
		$this->assertSame('BM', substr($bytes, 0, 2));
		$this->assertSame(54 + 12 * 2, strlen($bytes));
	}

	public function testImageBmpAcceptsPaletteImages()
	{
		$im = imagecreate(5, 2);
		imagecolorallocate($im, 255, 0, 0);
		$target = $this->path('palette.bmp');

		$this->assertTrue(NanoImage::image_bmp($im, $target));
		$bmp = $this->parseBmp(file_get_contents($target));
		$this->assertSame("\x00\x00\xFF", substr($bmp['rows'][0], 0, 3), 'Red, stored as B, G, R.');
	}
}
