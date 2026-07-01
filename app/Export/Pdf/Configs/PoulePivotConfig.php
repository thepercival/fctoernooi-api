<?php

declare(strict_types=1);

namespace App\Export\Pdf\Configs;

final readonly class PoulePivotConfig
{
    public function __construct(
        private float $rowHeight = 18.0/*; between $fontHeight and 20*/,
        private float $fontHeight = 14.0/*; between 10 and 20*/,
    )
    {
        if( $fontHeight < 10.0 || $fontHeight > 20.0) {
            throw new \Exception('fontHeight should be between 10 and 20');
        }
        if( $rowHeight <= $fontHeight || $rowHeight > 20.0) {
            throw new \Exception('rowHeight should be between fontheight and 20');
        }
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
