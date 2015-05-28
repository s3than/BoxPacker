<?php

namespace DVDoug\BoxPacker;

require dirname(__FILE__) . "/../vendor/autoload.php";

class StdItem implements \DVDoug\BoxPacker\RotateItemInterface {

    public function __construct($aDescription,$aWidth,$aLength,$aDepth,$aWeight,$rotateVertical=false) {
        $this->description = $aDescription;
        $this->width = $aWidth;
        $this->length = $aLength;
        $this->depth = $aDepth;
        $this->weight = $aWeight;
        $this->volume = $this->width * $this->length * $this->depth;
        $this->rotateVertical = $rotateVertical;
    }

    public function getDescription() {
        return $this->description;
    }

    public function setWidth($width) {
        $this->width = $width;
    }

    public function getWidth() {
        return $this->width;
    }

    public function setLength($length) {
        $this->length = $length;
    }

    public function getLength() {
        return $this->length;
    }

    public function setDepth($depth) {
        $this->depth = $depth;
    }

    public function getDepth() {
        return $this->depth;
    }

    public function getWeight() {
        return $this->weight;
    }

    public function getVolume() {
        return $this->volume;
    }

    public function isRotateVertical()
    {
        return $this->rotateVertical;
    }
}
