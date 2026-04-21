<?php

namespace App\Export\Pdf;

final class Point implements \Stringable
{
    protected float $x;
    protected float $y;

    public function __construct(Point|float $pointOrX, float $y = null)
    {
        if( $pointOrX instanceof Point) {
            $this->x = $pointOrX->getX();
            $this->y = $pointOrX->getY();
        } else {
            $this->x = $pointOrX;
            if( $y === null ) {
                throw new \Exception('incorrect use of point-constructor');
            }
            $this->y = $y;
        }
    }

    public function addX(float $x): self
    {
        return new self($this->getX() + $x, $this->getY());
    }

    public function getX(): float
    {
        return $this->x;
    }

    public function getY(): float
    {
        return $this->y;
    }

    public function addY(float $y): self
    {
        return new self($this->getX(), $this->getY() + $y);
    }

    public function add(float $x, float $y): self
    {
        return new self($this->getX() + $x, $this->getY() - $y);
    }

//    public function enlarge(float $multiplier): Point
//    {
//        return new Point($this->getX() * $multiplier, $this->getY() * $multiplier);
//    }

    #[\Override]
    public function __toString(): string
    {
        return (string)$this->getX() . ',' . (string)$this->getY();
    }
}