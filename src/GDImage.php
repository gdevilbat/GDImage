<?php
/**
 * GDImage class
 *
 * PHP version 5
 *
 * @category GDImage
 * @package  GDImage\GDImage
 * @author   Dmitry Elfimov <elfimov@gmail.com>
 * @license  MIT License
 * @link     https://github.com/delfimov/GDImage/
 */

/**
 * Example:
 *
    // Easy to use image manipulation tool based on PHP-GD extension
    include 'GDImage.php';

    $logo = new GDImage('logo.png');
    $logo->opacity(30)->save('newlogo.png');

    $image = new GDImage('test.jpg');
    $image->resize(300, 200, true)
        ->merge($logo, 'right', 'bottom')
        ->save('new.jpg');
 */

namespace DElfimov\GDImage;

/**
 * Class GDImage
 *
 * @category GDImage
 * @package  DElfimov\GDImage
 * @author   Dmitry Elfimov <elfimov@gmail.com>
 * @license  MIT License
 * @link     https://github.com/delfimov/GDImage/
 */
class GDImage
{

    /**
     * Default memory limit
     * Default PHP memory_limit=128Mb
     *
     * @var int
     */
    public $memoryLimit = 134217728;

    /**
     * RGB fill color. Default is black #000
     *
     * @var array
     */
    public $fillColor = [0, 0, 0];

    /**
     * JPEG quality for output file, 0-100, where 100 is best quality
     *
     * @var int
     */
    public $jpgQuality = 80;

    /**
     * PNG compression, 0-9, from 0 (no compression) to 9
     *
     * @var int
     */
    public $pngQuality = 0;

    /**
     * An image resource, returned by one of the image creation functions
     *
     * @var resource
     */
    public $image;

    /**
     * Alphablending
     *
     * @var bool
     */
    protected $alphablending = false;

    /**
     * Save alpha from source
     *
     * @var bool
     */
    protected $savealpha = false;

    /**
     * Source image type
     *
     * @var null|string
     */
    protected $type = null;

    /**
     * Source image width
     *
     * @var null|int|double
     */
    public $width = null;

    /**
     * Source image height
     *
     * @var null|int|double
     */
    public $height = null;

    /**
     * Source image file name
     *
     * @var null|string
     */
    public $src = null;

    /**
     * Default text settings
     *
     * @var array
     */
    public $textOptions = [
        'size' => 20,
        'angle' => 0,
        'x' => 0,
        'y' => 0,
        'color' => [0, 0, 0],
        'font' => '/../fonts/Roboto-Medium.ttf'
    ];

    /**
     * Constructor
     *
     * @param string $fileName Source image file name
     *
     * @throws \Exception
     */
    public function __construct($fileName)
    {
        if (!function_exists('imagecreatefrompng')) {
            throw new \Exception('GD is not available');
        }
        $this->textOptions['font'] = __DIR__ . $this->textOptions['font'];
        $this->setMemoryLimit();
        $this->getImage($fileName);
    }


    /**
     * Set real memory limit
     *
     */
    private function setMemoryLimit()
    {
        $memLimit = ini_get('memory_limit');
        if (!empty($memLimit)) {
            $letter = strtoupper(substr($memLimit, -1));
            $number = intval(substr($memLimit, 0, -1));
            switch ($letter) {
                case "K":
                    $memLimit = $number * 1024;
                    break;
                case "M":
                    $memLimit = $number * pow(1024, 2);
                    break;
                case "G":
                    $memLimit = $number * pow(1024, 3);
                    break;
                default:
                    break;
            }
            $memLimit = intval($memLimit);
            if (!empty($memLimit)) {
                $this->memoryLimit = $memLimit;
            }
        }
    }


