<?php

namespace App\Export;

use FCToernooi\Tournament;
use Voetbal\Game;
use Voetbal\NameService;
use Voetbal\Planning\Service as PlanningService;
use Voetbal\Poule;
use Voetbal\Round;
use Voetbal\Round\Number as RoundNumber;
use Voetbal\State;
use Voetbal\Structure;

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
     * @var PlanningService
     */
    protected $planningService;
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
     * @return PlanningService
     */
    public function getPlanningService()
    {
        return $this->planningService;
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
            if ($roundNumber->getValidPlanningConfig()->getSelfReferee()) {
                $games = $roundNumber->getGames(Game::ORDER_BY_BATCH);
                if (count(
                        array_filter(
                            $games,
                            function (Game $game) {
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
            $this->nameService = new NameService();
        }
        return $this->nameService;
    }

    // public function getPouleName( Poule $poule )
    //    return $nameService->getPouleName( $poule, true );
}
