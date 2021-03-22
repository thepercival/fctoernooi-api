<?php

declare(strict_types=1);

namespace FCToernooi\Tournament;

use DateTimeImmutable;
use Sports\Sport;
use FCToernooi\Tournament;
use FCToernooi\User;

class Shell
{
    /**
     * @var int
     */
    private $tournamentId;
    /**
     * @var int
     */
    private $sportCustomId;
    /**
     * @var string
     */
    private $name;
    /**
     * @var DateTimeImmutable
     */
    private $startDateTime;
    /**
     * @var int
     */
    private $roles;
    /**
     * @var bool
     */
    private $public;

    public function __construct(Tournament $tournament, User $user = null)
    {
        $this->tournamentId = $tournament->getId();
        $competition = $tournament->getCompetition();
        $league = $competition->getLeague();
        $this->sportCustomId = 0;
        if ($competition->getSports()->count() === 1) {
            $this->sportCustomId = $competition->getSports()->first()->getSport()->getCustomId();
        }
        $this->name = $league->getName();
        $this->startDateTime = $competition->getStartDateTime();

        $this->roles = 0;
        if ($user !== null) {
            $tournamentUser = $tournament->getUser($user);
            if ($tournamentUser !== null) {
                $this->roles = $tournamentUser->getRoles();
            }
        }
        $this->public = $tournament->getPublic();
    }

    public function getTournamentId(): int
    {
        return $this->tournamentId;
    }

    public function getSportCustomId(): int
    {
        return $this->sportCustomId;
    }

    /**
     * @param list<Sport> $sports
     * @return int
     */
    protected function getSportCustomIdBySports(array $sports): int
    {
        $firstSport = reset($sports);
        if ($firstSport !== false) {
            if (count($sports) === 1) {
                return $firstSport->getCustomId();
            }
            return -1;
        }
        return 0;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getStartDateTime(): DateTimeImmutable
    {
        return $this->startDateTime;
    }

    public function getRoles(): int
    {
        return $this->roles;
    }

    public function getPublic(): bool
    {
        return $this->public;
    }
}
