<?php

declare(strict_types=1);

namespace App\Export\Pdf\Configs;

final readonly class GamesConfig
{
    public function __construct(
        private float $roundNumberHeaderHeight/*,
        private float $rowHeight,
        private float $fontHeight,*/
    ) {
//        if ($fontHeight < 10.0 || $fontHeight > 20.0) {
//            throw new \Exception('fontHeight should be between 10 and 20');
//        }
//        if ($rowHeight <= $fontHeight || $rowHeight > 20.0) {
//            throw new \Exception('rowHeight should be between fontheight and 20');
//        }
    }

    public function getRoundNumberHeaderHeight(): float
    {
        return $this->roundNumberHeaderHeight;
    }

    public function getRoundNumberHeaderFontHeight(): float
    {
        return $this->roundNumberHeaderHeight - 4.0;
    }

//    public function getRowHeight(): int
//    {
//        return $this->rowHeight;
//    }

//    public function getFontHeight(): int
//    {
//        return $this->fontHeight;
//    }
}
