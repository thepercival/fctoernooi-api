<?php

namespace App\Export\Pdf\Poule;

use App\Export\Pdf\Point;
use Sports\Poule;

class PoulePoint
{
    public function __construct(protected Point $point, protected Poule $poule)
    {
    }

    public function getPoint(): Point
    {
        return $this->point;
    }

    public function getPoule(): Poule
    {
        return $this->poule;
    }
}
