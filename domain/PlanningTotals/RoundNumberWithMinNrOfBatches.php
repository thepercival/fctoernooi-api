<?php

namespace FCToernooi\PlanningTotals;

use Sports\Round\Number as RoundNumber;

class RoundNumberWithMinNrOfBatches
{
    public function __construct(public RoundNumber $roundNumber, public int $minNrOfBatches ) {

    }
}