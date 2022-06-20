<?php

declare(strict_types=1);

namespace App\Export\Pdf\Configs;

use App\Export\Pdf\Drawers\GameLine\Column\DateTime as DateTimeColumn;
use App\Export\Pdf\Drawers\GameLine\Column\Referee as RefereeColumn;

class GameLineConfig
{
    public function __construct(
        private DateTimeColumn $dateTimeColumn,
        private RefereeColumn $refereeColumn,
        private int $rowHeight = 18,
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

    public function getDateTimeColumn(): DateTimeColumn
    {
        return $this->dateTimeColumn;
    }

    public function getRefereeColumn(): RefereeColumn
    {
        return $this->refereeColumn;
    }

    public function getMaxNrOfPlacesPerLine(): int
    {
        return $this->maxNrOfPlacesPerLine;
    }
}
