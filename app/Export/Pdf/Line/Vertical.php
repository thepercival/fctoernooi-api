<?php

namespace App\Export\Pdf\Line;

use App\Export\Pdf\Line;
use App\Export\Pdf\Point;

class Vertical extends Line
{
    public function __construct(protected Point $start, float $height)
    {
        if ($height < 0) {
            parent::__construct($start->addY($height), $start);
        } else {
            parent::__construct($start, $start->addY($height));
        }
    }

    public function getX(): float
    {
        return $this->start->getX();
    }

    public function addX(float $delta): self
    {
        return new self($this->start->addX($delta), $this->getHeight());
    }

//    public function moveDown(): self
//    {
//        return new self($this->end, $this->getHeight());
//    }

    public function getHeight(): float
    {
        return $this->end->getY() - $this->start->getY();
    }
}