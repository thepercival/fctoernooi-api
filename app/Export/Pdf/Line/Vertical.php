<?php

namespace App\Export\Pdf\Line;

use App\Export\Pdf\Line;
use App\Export\Pdf\Point;

class Vertical extends Line
{
    public function __construct(protected Point $start, float $height)
    {
        parent::__construct($start, $start->addY($height));
    }

    public function getX(): float
    {
        return $this->start->getX();
    }

    public function addX(float $y): self
    {
        return new self($this->start->addX($y), $this->getHeight());
    }

//    public function moveDown(): self
//    {
//        return new self($this->end, $this->getHeight());
//    }

    public function getHeight(): float
    {
        return $this->start->getY() - $this->end->getY();
    }
}