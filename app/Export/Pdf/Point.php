<?php

namespace App\Export\Pdf;

final class Point
{
    public function __construct(protected float $x, protected float $y)
    {
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
        return new self($this->getX() + $x, $this->getY() + $y);
    }

    public function enlarge(float $multiplier): Point
    {
        return new Point($this->getX() * $multiplier, $this->getY() * $multiplier);
    }
}