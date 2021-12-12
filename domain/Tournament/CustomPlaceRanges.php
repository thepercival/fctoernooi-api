<?php

declare(strict_types=1);

namespace FCToernooi\Tournament;

use SportsHelpers\PlaceRanges;

class CustomPlaceRanges extends PlaceRanges
{
    public const MaxNrOfPlacesPerPouleSmall = 20;
    public const MaxNrOfPlacesPerPouleLarge = 12;
    public const MaxNrOfPlacesPerRoundSmall = 40;
    public const MaxNrOfPlacesPerRoundLarge = 128;

    public function __construct(int $minNrOfPlacesPerPoule)
    {
        parent::__construct(
            $minNrOfPlacesPerPoule,
            self::MaxNrOfPlacesPerPouleSmall,
            self::MaxNrOfPlacesPerPouleLarge,
            $minNrOfPlacesPerPoule,
            self::MaxNrOfPlacesPerRoundSmall,
            self::MaxNrOfPlacesPerRoundLarge,
        );
    }
}
