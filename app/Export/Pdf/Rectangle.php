<?php

namespace App\Export\Pdf;

use App\Export\Pdf\Line\Horizontal as HorizontalLine;
use App\Export\Pdf\Line\Vertical as VerticalLine;

final class Rectangle
{
    private HorizontalLine $top;
    private VerticalLine $right;
    private HorizontalLine $bottom;
    private VerticalLine $left;

    public function __construct(HorizontalLine|VerticalLine $start, float $widthHeight)
    {
        if ($widthHeight == 0.0) {
            throw new \Exception('width or height can not be null', E_ERROR);
        }
        if ($start instanceof HorizontalLine) {
            $this->initByHorizontalLine($start, $widthHeight);
        } else {
            $this->initByVerticalLine($start, $widthHeight);
        }
    }

    private function initByHorizontalLine(HorizontalLine $horLine, float $height): void
    {
        if ($height < 0.0) {
            $this->top = $horLine;
            $this->bottom = $horLine->addY($height);
        } else {
            $this->bottom = $horLine;
            $this->top = $horLine->addY($height);
        }

        $calcHeight = $this->top->getY() - $this->bottom->getY();
        $this->left = new VerticalLine($this->bottom->getStart(), $calcHeight);
        $this->right = new VerticalLine($this->bottom->getEnd(), $calcHeight);
    }

    private function initByVerticalLine(VerticalLine $vertLine, float $width): void
    {
        if ($width < 0.0) {
            $this->right = $vertLine;
            $this->left = $vertLine->addX($width);
        } else {
            $this->left = $vertLine;
            $this->right = $vertLine->addX($width);
        }
        $this->top = new HorizontalLine($this->left->getStart(), $width);
        $this->bottom = new HorizontalLine($this->left->getEnd(), $width);
    }

    public function getHeight(): float
    {
        return $this->top->getY() - $this->bottom->getY();
    }

    public function getWidth(): float
    {
        return $this->right->getX() - $this->left->getX();
    }

//    public function getStart(): Point
//    {
//        return $this->top->getStart();
//    }

    public function getTop(): HorizontalLine
    {
        return $this->top;
    }

    public function getRight(): VerticalLine
    {
        return $this->right;
    }

//    public function getEnd(): Point {
//        return $this->end;
//    }

    public function getBottom(): HorizontalLine
    {
        return $this->bottom;
    }

    public function getLeft(): VerticalLine
    {
        return $this->left;
    }

    public function getAspectRatio(): float
    {
        return $this->getWidth() / $this->getHeight();
    }

//    public function enlarge(float $multiplier): Rectangle
//    {
//        return new Point($this->getX() * $multiplier, $this->getY() * $multiplier);
//    }
}
