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

namespace LoKingWei\Tspl;

use Exception;
use LoKingWei\Tspl\PrintImages\TsplImage;

class Printer
{
    protected $connector;

    private $defaultUnit;

    private $sizeWidth;
    private $sizeHeight;
    private $sizeUnit;

    private $gapDistance;
    private $gapOffset;
    private $gapUnit;

    private $referenceX;
    private $referenceY;

    const LINE_BREAK = "\r\n";
    const SEPARATOR = ",";
    const SPACE = " ";

    //Configuration related
    const SIZE = 'SIZE';
    const GAP = 'GAP';
    const REFERENCE = 'REFERENCE';

    //Action related command
    const BITMAP = "BITMAP";
    const PRINT = "PRINT";

    //Single word command
    const CLS = "CLS";
    const EOP = "EOP";
    const DEFAULT_UNIT = "";

    public function setDefaultUnit($defaultUnit) {
        $this->defaultUnit = $defaultUnit;
    }

    public function setSize($width, $height = null, $unit = null) {
        $this->sizeWidth = $width;
        $this->sizeHeight = $height;
        $this->sizeUnit = $unit;
    }

    public function setGap($distance, $offset, $unit = null) {
        $this->gapDistance = $distance;
        $this->gapOffset = $offset;
        $this->gapUnit = $unit;
        return $this;
    }

    public function setReference($x, $y) {
        $this->referenceX = $x;
        $this->referenceY = $y;
        return $this;
    }

    
    public function getSizeCommand()
    {
        $str = self::SIZE;
        $str .= self::SPACE;
        $str .= $this->sizeWidth;
        $str .= $this->getUnit($this->sizeUnit);
        if(isset($this->sizeHeight)) {
            $str .= self::SEPARATOR;
            $str .= $this->sizeHeight;
            $str .= $this->getUnit($this->sizeUnit);
        }
        return $str;
    }

    public function getGapCommad()
    {
        $str = self::GAP;
        $str .= self::SPACE;
        $str .= $this->gapDistance;
        $str .= $this->getUnit($this->gapUnit);
        $str .= self::SEPARATOR;
        $str .= $this->gapOffset;
        $str .= $this->getUnit($this->gapUnit);
        return $str;
    }

    public function getReferenceCommand()
    {
        $str = self::REFERENCE;
        $str .= self::SPACE;
        $str .= $this->referenceX;
        $str .= self::SEPARATOR;
        $str .= $this->referenceY;
        return $str;
    }

    public function getBitmapCommand($x, $y, $withdBytes, $heightDots, $mode, $data)
    {
        $str = self::BITMAP;
        $str .= self::SPACE;
        $str .= $x;
        $str .= self::SEPARATOR;
        $str .= $y;
        $str .= self::SEPARATOR;
        $str .= $withdBytes;
        $str .= self::SEPARATOR;
        $str .= $heightDots;
        $str .= self::SEPARATOR;
        $str .= $mode;
        $str .= self::SEPARATOR;
        $str .= $data;
        return $str;
    }

    public function getPrintCommand($set, $copy = null)
    {
        $str = self::PRINT;
        $str .= self::SPACE;
        $str .= $set;
        if(isset($copy)) {
            $str .= self::SEPARATOR;
            $str .= $copy;
        }
        return $str;
    }

    public function printBitmapImage(TsplImage $image, $x = 0, $y = 0, $mode = 0)
    {
        $commands = [];
        array_push($commands, $this->getSizeCommand());
        array_push($commands, $this->getGapCommad());
        array_push($commands, $this->getReferenceCommand());
        array_push($commands, self::CLS);
        array_push($commands, $this->getBitmapCommand($x, $y, $image->getWidthBytes(), $image->getHeight(), $mode, $image->toRasterFormat()));
        array_push($commands, $this->getPrintCommand(1));
        array_push($commands, self::EOP);
        $this->sendCommands($commands);
    }

    protected function sendCommands($commands)
    {
        $commandString = implode(self::LINE_BREAK, $commands);
        $this->connector->write($commandString);
    }

    protected function getUnit($unit)
    {
        return isset($unit) ? $unit : $this->defaultUnit ? $this->defaultUnit : self::DEFAULT_UNIT;
    }
}
