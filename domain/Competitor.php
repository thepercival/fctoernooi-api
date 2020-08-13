<?php

namespace FCToernooi;

use Sports\Competition;
use Sports\Competitor\Base;
use Sports\Competitor as SportsCompetitor;

class Competitor implements SportsCompetitor
{
    /**
     * @var int|string
     */
    protected $id;
    /**
     * @var string
     */
    protected $name;
    /**
     * @var Tournament
     */
    protected $tournament;

    protected $abbreviationDep;
    protected $imageUrlDep;
    protected $associationDep;

    use Base;

    public function __construct(Tournament $tournament, int $pouleNr, int $placeNr, string $name)
    {
        $this->setTournament($tournament);
        $this->setName($name);
        $this->setPouleNr( $pouleNr );
        $this->setPlaceNr( $placeNr );
    }

    public function getTournament(): Tournament
    {
        return $this->tournament;
    }

    public function setTournament(Tournament $tournament)
    {
        if ($this->tournament === null and !$tournament->getCompetitors()->contains($this)) {
            $tournament->getCompetitors()->add($this) ;
        }
        $this->tournament = $tournament;
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
