<?php

declare(strict_types=1);

namespace App\Export\Pdf\Configs;

readonly class FrontPageConfig
{
    public function __construct(
        private int $padding,
        private int $fontHeight,
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

    public function getPadding(): int
    {
        return $this->padding;
    }

    public function getFontHeight(): int
    {
        return $this->fontHeight;
    }
}
