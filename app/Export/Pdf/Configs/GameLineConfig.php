<?php

declare(strict_types=1);

namespace App\Export\Pdf\Configs;

class GameLineConfig
{
    public function __construct(
        private int $rowHeight,
        private int $fontHeight,
        private int $maxNrOfPlacesPerLine = 4
    ) {
        if ($maxNrOfPlacesPerLine < 1 || $maxNrOfPlacesPerLine > 6) {
            throw new \Exception('$maxNrOfPlacesPerLine should be between 1 and 6');
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

//    public function getDateTimeColumn(): DateTimeColumn
//    {
//        return $this->dateTimeColumn;
//    }
//
//    public function getRefereeColumn(): RefereeColumn
//    {
//        return $this->refereeColumn;
//    }

    public function getMaxNrOfPlacesPerLine(): int
    {
        return $this->maxNrOfPlacesPerLine;
    }
}
