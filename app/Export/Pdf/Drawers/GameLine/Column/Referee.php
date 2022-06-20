<?php

declare(strict_types=1);

namespace App\Export\Pdf\Drawers\GameLine\Column;

enum Referee: int
{
    case None = 1;
    case Referee = 2;
    case SelfReferee = 3;
}
