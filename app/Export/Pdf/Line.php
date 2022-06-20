<?php

namespace App\Export\Pdf;

class Line
{
    public function __construct(protected Point $start, protected Point $end)
    {
        if($start->getX() == $end->getX() && $start->getX() == $end->getX()) {
            throw new \Exception('the points("' . $start . '" & "' . $end . '") form a dot instead of a line', E_ERROR);
        } else if($start->getX() != $end->getX() && $start->getY() != $end->getY()) {
            throw new \Exception('the points("' . $start . '" & "' . $end . '") form a rectangle instead of a line', E_ERROR);
        }
    }

    public function getStart(): Point {
        return $this->start;
    }

    public function getEnd(): Point {
        return $this->end;
    }

//    public function isHorizontal(): bool {
//        return $this->start->getY() == $this->end->getY();
//    }

    public function move(float $width, float $height): self {
        return new self(
            new Point($this->start->getX() + $width, $this->start->getY() + $height),
            new Point($this->end->getX() + $width, $this->end->getY() + $height )
        );
    }

//    public function isVertical(): bool {
//        return $this->start->getX() == $this->end->getX();
//    }

//    public function enlarge(float $multiplier): Rectangle
//    {
//        return new Point($this->getX() * $multiplier, $this->getY() * $multiplier);
//    }
}