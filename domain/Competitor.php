<?php

declare(strict_types=1);

namespace FCToernooi;

use Sports\Competition;
use Sports\Competitor as SportsCompetitor;
use Sports\Competitor\Base;

class Competitor extends Base implements SportsCompetitor
{
    protected string $name;

    public function __construct(protected Tournament $tournament, int $pouleNr, int $placeNr, string $name)
    {
        parent::__construct($pouleNr, $placeNr);
        if (!$tournament->getCompetitors()->contains($this)) {
            $tournament->getCompetitors()->add($this);
        }
        $this->setName($name);
    }

    public function getTournament(): Tournament
    {
        return $this->tournament;
    }

    public function getCompetition(): Competition
    {
        return $this->tournament->getCompetition();
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }
}
