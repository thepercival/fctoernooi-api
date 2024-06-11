<?php

declare(strict_types=1);

namespace App\Export\Pdf\Configs;

readonly class LockerRoomConfig
{
    public function __construct(
        private int $lockerRoomMargin,
        private int $rowHeight,
        private int $fontHeight,
    ) {
        if ($lockerRoomMargin < 10 || $lockerRoomMargin > 30) {
            throw new \Exception('lockerRoomMargin should be between 10 and 30');
        }
        if ($fontHeight < 10 || $fontHeight > 20) {
            throw new \Exception('fontHeight should be between 10 and 20');
        }
        if ($rowHeight <= $fontHeight || $rowHeight > 20) {
            throw new \Exception('rowHeight should be between fontheight and 20');
        }
    }

    public function getLockerRoomMargin(): int
    {
        return $this->lockerRoomMargin;
    }

    public function getRowHeight(): int
    {
        return $this->rowHeight;
    }

    public function getFontHeight(): int
    {
        return $this->fontHeight;
    }
}
