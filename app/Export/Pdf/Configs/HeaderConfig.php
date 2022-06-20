<?php

declare(strict_types=1);

namespace App\Export\Pdf\Configs;

class HeaderConfig
{
    public function __construct(
        private int $rowHeight = 18/*; between $fontHeight and 20*/,
        private int $fontHeight = 14/*; between 10 and 20*/,
    )
    {
        if( $fontHeight < 10 || $fontHeight > 20) {
            throw new \Exception('fontHeight should be between 10 and 20');
        }
        if( $rowHeight <= $fontHeight || $rowHeight > 20) {
            throw new \Exception('rowHeight should be between fontheight and 20');
        }
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
