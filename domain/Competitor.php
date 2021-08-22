<?php
declare(strict_types=1);

namespace FCToernooi;

use Sports\Association;
use Sports\Competition;
use Sports\Competitor\Base;
use Sports\Competitor as SportsCompetitor;
use SportsHelpers\Identifiable;

class Competitor extends Identifiable implements SportsCompetitor
{
    protected string $name;

    use Base;

    public function __construct(protected Tournament $tournament, int $pouleNr, int $placeNr, string $name)
    {
        if (!$tournament->getCompetitors()->contains($this)) {
            $tournament->getCompetitors()->add($this) ;
        }
        $this->setName($name);
        $this->setPouleNr($pouleNr);
        $this->setPlaceNr($placeNr);
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
