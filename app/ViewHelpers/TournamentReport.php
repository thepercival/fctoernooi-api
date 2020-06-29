<?php

namespace App\ViewHelpers;

use \Exception;
use FCToernooi\Tournament;
use Voetbal\Structure;
use Voetbal\Structure\Validator as StructureValidator;
use Voetbal\Round\Number\GamesValidator;
use Voetbal\Round\Number as RoundNumber;

class TournamentReport
{

    /**
     * @var string
     */
    public $name;
    /**
     * @var string
     */
    public $firstRoundStructure;
    /**
     * @var int
     */
    public $nrOfRoundNumbers;
    /**
     * @var string
     */
    public $validateMessage;
    /**
     * @var string
     */
    public $validatePlanningMessage;
    /**
     * @var string
     */
    public $competitorUsage;
    /**
     * @var string
     */
    public $refereeUsage;
    /**
     * @var string
     */
    public $lockerRoomUsage;
    /**
     * @var string
     */
    public $exported;
    /**
     * @var string
     */
    public $scoresUsage;
    /**
     * @var string
     */
    public $createdDateTime;
    /**
     * @var string
     */
    public $publicUrl;

    public function __construct(Tournament $tournament, Structure $structure, string $publicUrl = null)
    {
        $firstRoundNumber = $structure->getFirstRoundNumber();
        $competition = $tournament->getCompetition();
        $nrOfPlaces = $firstRoundNumber->getNrOfPlaces();
        $this->name = $competition->getLeague()->getName();
        $this->publicUrl = $publicUrl;
        $this->firstRoundStructure = $nrOfPlaces . '(' . count($firstRoundNumber->getPoules()) . ')';;
        $this->nrOfRoundNumbers = count($structure->getRoundNumbers());
        $this->competitorUsage = count($firstRoundNumber->getCompetitors()) . '/' . $nrOfPlaces;
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
            $structureValidator->checkValidity($competition, $structure);
        } catch (Exception $e) {
            $this->validateMessage = $e->getMessage();
        }
        $gamesValidator = new GamesValidator();
        try {
            $gamesValidator->validateStructure($structure, $nrOfReferees);
        } catch (Exception $e) {
            $this->validatePlanningMessage = $e->getMessage();
        }
        $this->createdDateTime = $tournament->getCreatedDateTime()->format('Y-m-d H:i');
    }

    protected function getScoresUsage(RoundNumber $roundNumber, int &$nrOfGames, int &$nrOfScores)
    {
        foreach ($roundNumber->getGames() as $game) {
            $nrOfGames++;
            if ($game->getScores()->count() > 0) {
                $nrOfScores++;
            }
        }
        if ($roundNumber->hasNext()) {
            $this->getScoresUsage($roundNumber->getNext(), $nrOfGames, $nrOfScores);
        }
    }
}