<?php

declare(strict_types=1);

namespace FCToernooi\Competitor;

use Exception;
use FCToernooi\Tournament;
use Sports\Competition;

class Validator
{
    public function __construct()
    {
    }

    public function checkValidity(Tournament $tournament): void
    {
        $message = "tournament:" . $tournament->getName() . " => ";

        $locations = [];
        foreach ($tournament->getCompetitors() as $competitor) {
            if( array_key_exists($competitor->getStartId(), $locations) ) {
                $competitorChecked = $locations[$competitor->getStartId()];
                $message .= 'location ' . $competitor->getStartId() . ' is used for multiple competitors : ' . PHP_EOL;
                $message .= 'competitors "' . $competitor->getName() . '" & "'.$competitorChecked->getName().'"';
                throw new \Exception($message, E_ERROR);
            }
            $locations[$competitor->getStartId()] = $competitor;
        }
    }
}
