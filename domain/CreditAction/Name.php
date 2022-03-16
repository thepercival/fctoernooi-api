<?php

namespace FCToernooi\CreditAction;

enum Name: string
{
    case Buy = 'Buy';
    case ValidateReward = 'ValidateReward';
    case CreateTournament = 'CreateTournament';
}