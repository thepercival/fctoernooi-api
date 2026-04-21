<?php

declare(strict_types=1);

namespace App\Export\Pdf\Configs;

final readonly class RegistrationFormConfig
{
    public function __construct(
//        private int $roundNumberHeaderHeight,
        private float $rowHeight,
        private float $fontHeight,
    ) {
        if ($fontHeight < 10.0 || $fontHeight > 20.0) {
            throw new \Exception('fontHeight should be between 10 and 20');
        }
        if ($rowHeight <= $fontHeight || $rowHeight > 20.0) {
            throw new \Exception('rowHeight should be between fontheight and 20');
        }
    }

//    public function getRoundNumberHeaderHeight(): int
//    {
//        return $this->roundNumberHeaderHeight;
//    }
//
//    public function getRoundNumberHeaderFontHeight(): int
//    {
//        return $this->roundNumberHeaderHeight - 4;
//    }

    public function getRowHeight(): float
    {
        return $this->rowHeight;
    }

    public function getFontHeight(): float
    {
        return $this->fontHeight;
    }
}
