<?php

declare(strict_types=1);

namespace App\Export\Pdf\Configs;

class RegistrationFormConfig
{
    public function __construct(
//        private int $roundNumberHeaderHeight,
        private int $rowHeight,
        private int $fontHeight,
    ) {
        if ($fontHeight < 10 || $fontHeight > 20) {
            throw new \Exception('fontHeight should be between 10 and 20');
        }
        if ($rowHeight <= $fontHeight || $rowHeight > 20) {
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

    public function getRowHeight(): int
    {
        return $this->rowHeight;
    }

    public function getFontHeight(): int
    {
        return $this->fontHeight;
    }
}