    /**
     * Create image from source
     *
     * @param string $fileName Source image file name
     *
     * @return $this|bool
     * @throws \Exception
     */
    public function getImage($fileName)
    {
        if (!file_exists($fileName)) {
            throw new \Exception('Image ' . $fileName . ' is not exists');
        }

        $info = getimagesize($fileName);

        $channels = empty($info['channels']) ? 3 : $info['channels'];
        $bits = empty($info['bits']) ? 8 : $info['bits'];

        if ($this->memoryLimit > 0
            && ($info[0] * $info[1] * ($channels * $bits / 8)) > $this->memoryLimit
        ) {
            throw new \Exception(
                'Image is larger then memory limit ' . $this->memoryLimit
            );
        }

        $this->width = $info[0];
        $this->height = $info[1];
        $this->src = $fileName;

        // create new image from fileName
        switch ($info[2]) {
            case IMAGETYPE_GIF:
                $this->type = 'gif';
                $this->image = imagecreatefromgif($fileName);
                break;
            case IMAGETYPE_JPEG:
                $this->type = 'jpg';
                $this->image = imagecreatefromjpeg($fileName);
                break;
            case IMAGETYPE_PNG:
                $this->type = 'png';
                $this->image = imagecreatefrompng($fileName);
                break;
            case IMAGETYPE_WEBP:
                $this->type = 'png';
                $this->image = imagecreatefromwebp($fileName);
                break;
            default:
                throw new \Exception('Supported formats are gif, jpg, png & webp only');
                break;
        }

        if (empty($this->image) || get_resource_type($this->image) != 'gd') {
            throw new \Exception('Error while reading file ' . $this->src);
        }

        imagepalettetotruecolor($this->image);
        imagealphablending($this->image, true);
        imagesavealpha($this->image, true);

        if ($this->type == 'jpg') {
            $this->imageExifOrientation();
        }

        return $this;
    }


    /**
     * Rotate image if PHP Exif extension is avialable.
     * Exif Orientation (source: http://www.exif.org/Exif2-2.PDF)
     * 1 = The 0th row is at the visual top of the image, and the 0th column is the visual left-hand side.
     * 2 = The 0th row is at the visual top of the image, and the 0th column is the visual right-hand side.
     * 3 = The 0th row is at the visual bottom of the image, and the 0th column is the visual right-hand side.
     * 4 = The 0th row is at the visual bottom of the image, and the 0th column is the visual left-hand side.
     * 5 = The 0th row is the visual left-hand side of the image, and the 0th column is the visual top.
     * 6 = The 0th row is the visual right-hand side of the image, and the 0th column is the visual top.
     * 7 = The 0th row is the visual right-hand side of the image, and the 0th column is the visual bottom.
     * 8 = The 0th row is the visual left-hand side of the image, and the 0th column is the visual bottom.
     *
     * @return null
     */
    private function imageExifOrientation()
    {
        if (function_exists('exif_read_data')) {
            try {
                $exif = exif_read_data($this->src);
            } catch (\Exception $e) {
                $exif = false;
            }
            if (!empty($exif) && !empty($exif['Orientation'])) {
                if ($exif['Orientation'] == 6) {
                    $this->rotate(270);
                }
                if ($exif['Orientation'] == 5 || $exif['Orientation'] == 7 || $exif['Orientation'] == 8) {
                    $this->rotate(90);
                }
                if ($exif['Orientation'] == 2 || $exif['Orientation'] == 3 || $exif['Orientation'] == 7) {
                    $this->flipHorizontal();
                }
                if ($exif['Orientation'] == 3 || $exif['Orientation'] == 4 || $exif['Orientation'] == 5) {
                    $this->flipVertical();
                }
            }
        }
        return null;
    }

    /**
     * Flips an image horizontally
     *
     * @return $this
     */
    public function flipHorizontal()
    {
        imageflip($this->image, IMG_FLIP_HORIZONTAL);
        return $this;
    }

    /**
     * Flips an image vertically
     *
     * @return $this
     */
    public function flipVertical()
    {
        imageflip($this->image, IMG_FLIP_VERTICAL);
        return $this;
    }

    /**
     * Rotate image
     *
     * @param float|int $angle             Rotation angle, in degrees
     * @param null      $bgColor           Specifies the color of the uncovered zone after the rotation
     *                                     (see imagecolorallocate())
     * @param int       $ignoreTransparent If set and non-zero, transparent colors are ignored
     *
     * @return $this
     */
    public function rotate($angle, $bgColor = null, $ignoreTransparent = 0)
    {
        $this->alpha();
        $this->image = imagerotate($this->image, $angle, $bgColor, $ignoreTransparent);
        $this->alpha();
        $this->width = imagesx($this->image);
        $this->height = imagesy($this->image);
        return $this;
    }

