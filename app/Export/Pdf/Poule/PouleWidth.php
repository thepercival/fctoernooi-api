<?php

namespace App\Export\Pdf\Poule;

use Sports\Poule;

final class PouleWidth
{
    public function __construct(protected float $width)
    {
    }

    public function getWidth(): float
    {
        return $this->width;
    }
}
