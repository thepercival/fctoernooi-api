<?php

declare(strict_types=1);

namespace App\Export\Pdf\Configs;

class GameNotesConfig
{
    public function __construct(
        private int $rowHeight = 20,
        private int $fontHeight = 14,
        private int $margin = 15
    )
    {
        if( $fontHeight < 10 || $fontHeight > 30) {
            throw new \Exception('fontHeight should be between 10 and 30');
        }
        if( $rowHeight <= $fontHeight || $rowHeight > 20) {
            throw new \Exception('rowHeight should be between fontheight and 20');
        }
        if( $margin <= 10 || $margin > 20) {
            throw new \Exception('margin should be between 10 and 20');
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

    public function getMargin(): int
    {
        return $this->margin;
    }
}