    /**
     * Crop image
     *
     * @param int $x1 crop start X position
     * @param int $y1 crop start Y position
     * @param int $x2 crop end X position
     * @param int $y2 crop end Y position
     *
     * @return $this|bool
     */
    public function crop($x1, $y1, $x2, $y2)
    {
        if ($x1 > $x2) {
            $temp = $x1;
            $x1 = $x2;
            $x2 = $temp;
        }

        if ($y1 > $y2) {
            $temp = $y1;
            $y1 = $y2;
            $y2 = $temp;
        }

        $width = $x2 - $x1;
        $height = $y2 - $y1;

        $image = imagecreatetruecolor($width, $height);

        $this->alpha($this->alphablending, $this->savealpha);

        imagecopy($image, $this->image, 0, 0, $x1, $y1, $width, $height);

        $this->image = $image;
        $this->width = $width;
        $this->height = $height;

        return $this;
    }

    /**
     * Resize image
     *
     * @param int  $width        new width
     * @param int  $height       new height
     * @param bool $crop         if true - crop image, otherwise - cover
     * @param bool $proportional stretch with source aspect ratio or not
     *
     * @return $this
     */
    public function resize($width, $height, $crop = false, $proportional = true)
    {
        if ($this->width > $width && $this->height == $height && $crop) {
            $x = round(($this->width - $width) / 2);
            $this->crop($x, 0, $x + $width, $height);
        } elseif ($this->height > $height && $this->width == $width && $crop) {
            $y = round(($this->height - $height) / 2);
            $this->crop(0, $y, $width, $height + $y);
        } elseif ($width > $this->width && $this->height == $height && !$crop) {
            $this->createCopyImage(round(($width - $this->width) / 2), 0, $width, $height);
        } elseif ($height > $this->height && $this->width == $width && !$crop) {
            $this->createCopyImage(0, round(($height - $this->height) / 2), $width, $height);
        } elseif ($width != $this->width || $this->height != $height) {
            $this->createCopyResampledImage($width, $height, $crop, $proportional);
        }
        return $this;
    }

    /**
     * Create fill and copy resampled image. Helper for resize() method.
     *
     * @param int  $width        new width
     * @param int  $height       new height
     * @param bool $crop         if true - crop image, otherwise - cover
     * @param bool $proportional stretch with source aspect ratio or not
     */
    private function createCopyResampledImage($width, $height, $crop, $proportional)
    {
        $srcX = 0;
        $srcY = 0;
        $dstX = 0;
        $dstY = 0;
        $oldRatio = round($this->width / $this->height, 2);
        $newRatio = round($width / $height, 2);
        $srcHeight = $this->height;
        $srcWidth = $this->width;
        $dstHeight = $height;
        $dstWidth = $width;
        if ($proportional) {
            if ($oldRatio > $newRatio) { // album to book
                if ($crop) {
                    $hr = $this->height * $newRatio;
                    $srcWidth = round($hr);
                    $srcX = round(($this->width - $hr) / 2);
                } else {
                    $dstHeight = round($width / $oldRatio);
                    $dstY = round(($height - $dstHeight) / 2);
                }
            } elseif ($oldRatio < $newRatio) { // book to album
                if ($crop) {
                    $wr = $this->width / $newRatio;
                    $srcY = round(($this->height - $wr) / 2);
                    $srcHeight = round($wr);
                } else {
                    $dstWidth = round($height * $oldRatio);
                    $dstX = round(($width - $dstWidth) / 2);
                }
            }
        }
        $newImage = imagecreatetruecolor($width, $height);
        $this->setAlpha($newImage, $this->alphablending, $this->savealpha);
        if ($this->alphablending) {
            $color = imagecolorallocatealpha($newImage, 0, 0, 0, 127);
            imagefill($newImage, 0, 0, $color);
        } elseif ($dstY > 0 || $dstX > 0) {
            $color = imagecolorallocate($newImage, $this->fillColor[0], $this->fillColor[1], $this->fillColor[2]);
            imagefill($newImage, 0, 0, $color);
        }
        imagecopyresampled(
            $newImage,
            $this->image,
            $dstX,
            $dstY,
            $srcX,
            $srcY,
            $dstWidth,
            $dstHeight,
            $srcWidth,
            $srcHeight
        );
        $this->image = $newImage;
        $this->width = $width;
        $this->height = $height;
    }

