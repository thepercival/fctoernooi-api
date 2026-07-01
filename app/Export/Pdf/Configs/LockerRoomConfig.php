<?php

declare(strict_types=1);

namespace App\Export\Pdf\Configs;

final readonly class LockerRoomConfig
{
    public function __construct(
        private float $lockerRoomMargin,
        private float $rowHeight,
        private float $fontHeight,
    ) {
        if ($lockerRoomMargin < 10.0 || $lockerRoomMargin > 30.0) {
            throw new \Exception('lockerRoomMargin should be between 10 and 30');
        }
        if ($fontHeight < 10.0 || $fontHeight > 20.0) {
            throw new \Exception('fontHeight should be between 10 and 20');
        }
        if ($rowHeight <= $fontHeight || $rowHeight > 20.0) {
            throw new \Exception('rowHeight should be between fontheight and 20');
        }
    }

    public function getLockerRoomMargin(): float
    {
        return $this->lockerRoomMargin;
    }

    public function getRowHeight(): float
    {
        return $this->rowHeight;
    }

    public function getFontHeight(): float
    {
        return $this->fontHeight;
    }
}
