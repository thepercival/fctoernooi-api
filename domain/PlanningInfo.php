<?php

declare(strict_types=1);

namespace FCToernooi;

use DateTimeImmutable;
use FCToernooi\PlanningInfo\CompetitorAmount;
use League\Period\Period;

final class PlanningInfo
{
    private DateTimeImmutable $startDateTime;
    private DateTimeImmutable $endDateTime;

    public function __construct(Period $period, private CompetitorAmount $competitorAmount)
    {
        $this->startDateTime = $period->getStartDate();
        $this->endDateTime = $period->getEndDate();
    }

    public function getStartDateTime(): DateTimeImmutable
    {
        return $this->startDateTime;
    }

    public function getEndDateTime(): DateTimeImmutable
    {
        return $this->endDateTime;
    }

    public function getCompetitorAmount(): CompetitorAmount
    {
        return $this->competitorAmount;
    }
}
