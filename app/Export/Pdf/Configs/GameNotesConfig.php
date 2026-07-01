<?php

declare(strict_types=1);

namespace App\Export\Pdf\Configs;

final readonly class GameNotesConfig
{
    public function __construct(
        private float $rowHeight = 20,
        private float $fontHeight = 14,
        private float $margin = 15
    )
    {
        if( $fontHeight < 10.0 || $fontHeight > 30.0) {
            throw new \Exception('fontHeight should be between 10 and 30');
        }
        if( $rowHeight <= $fontHeight || $rowHeight > 20.0) {
            throw new \Exception('rowHeight should be between fontheight and 20');
        }
        if( $margin <= 10.0 || $margin > 20.0) {
            throw new \Exception('margin should be between 10 and 20');
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

    public function getMargin(): float
    {
        return $this->margin;
    }
}
