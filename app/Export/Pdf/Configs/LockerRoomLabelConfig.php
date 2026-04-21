<?php

declare(strict_types=1);

namespace App\Export\Pdf\Configs;

final readonly class LockerRoomLabelConfig
{
    public function __construct(
        private float $infoHeight = 150.0,
        private float $startFontSize = 40.0,
        private float $maxFontSize = 50.0,
        private float $infoFontSize = 20.0
    ) {
    }

    public function getInfoHeight(): float
    {
        return $this->infoHeight;
    }

    public function getStartFontSize(): float
    {
        return $this->startFontSize;
    }

    public function getMaxFontSize(): float
    {
        return $this->maxFontSize;
    }

    public function getInfoFontSize(): float
    {
        return $this->infoFontSize;
    }
}
