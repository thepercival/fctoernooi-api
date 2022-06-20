<?php

declare(strict_types=1);

namespace FCToernooi\Tournament;

use DateTimeImmutable;
use FCToernooi\Tournament;
use FCToernooi\User;

class Shell
{
    private int $tournamentId;
    private int $singleCustomSport;
    private string $name;
    private DateTimeImmutable $startDateTime;
    private int $roles;
    private bool $public;

    public function __construct(Tournament $tournament, User $user = null)
    {
        $this->tournamentId = (int)$tournament->getId();
        $competition = $tournament->getCompetition();
        $league = $competition->getLeague();
        $this->singleCustomSport = 0;
        if (!$competition->hasMultipleSports()) {
            $this->singleCustomSport = $competition->getSingleSport()->getSport()->getCustomId();
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

    public function getSingleCustomSport(): int
    {
        return $this->singleCustomSport;
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
