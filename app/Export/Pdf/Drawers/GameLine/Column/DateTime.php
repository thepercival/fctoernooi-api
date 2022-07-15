<?php

declare(strict_types=1);

namespace App\Export\Pdf\Drawers\GameLine\Column;

use Sports\Round\Number as RoundNumber;

enum DateTime: int
{
    case None = 1;
    case DateTime = 2;
    case Time = 3;

    public static function getValue(RoundNumber $roundNumber): self
    {
        $planningConfig = $roundNumber->getValidPlanningConfig();
        if (!$planningConfig->getEnableTime()) {
            return self::None;
        }
        if (self::gamesOnSameDay($roundNumber)) {
            return self::Time;
        }
        return self::DateTime;
    }

    private static function gamesOnSameDay(RoundNumber $roundNumber): bool
    {
        $dateOne = $roundNumber->getFirstGameStartDateTime();
        $dateTwo = $roundNumber->getLastGameStartDateTime();
        return $dateOne->format('Y-m-d') === $dateTwo->format('Y-m-d');
    }
}
