<?php

declare(strict_types=1);

namespace App\Export\Pdf\Drawers\GameLine;

enum Column: int
{
    case Poule = 1;
    case Start = 2;
    case Field = 3;
    case PlacesAndScore = 4;
    case Referee = 5;
}
