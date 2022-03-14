<?php

declare(strict_types=1);

namespace FCToernooi;

use DateTimeImmutable;
use League\Period\Period;
use SportsHelpers\Identifiable;

class Recess extends Identifiable
{
    private DateTimeImmutable $startDateTime;
    private DateTimeImmutable $endDateTime;

    public function __construct(private Tournament $tournament, Period $period)
    {
        $this->startDateTime = $period->getStartDate();
        $this->endDateTime = $period->getEndDate();
        $tournament->getRecesses()->add($this);
    }

    public function getTournament(): Tournament
    {
        return $this->tournament;
    }

    public function getStartDateTime(): DateTimeImmutable
    {
        return $this->startDateTime;
    }

    public function getEndDateTime(): DateTimeImmutable
    {
        return $this->endDateTime;
    }

    public function getPeriod(): Period
    {
        return new Period($this->getStartDateTime(), $this->getEndDateTime());
    }
}
