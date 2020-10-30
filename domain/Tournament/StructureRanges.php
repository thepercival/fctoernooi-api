<?php

declare(strict_types=1);


namespace FCToernooi\Tournament;

use SportsHelpers\Range;
use SportsHelpers\Place\Range as PlaceRange;

class StructureRanges
{
    /**
     * @var array|PlaceRange[]
     */
    protected $placeRanges;

    public function __construct()
    {
        $this->placeRanges[] = new PlaceRange(2, 40, new Range(2, 12));
        $this->placeRanges[] = new PlaceRange(41, 128, new Range(2, 8));
    }

    public function getFirstPlaceRange(): PlaceRange
    {
        return reset($this->placeRanges);
    }
}
