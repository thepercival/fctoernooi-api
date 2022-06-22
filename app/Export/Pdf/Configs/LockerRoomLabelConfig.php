<?php

declare(strict_types=1);

namespace App\Export\Pdf\Configs;

class LockerRoomLabelConfig
{
    public function __construct(
        private int $infoHeight = 150,
        private int $startFontSize = 40,
        private int $maxFontSize = 50,
        private int $infoFontSize = 20
    ) {
    }

    public function getInfoHeight(): int
    {
        return $this->infoHeight;
    }

    public function getStartFontSize(): int
    {
        return $this->startFontSize;
    }

    public function getMaxFontSize(): int
    {
        return $this->maxFontSize;
    }

    public function getInfoFontSize(): int
    {
        return $this->infoFontSize;
    }
}
