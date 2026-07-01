<?php

declare(strict_types=1);

namespace App\Export\Pdf\Configs;

final readonly class FrontPageConfig
{
    public function __construct(
        private float $padding,
        private float $fontHeight,
    ) {
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

    public function getPadding(): float
    {
        return $this->padding;
    }

    public function getFontHeight(): float
    {
        return $this->fontHeight;
    }
}
