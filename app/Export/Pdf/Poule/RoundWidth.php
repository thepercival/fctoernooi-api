<?php

namespace App\Export\Pdf\Poule;

use Sports\Round;

class RoundWidth
{
    public function __construct(protected float $width, protected Round $round)
    {
    }

    public function getWidth(): float
    {
        return $this->width;
    }

    public function getRound(): Round
    {
        return $this->round;
    }
}
