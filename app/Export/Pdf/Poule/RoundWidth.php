<?php

namespace App\Export\Pdf\Poule;

use Sports\Round;

final class RoundWidth
{
    public function __construct(protected float $width)
    {
    }

    public function getWidth(): float
    {
        return $this->width;
    }

//    public function getRound(): Round
//    {
//        return $this->round;
//    }
}
