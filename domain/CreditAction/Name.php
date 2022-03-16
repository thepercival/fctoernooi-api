<?php

namespace FCToernooi\CreditAction;

enum Name: string
{
    case CreateAccountReward = 'CreateAccountReward';
    case CreateTournament = 'CreateTournament';
    case ValidateReward = 'ValidateReward';
    case Buy = 'Buy';
}