<?php

namespace FCToernooi\Recess;

use FCToernooi\Tournament;
use League\Period\Period;

class Validator
{
    public function validateNewPeriod(Period $recessPeriod, Tournament $tournament): void {
        $this->validateBeforeCompetitionStart($recessPeriod, $tournament);
        $this->validateOverlapping($recessPeriod, $tournament);
    }

    protected function validateBeforeCompetitionStart(Period $recessPeriod, Tournament $tournament): void {
        $competitionStart = $tournament->getCompetition()->getStartDateTime();
        if ($recessPeriod->getEndDate()->getTimestamp() <= $competitionStart->getTimestamp()) {
            throw new \Exception('er is een pauze voordat het toernooi start', E_ERROR);
        }
    }

    protected function validateOverlapping(Period $recessPeriod, Tournament $tournament): void {
        foreach ($tournament->getRecesses() as $recessIt) {
            if ($recessIt->getPeriod()->overlaps($recessPeriod)) {
                throw new \Exception('er is een overlapping met een andere pauze', E_ERROR);
            }
        }
    }
}