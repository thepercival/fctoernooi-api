<?php
declare(strict_types=1);

namespace App\Export;

use FCToernooi\Tournament;
use Sports\Competitor\Map as CompetitorMap;
use Sports\Game;
use Sports\Game\Together as TogetherGame;
use Sports\Game\Against as AgainstGame;
use Sports\NameService;
use Sports\Round;
use Sports\Round\Number as RoundNumber;
use Sports\State;
use Sports\Structure;

trait Document
{
    protected Tournament $tournament;
    protected Structure $structure;
    protected NameService|null $nameService = null;
    protected CompetitorMap|null $competitorMap = null;
    protected string $url;

    public function getStructure(): Structure
    {
        return $this->structure;
    }

    public function getTournament(): Tournament
    {
        return $this->tournament;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function gamesOnSameDay(RoundNumber $roundNumber): bool
    {
        $dateOne = $roundNumber->getFirstStartDateTime();
        $dateTwo = $roundNumber->getLastStartDateTime();
//        if ($dateOne === null && $dateTwo === null) {
//            return true;
//        }
        return $dateOne->format('Y-m-d') === $dateTwo->format('Y-m-d');
    }

    public function getNameService(): NameService
    {
        if ($this->nameService === null) {
            $this->nameService = new NameService($this->getPlaceLocationMap());
        }
        return $this->nameService;
    }

    public function getPlaceLocationMap(): CompetitorMap
    {
        if ($this->competitorMap === null) {
            $competitors = array_values($this->tournament->getCompetitors()->toArray());
            $this->competitorMap = new CompetitorMap($competitors);
        }
        return $this->competitorMap;
    }



    // public function getPouleName( Poule $poule )
    //    return $nameService->getPouleName( $poule, true );
}
