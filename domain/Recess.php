<?php

declare(strict_types=1);

namespace FCToernooi;

use DateTimeImmutable;
use League\Period\Period;
use SportsHelpers\Identifiable;

final class Recess extends Identifiable
{
    public const MAX_LENGTH_NAME = 15;

    private string $name;
    private DateTimeImmutable $startDateTime;
    private DateTimeImmutable $endDateTime;

    public function __construct(private Tournament $tournament, string $name, Period $period)
    {
        $this->setName($name);
        $this->startDateTime = $period->getStartDate();
        $this->endDateTime = $period->getEndDate();
        $tournament->getRecesses()->add($this);
    }

    public function getTournament(): Tournament
    {
        return $this->tournament;
    }

    public function getName(): string
    {
        return $this->name;
    }

    protected function setName(string $name): void
    {
        if (strlen($name) > self::MAX_LENGTH_NAME) {
            throw new \InvalidArgumentException(
                'de naam mag maximaal ' . self::MAX_LENGTH_NAME . ' karakters bevatten',
                E_ERROR
            );
        }
        $this->name = $name;
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
