<?php

namespace App\Export\Pdf\Poule;

use Sports\Poule;

class PouleWidth
{
    public function __construct(protected float $width, protected Poule $poule)
    {
    }

    public function getWidth(): float
    {
        return $this->width;
    }

    public function getPoule(): Poule
    {
        return $this->poule;
    }
}
