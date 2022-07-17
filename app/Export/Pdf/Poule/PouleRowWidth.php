<?php

namespace App\Export\Pdf\Poule;

use Sports\Poule;

class PouleRowWidth
{
    public function __construct(protected array $poules, protected float $width)
    {
    }

    public function getWidth(): float
    {
        return $this->width;
    }

    /**
     * @return list<Poule>
     */
    public function getPoules(): array
    {
        return $this->poules;
    }
}
