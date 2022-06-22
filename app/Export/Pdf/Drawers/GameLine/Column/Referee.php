<?php

declare(strict_types=1);

namespace App\Export\Pdf\Drawers\GameLine\Column;

use Sports\Round\Number as RoundNumber;

enum Referee: int
{
    case None = 1;
    case Referee = 2;
    case SelfReferee = 3;

    public static function getValue(RoundNumber $roundNumber): self
    {
        $planningConfig = $roundNumber->getValidPlanningConfig();
        if ($planningConfig->selfRefereeEnabled()) {
            return self::SelfReferee;
        } elseif ($roundNumber->getCompetition()->getReferees()->count() >= 1) {
            return self::Referee;
        }
        return self::None;
    }
}
