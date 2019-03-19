<?php
/**
 * This file is part of escpos-php: PHP receipt printer library for use with
 * ESC/POS-compatible thermal and impact printers.
 *
 * Copyright (c) 2014-16 Michael Billington < michael.billington@gmail.com >,
 * incorporating modifications by others. See CONTRIBUTORS.md for a full list.
 *
 * This software is distributed under the terms of the MIT license. See LICENSE.md
 * for details.
 */
namespace LoKingWei\Tspl\PrintImages;

use Exception;
use Imagick;

/**
 * Implementation of EscposImage using the Imagick PHP plugin.
 */
class ImagickTsplImage extends TsplImage
{
    /**
     * Load actual image pixels from Imagick object
     *
     * @param Imagick $im Image to load from
     */
    public function readImageFromImagick(\Imagick $im)
    {
        /* Strip transparency */
        $im = self::alphaRemove($im);
        /* Threshold */
        $im -> setImageType(\Imagick::IMGTYPE_TRUECOLOR); // Remove transparency (good for PDF's)
        $max = $im->getQuantumRange();
        $max = $max["quantumRangeLong"];
        $im -> thresholdImage(0.5 * $max);
        /* Make a string of 1's and 0's */
        $imgHeight = $im -> getimageheight();
        $imgWidth = $im -> getimagewidth();
        $imgData = str_repeat("\0", $imgHeight * $imgWidth);
        for ($y = 0; $y < $imgHeight; $y++) {
            for ($x = 0; $x < $imgWidth; $x++) {
                /* Faster to average channels, blend alpha and negate the image here than via filters (tested!) */
                $cols = $im -> getImagePixelColor($x, $y);
                $cols = $cols -> getcolor();
                $greyness = (int)(($cols['r'] + $cols['g'] + $cols['b']) / 3) >> 7;  // 1 for white, 0 for black
                $imgData[$y * $imgWidth + $x] = ($greyness); // 1 for white, 0 for black
            }
        }
        $this -> setImgWidth($imgWidth);
        $this -> setImgHeight($imgHeight);
        $this -> setImgData($imgData);
    }

    /**
     * Load an image from disk, into memory, using Imagick.
     *
     * @param string $filename The filename to load from
     * @throws Exception if the image format is not supported,
     *  or the file cannot be opened.
     */
    protected function loadImageData($filename = null)
    {
        if ($filename === null) {
            /* Set to blank image */
            return parent::loadImageData($filename);
        }
    
        $im = $this -> getImageFromFile($filename);
        $this -> readImageFromImagick($im);
    }

    /**
     * Load Imagick file from image
     *
     * @param string $filename Filename to load
     * @throws Exception Wrapped Imagick error if image can't be loaded
     * @return Imagick Loaded image
     */
    private function getImageFromFile($filename)
    {
        $im = new Imagick();
        try {
            $im->setResourceLimit(6, 1); // Prevent libgomp1 segfaults, grumble grumble.
            $im -> readimage($filename);
            // Scale image width to multiple of 8 or match the paper size
            if ($this->scaleWidth) {
                $width = floor($this->scaleWidth/8)*8;
            } else {
                $width = floor($im -> getimagewidth()/8)*8;
            }
            $im->scaleImage($width, $this->scaleHeight);
        } catch (ImagickException $e) {
            /* Re-throw as normal exception */
            throw new Exception($e);
        }
        return $im;
    }

    /**
     * Pull blob (from PBM-formatted image only!), and spit out a blob or raster data.
     * Will crash out on anything which is not a valid 'P4' file.
     *
     * @param Imagick $im Image which has format PBM.
     * @return string raster data from the image
     */
    private function getRasterBlobFromImage(Imagick $im)
    {
        $blob = $im -> getimageblob();
        /* Find where header ends */
        $i = strpos($blob, "P4\n") + 2;
        while ($blob[$i + 1] == '#') {
            $i = strpos($blob, "\n", $i + 1);
        }
        $i = strpos($blob, "\n", $i + 1);
        /* Return raster data only */
        $subBlob = substr($blob, $i + 1);
        return $subBlob;
    }

    /**
     * @param string $filename
     *  Filename to load from
     * @return string|NULL
     *  Raster format data, or NULL if no optimised renderer is available in
     *  this implementation.
     */
    protected function getRasterFormatFromFile($filename = null)
    {
        if ($filename === null) {
            return null;
        }
        $im = $this -> getImageFromFile($filename);
        $this -> setImgWidth($im -> getimagewidth());
        $this -> setImgHeight($im -> getimageheight());
        /* Convert to PBM and extract raster portion */
        $im = self::alphaRemove($im);
        $im -> setFormat('pbm');
        // Invert the color to match TSPL BITMAP: 1 for white, 0 for black
        $im -> negateImage(true, Imagick::CHANNEL_BLACK);
        $im -> setImageColorSpace(Imagick::COLORSPACE_CMYK);
        return $this -> getRasterBlobFromImage($im);
    }

    /**
     * Paste image over white canvas to stip transparency reliably on different
     * versions of ImageMagick.
     *
     * There are other methods for this:
     * - flattenImages() is deprecated
     * - setImageAlphaChannel(Imagick::ALPHACHANNEL_REMOVE) is not available on
     *      ImageMagick < 6.8.
     *
     * @param Imagick $im Image to flatten
     * @return Imagick Flattened image
     */
    private static function alphaRemove(Imagick $im)
    {
        $flat = new \Imagick();
        $flat -> newImage($im -> getimagewidth(), $im -> getimageheight(), "white", $im -> getimageformat());
        $flat -> compositeimage($im, \Imagick::COMPOSITE_OVER, 0, 0);
        return $flat;
    }
}
