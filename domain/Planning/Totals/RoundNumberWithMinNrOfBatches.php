<?php

namespace FCToernooi\Planning\Totals;

use Sports\Round\Number as RoundNumber;

final class RoundNumberWithMinNrOfBatches
{
    public function __construct(public RoundNumber $roundNumber, public int $minNrOfBatches ) {

    }
}