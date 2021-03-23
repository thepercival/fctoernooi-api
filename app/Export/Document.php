<?php
declare(strict_types=1);

namespace App\Export;

use FCToernooi\Tournament;
use Sports\Competitor\Map as CompetitorMap;
use Sports\Game;
use Sports\NameService;
use Sports\Round;
use Sports\Round\Number as RoundNumber;
use Sports\State;
use Sports\Structure;

trait Document
{
    /**
     * @var Tournament
     */
    protected $tournament;
    /**
     * @var Structure
     */
    protected $structure;
    /**
     * @var TournamentConfig
     */
    protected $config;
    /**
     * @var bool
     */
    protected $areSelfRefereesAssigned;
    /**
     * @var NameService
     */
    protected $nameService;
    /**
     * @var CompetitorMap
     */
    protected $competitorMap;
    /**
     * @var string
     */
    protected $url;

    /**
     * @return Structure
     */
    public function getStructure()
    {
        return $this->structure;
    }

    /**
     * @return Tournament
     */
    public function getTournament()
    {
        return $this->tournament;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    protected function areSelfRefereesAssigned(): bool
    {
        if ($this->areSelfRefereesAssigned !== null) {
            return $this->areSelfRefereesAssigned;
        };
        $hasSelfRefereeHelper = function (RoundNumber $roundNumber) use (&$hasSelfRefereeHelper): bool {
            if ($roundNumber->getValidPlanningConfig()->selfRefereeEnabled()) {
                $games = $roundNumber->getGames(Game::ORDER_BY_BATCH);
                if (count(
                    array_filter(
                            $games,
                            function (Game $game): bool {
                                return $game->getRefereePlace() !== null;
                            }
                        )
                ) > 0) {
                    return true;
                }
            }
            if ($roundNumber->hasNext() === false) {
                return false;
            }
            return $hasSelfRefereeHelper($roundNumber->getNext());
        };
        $this->areSelfRefereesAssigned = $hasSelfRefereeHelper($this->structure->getFirstRoundNumber());
        return $this->areSelfRefereesAssigned;
    }

    protected function areRefereesAssigned()
    {
        return $this->tournament->getCompetition()->getReferees()->count() > 0 || $this->areSelfRefereesAssigned();
    }

    /**
     * @param Round $round
     * @param array $games
     * @return array
     */
    public function getScheduledGames(Round $round, $games = []): array
    {
        $games = array_merge($games, $round->getGamesWithState(State::Created));
        foreach ($round->getChildren() as $childRound) {
            $games = $this->getScheduledGames($childRound, $games);
        }
        return $games;
    }

    public function gamesOnSameDay(RoundNumber $roundNumber)
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
