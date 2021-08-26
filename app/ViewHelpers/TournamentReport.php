<?php
declare(strict_types=1);

namespace App\ViewHelpers;

use \Exception;
use FCToernooi\Tournament;
use Sports\Structure;
use Sports\Structure\Validator as StructureValidator;
use Sports\Round\Number\GamesValidator;
use Sports\Round\Number as RoundNumber;
use Sports\Game\Together as TogetherGame;
use Sports\Game\Against as AgainstGame;
use Sports\Game\Order as GameOrder;

class TournamentReport
{
    public string $name;
    public string $firstRoundStructure;
    public int $nrOfRoundNumbers;
    public string|null $validateMessage = null;
    public string|null $validatePlanningMessage = null;
    public string $competitorUsage;
    public string $refereeUsage;
    public string $lockerRoomUsage;
    public string $exported;
    public string $scoresUsage;
    public string $createdDateTime;
    public string|null $publicUrl;

    public function __construct(Tournament $tournament, Structure $structure, string $publicUrl = null)
    {
        $firstRoundNumber = $structure->getFirstRoundNumber();
        $competition = $tournament->getCompetition();
        $nrOfPlaces = $firstRoundNumber->getNrOfPlaces();
        $this->name = $competition->getLeague()->getName();
        $this->publicUrl = $publicUrl;
        $this->firstRoundStructure = $nrOfPlaces . '(' . count($firstRoundNumber->getPoules()) . ')';
        ;
        $this->nrOfRoundNumbers = count($structure->getRoundNumbers());
        $this->competitorUsage = count($tournament->getCompetitors()) . '/' . $nrOfPlaces;
        $nrOfReferees = $competition->getReferees()->count();
        $this->refereeUsage = '' . $nrOfReferees;
        $nrOflockerRoomCompetitors = 0;
        foreach ($tournament->getLockerRooms() as $lockerRoom) {
            $nrOflockerRoomCompetitors += $lockerRoom->getCompetitors()->count();
        }
        $this->lockerRoomUsage = $nrOflockerRoomCompetitors . '(' . $tournament->getLockerRooms()->count() . ')';
        $this->exported = $tournament->getExported() > 0 ? 'ja' : 'nee';
        $nrOfGames = 0;
        $nrOfScores = 0;
        $this->getScoresUsage($firstRoundNumber, $nrOfGames, $nrOfScores);
        $this->scoresUsage = $nrOfScores . '/' . $nrOfGames;
        $structureValidator = new StructureValidator();
        try {
            $structureValidator->checkValidity($competition, $structure, $tournament->getPlaceRanges());
        } catch (Exception $exception) {
            $this->validateMessage = $exception->getMessage();
        }
        $gamesValidator = new GamesValidator();
        try {
            $gamesValidator->validateStructure($structure, $nrOfReferees, true, $tournament->getBreak());
        } catch (Exception $exception) {
            $this->validatePlanningMessage = $exception->getMessage();
        }
        $this->createdDateTime = $tournament->getCreatedDateTime()->format('Y-m-d H:i');
    }

    protected function getScoresUsage(RoundNumber $roundNumber, int &$nrOfGames, int &$nrOfScores): void
    {
        foreach ($roundNumber->getGames(GameOrder::ByBatch) as $game) {
            $nrOfGames++;
            if ($game instanceof AgainstGame) {
                if ($game->getScores()->count() > 0) {
                    $nrOfScores++;
                }
            } else {
                foreach ($game->getPlaces() as $gamePlace) {
                    if ($gamePlace->getScores()->count() > 0) {
                        $nrOfScores++;
                    }
                }
            }
        }
        $nextRoundNumber = $roundNumber->getNext();
        if ($nextRoundNumber !== null) {
            $this->getScoresUsage($nextRoundNumber, $nrOfGames, $nrOfScores);
        }
    }
}
