<?php


namespace FCToernooi\Tournament;

use Voetbal\Range as VoetbalRange;
use Voetbal\Place\Range as PlaceRange;

class StructureRanges
{
    /**
     * @var array|PlaceRange[]
     */
    protected $placeRanges;

    public function __construct()
    {
        $this->placeRanges[] = new PlaceRange(2, 40, new VoetbalRange(2, 12));
        $this->placeRanges[] = new PlaceRange(41, 128, new VoetbalRange(2, 8));
    }

    public function getFirstPlaceRange(): PlaceRange
    {
        return reset($this->placeRanges);
    }
}
