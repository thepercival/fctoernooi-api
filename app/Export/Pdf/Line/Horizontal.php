<?php

namespace App\Export\Pdf\Line;

use App\Export\Pdf\Line;
use App\Export\Pdf\Point;

class Horizontal extends Line
{
    public function __construct(protected Point $start, float $width)
    {
        if ($width < 0) {
            parent::__construct($start->addX($width), $start);
        } else {
            parent::__construct($start, $start->addX($width));
        }
    }

    public function getY(): float
    {
        return $this->start->getY();
    }

    public function addY(float $y): self
    {
        return new self($this->start->addY($y), $this->getWidth());
    }

    public function getWidth(): float {
        return $this->end->getX() - $this->start->getX();
    }

    public function moveY(float $length): self {
        $newStartPoint = new Point($this->getStart()->getX(), $this->getStart()->getY() + $length);
        return new self($newStartPoint, $this->getWidth());
    }
}