    /**
     * Create fill and copy image. Helper for resize() method.
     *
     * @param int $x      X coordinates for copy
     * @param int $y      Y coordinates for copy
     * @param int $width  Source width
     * @param int $height Source height
     */
    private function createCopyImage($x, $y, $width, $height)
    {
        $newImage = imagecreatetruecolor($width, $height);
        if (!$this->alphablending) {
            imagefill(
                $newImage,
                0,
                0,
                imagecolorallocate(
                    $newImage,
                    $this->fillColor[0],
                    $this->fillColor[1],
                    $this->fillColor[2]
                )
            );
        }
        imagecopy($newImage, $this->image, $x, $y, 0, 0, $this->width, $this->height);
        $this->image = $newImage;
        $this->width = $width;
    }

    /**
     * Merge image with overlay
     *
     * @param GDImage|string $overlay image
     * @param int            $posX    position X
     * @param int            $posY    position Y
     *
     * @throws \Exception
     * @return $this|bool
     */
    public function merge($overlay, $posX, $posY)
    {
        if (empty($this->image)) {
            return false;
        }

        if (is_string($overlay)) {
            try {
                $overlay = new GDImage($overlay);
            } catch (\Exception $e) {
                throw new \Exception('Overlay error: ' . $e->getMessage());
            }
        }

        if (!is_int($posX)) {
            if ($posX == 'left') {
                $posX = 0;
            } elseif ($posX == 'right') {
                $posX = $this->width - $overlay->width;
            } elseif ($posX == 'center') {
                $posX = round(($this->width - $overlay->width) / 2);
            }
        }

        if (!is_int($posY)) {
            if ($posY == 'top') {
                $posY = 0;
            } elseif ($posY == 'bottom') {
                $posY = $this->height - $overlay->height;
            } elseif ($posY == 'center') {
                $posY = round(($this->height - $overlay->height) / 2);
            }
        }

        $this->alpha(true, false);

        imagecopy(
            $this->image,
            $overlay->image,
            $posX,
            $posY,
            0,
            0,
            $overlay->width,
            $overlay->height
        );

        return $this;
    }


    /**
     * Set image opactity
     *
     * @param int $opacity from 0 to 100
     *
     * @return $this|bool
     */
    public function opacity($opacity)
    {
        if (empty($this->image)) {
            return false;
        }

        $this->alpha();

        $opacity /= 100;
        for ($x = 0; $x < $this->width; $x++) {
            for ($y = 0; $y < $this->height; $y++) {
                $color = imagecolorat($this->image, $x, $y);
                $alpha = ($color >> 24) & 0xFF;
                $alpha = 127 + $opacity * ($alpha - 127);
                $colorWithAlpha = imagecolorallocatealpha(
                    $this->image,
                    ($color >> 16) & 0xFF,
                    ($color >> 8) & 0xFF,
                    $color & 0xFF,
                    $alpha
                );
                // get the new color with alpha and set it.
                imagesetpixel(
                    $this->image,
                    $x,
                    $y,
                    $colorWithAlpha
                ); // set new pixel
            }
        }
        return $this;
    }


    /**
     * Set alpha channel properties for image
     *
     * @param bool $alphablending blending
     * @param bool $savealpha     alpha flag
     *
     * @return $this
     */
    public function alpha($alphablending = false, $savealpha = true)
    {
        $this->alphablending = $alphablending;
        $this->savealpha = $savealpha;
        $this->setAlpha($this->image, $alphablending, $savealpha);
        return $this;
    }

