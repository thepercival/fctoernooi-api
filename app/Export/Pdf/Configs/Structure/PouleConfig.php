<?php

declare(strict_types=1);

namespace App\Export\Pdf\Configs\Structure;

class PouleConfig
{
    private int $fontHeight;

    public function __construct(
        private int $paddingX = 2,
        private int $rowHeight = 18,
        int|null $fontHeight = null
    ) {
        if ($rowHeight < 10 || $rowHeight > 30) {
            throw new \Exception('rowHeight should be between 10 and 30');
        }
        if ($fontHeight === null) {
            $fontHeight = $this->rowHeight - 4;
        }

        if ($fontHeight < 10 || $fontHeight > 30) {
            throw new \Exception('fontHeight should be between 10 and 30');
        }
        $this->fontHeight = $fontHeight;

//        if ($fontHeight < 10 || $fontHeight > 20) {
//            throw new \Exception('placeWidth should be between 0 and 100');
//        }
//
//        if( $pouleMargin < 0 || $pouleMargin > 100) {
//            throw new \Exception('pouleMargin should be between 0 and 100');
//        }
//        if( $placeWidth < 0 || $placeWidth > 100) {
//            throw new \Exception('placeWidth should be between 0 and 100');
//        }
//        if( $rowHeight < 5 || $rowHeight > 30) {
//            throw new \Exception('rowHeight should be between 5 and 30');
//        }
//        if( $maxNrOfPlacesPerColumn < 1 || $maxNrOfPlacesPerColumn > 20) {
//            throw new \Exception('maxNrOfPlacesPerColumn should be between 1 and 20');
//        }
    }

    public function getPaddingX(): int
    {
        return $this->paddingX;
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