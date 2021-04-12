<?php
declare(strict_types=1);

namespace FCToernooi\Tournament;

use SportsHelpers\SportRange;
use SportsHelpers\Place\Range as PlaceRange;

class StructureRanges
{
    /**
     * @var non-empty-list<PlaceRange>
     */
    protected array $placeRanges;

    public function __construct()
    {
        $this->placeRanges = [
            new PlaceRange(2, 40, new SportRange(2, 12)),
            new PlaceRange(41, 128, new SportRange(2, 8))
        ];
    }

    public function getFirstPlaceRange(): PlaceRange
    {
        return reset($this->placeRanges);
    }
}
