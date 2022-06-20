<?php

declare(strict_types=1);

namespace App\Export\Pdf\Drawers\GameLine\Column;

enum DateTime: int
{
    case None = 1;
    case DateTime = 2;
    case Time = 3;
}