    /**
     * Set alpha channel properties for image
     *
     * @param resource $image         GD library resource
     * @param bool     $alphablending blending
     * @param bool     $savealpha     alpha flag
     *
     * @return bool
     */
    protected function setAlpha($image, $alphablending, $savealpha)
    {
        imagealphablending($image, $alphablending);
        imagesavealpha($image, $savealpha);
        return true;
    }


    /**
     * Save image
     *
     * @param string|resource $to     The path or an open stream resource
     *                                (which is automatically being closed
     *                                after this function returns) to save
     *                                the file to. If not set or NULL, the raw
     *                                image stream will be outputted directly.
     * @param null|string     $format new image format, if not set,
     *                                will be detected from file name
     *
     * @return bool|GDImage
     */
    public function save($to, $format = null)
    {
        if (empty($this->image)) {
            return false;
        }

        if (empty($format)) {
            $dotPos = strrpos($to, '.');
            $format = strtolower($dotPos < 1 ? '' : substr($to, $dotPos + 1));
        }

        switch ($format) {
            case 'webp':
                $result = imagewebp($this->image, $to);
                break;
            case 'gif':
                $result = imagegif($this->image, $to);
                break;
            case 'png8':
            case 'png24':
            case 'png':
                $result = imagepng($this->image, $to, $this->pngQuality);
                break;
            case 'jpeg':
            case 'jpg':
            default:
                $result = imagejpeg($this->image, $to, $this->jpgQuality);
                break;
        }
        return empty($result) ? false : $this;
    }


    /**
     * Set background fill color
     *
     * @param array $color RGB array [r,g,b]
     *
     * @return bool
     */
    public function setFillColor(array $color)
    {
        $this->fillColor = $color;
        return true;
    }


    /**
     * Get image type
     *
     * @return null|string
     */
    public function getImageType()
    {
        return $this->type;
    }

    /**
     * @return bool
     */
    public function isAnimated()
    {
        if ($this->type != 'gif') {
            return false;
        }
        $fileContents = file_get_contents($this->src);
        $filePosition = 0;
        $count = 0;
        // no need to continue when we find the second frame
        while ($count < 2) {
            $where1 = strpos($fileContents, "\x00\x21\xF9\x04", $filePosition);
            if ($where1 === false) {
                break;
            } else {
                $filePosition = $where1 + 1;
                $where2 = strpos($fileContents, "\x00\x2C", $filePosition);
                if ($where2 === false) {
                    break;
                } else {
                    if ($where1 + 8 == $where2) {
                        $count++;
                    }
                    $filePosition = $where2 + 1;
                }
            }
        }
        return $count > 1;
    }

    /**
     * @param string $text
     * @param array $options
     *
     * @return $this
     */
    public function addText($text, array $options = [])
    {
        $options = array_merge($this->textOptions, $options);
        imagettftext(
            $this->image,
            $options['size'],
            $options['angle'],
            $options['x'],
            $options['y'],
            imagecolorallocate($this->image, $options['color'][0], $options['color'][1], $options['color'][2]),
            $options['font'],
            $text
        );
        return $this;
    }

    /**
     * @param string $text
     * @param array $options
     *
     * @return array|bool width and height or false on fail
     * @throws \Exception
     */
    public function getTextSize($text, array $options = [])
    {
        $options = array_merge($this->textOptions, $options);
        $space = imagettfbbox(
            $options['size'],
            $options['angle'],
            $options['font'],
            $text
        );
        if (empty($space)) {
            throw new \Exception('getTextSize error');
        }
        return [
            'width'  => abs($space[4] - $space[0]),
            'height' => abs($space[5] - $space[1]),
        ];
    }

    /**
     * @param $x1
     * @param $y1
     * @param $x2
     * @param $y2
     * @param array $color rgb fill color
     *
     * @return $this
     */
    public function addRectangle($x1, $y1, $x2, $y2, array $color)
    {
        imagefilledrectangle(
            $this->image,
            $x1,
            $y1,
            $x2,
            $y2,
            imagecolorallocate($this->image, $color[0], $color[1], $color[2])
        );
        return $this;
    }
}
