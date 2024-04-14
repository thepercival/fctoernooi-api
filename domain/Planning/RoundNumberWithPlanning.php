<?php

namespace FCToernooi\Planning;

use Sports\Round\Number as RoundNumber;
use SportsPlanning\Planning;

class RoundNumberWithPlanning
{
    public function __construct(public RoundNumber $roundNumber, public Planning $planning ) {

    }
}