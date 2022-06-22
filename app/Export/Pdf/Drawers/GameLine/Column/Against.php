<?php

declare(strict_types=1);

namespace App\Export\Pdf\Drawers\GameLine\Column;

enum Against: int
{
    case SidePlaces = 10;
    case Score = 11;
}
