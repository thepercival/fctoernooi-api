<?php

declare(strict_types=1);

namespace App\Actions\Sports;

use App\Actions\Action;
use App\Repositories\Sports\AgainstGameRepository;
use App\Repositories\Sports\AgainstScoreRepository;
use App\Repositories\Sports\StructureRepository;
use App\Repositories\Sports\TogetherGameRepository;
use App\Repositories\Sports\TogetherScoreRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Exception;
use JMS\Serializer\SerializerInterface;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Sports\Competition;
use Sports\Competition\CompetitionField;
use Sports\Competition\CompetitionReferee;
use Sports\Competition\CompetitionSport;
use Sports\Game\Against as AgainstGame;
use Sports\Game\State as GameState;
use Sports\Game\Together as TogetherGame;
use Sports\Place;
use Sports\Planning\EditMode as PlanningEditMode;
use Sports\Poule;
use Sports\Qualify\Service as QualifyService;
use Sports\Round\Number as RoundNumber;

class GameAction extends Action
{
    /** @var EntityRepository<Poule>  */
    protected EntityRepository $pouleRepos;
    /** @var EntityRepository<CompetitionSport>  */
    protected EntityRepository $competitionSportRepos;

    public function __construct(
        LoggerInterface $logger,
        SerializerInterface $serializer,
        protected TogetherGameRepository|AgainstGameRepository $gameRepos,
        protected AgainstGameRepository $againstGameRepos,
        protected TogetherGameRepository $togetherGameRepos,
        protected AgainstScoreRepository $againstScoreRepos,
        protected TogetherScoreRepository $togetherScoreRepos,
        protected StructureRepository $structureRepos,
        protected EntityManagerInterface $entityManager
    ) {
        parent::__construct($logger, $serializer);

        $metaData = $entityManager->getClassMetadata(Poule::class);
        $this->pouleRepos = new EntityRepository($entityManager, $metaData);

        $metaData = $entityManager->getClassMetadata(CompetitionSport::class);
        $this->competitionSportRepos = new EntityRepository($entityManager, $metaData);
    }

    protected function addBase(AgainstGame|TogetherGame $game, AgainstGame|TogetherGame $gameSer): void
    {
        $round = $game->getPoule()->getRound();
        $game->setState($gameSer->getState());
        $refereePlaceSer = $gameSer->getRefereePlace();
        $refereeSer = $gameSer->getReferee();
        if ($refereePlaceSer !== null) {
            $place = $round->getPlace($refereePlaceSer);
            $game->setRefereePlace($place);
        } elseif ($refereeSer !== null) {
            $referee = $this->getRefereeById($round->getCompetition(), $refereeSer);
            $game->setReferee($referee);
        }
        $fieldSer = $gameSer->getField();
        if ($fieldSer !== null) {
            $field = $this->getFieldById($game->getCompetitionSport(), $fieldSer);
            $game->setField($field);
        }
    }

    protected function editBase(AgainstGame|TogetherGame $game, AgainstGame|TogetherGame $gameSer): void
    {
        $poule = $game->getPoule();
        $roundNumber = $poule->getRound()->getNumber();
        $planningConfig = $roundNumber->getValidPlanningConfig();
        if ($planningConfig->getEditMode() !== PlanningEditMode::Manual) {
            return;
        }

        $game->setStartDateTime($gameSer->getStartDateTime());
        $refereeStructureLocation = $gameSer->getRefereeStructureLocation();
        $refereeSer = $gameSer->getReferee();
        if ($refereeStructureLocation !== null) {
            $placeMap = $this->getPlaceMap($roundNumber);
            $game->setRefereePlace($placeMap[(string)$refereeStructureLocation]);
            $game->setReferee(null);
        } elseif ($refereeSer !== null) {
            $referee = $this->getRefereeById($roundNumber->getCompetition(), $refereeSer);
            $game->setReferee($referee);
            $game->setRefereePlace(null);
        }
        $fieldSer = $gameSer->getField();
        if ($fieldSer !== null) {
            $field = $this->getFieldById($game->getCompetitionSport(), $fieldSer);
            $game->setField($field);
        }
    }

    /**
     * @param RoundNumber $roundNumber
     * @return array<string, Place>
     */
    protected function getPlaceMap(RoundNumber $roundNumber): array
    {
        $map = [];
        foreach ($roundNumber->getRounds() as $round) {
            foreach ($round->getPlaces() as $place) {
                $map[(string)$place->getStructureLocation()] = $place;
            }
        }
        return $map;
    }

    protected function getRefereeById(Competition $competition, CompetitionReferee $referee): CompetitionReferee|null
    {
        $referees = $competition->getReferees()->filter(fn (CompetitionReferee $refereeIt) => $referee->getId() === $refereeIt->getId());
        $returnReferee = $referees->first();
        return $returnReferee === false ? null : $returnReferee;
    }

    protected function getFieldById(CompetitionSport $competitionSport, CompetitionField $field): CompetitionField|null
    {
        $fields = $competitionSport->getFields()->filter(fn (CompetitionField $fieldIt) => $field->getId() === $fieldIt->getId());
        $returnField = $fields->first();
        return $returnField === false ? null : $returnField;
    }

    protected function getPouleFromInput(Request $request, Competition $competition): Poule
    {
        $queryParams = $request->getQueryParams();
        if (!array_key_exists("pouleId", $queryParams) || !is_string($queryParams["pouleId"])) {
            throw new Exception("er kan geen poule worden gevonden o.b.v. de invoergegevens", E_ERROR);
        }

        $poule = $this->pouleRepos->find((int)$queryParams["pouleId"]);
        if ($poule === null) {
            throw new Exception("er kan geen poule worden gevonden o.b.v. de invoergegevens", E_ERROR);
        }
        if ($poule->getRound()->getNumber()->getCompetition() !== $competition) {
            throw new Exception("de competitie van de poule komt niet overeen met de verstuurde competitie", E_ERROR);
        }
        return $poule;
    }

    /**
     * @param array<string, int|string> $args
     * @param Poule $poule
     * @return AgainstGame|TogetherGame
     * @throws Exception
     */
    protected function getGameFromInput(array $args, Poule $poule): AgainstGame|TogetherGame
    {
        if (!array_key_exists("gameId", $args)) {
            throw new Exception("er kan geen wedstrijd worden gevonden o.b.v. de invoergegevens", E_ERROR);
        }

        $game = $this->gameRepos->find((int)$args["gameId"]);
        if ($game === null) {
            throw new Exception("de wedstrijd kan niet gevonden worden o.b.v. id", E_ERROR);
        }
        if ($game->getPoule() !== $poule) {
            throw new Exception("de poule van de wedstrijd komt niet overeen met de verstuurde poule", E_ERROR);
        }
        return $game;
    }

    /**
     * @param Competition $competition
     * @param Poule $poule
     * @param GameState $originalPouleState
     * @return list<Place>
     */
    protected function getChangedQualifyPlaces(
        Competition $competition,
        Poule $poule,
        GameState $originalPouleState
    ): array {
        if (!$this->shouldQualifiersBeCalculated($poule, $originalPouleState)) {
            return [];
        }
        $this->structureRepos->getStructure($competition);

        $qualifyService = new QualifyService($poule->getRound());
        $pouleToFilter = $this->shouldQualifiersBeCalculatedForRound($poule) ? null : $poule;

        $nextRoundPlaces = $qualifyService->resetQualifiers($pouleToFilter);
        $this->savePlaces($nextRoundPlaces);

        return $qualifyService->setQualifiers($pouleToFilter);
    }

    protected function shouldQualifiersBeCalculated(Poule $poule, GameState $originalPouleState): bool
    {
        return !($originalPouleState !== GameState::Finished && $poule->getGamesState() !== GameState::Finished);
    }

    protected function shouldQualifiersBeCalculatedForRound(Poule $poule): bool
    {
        foreach ($poule->getRound()->getQualifyGroups() as $qualifyGroup) {
            if ($qualifyGroup->getMultipleRule() !== null) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param list<Place> $places
     * @throws Exception
     */
    protected function savePlaces(array $places): void
    {
        foreach ($places as $place) {
            $this->entityManager->persist($place);
            $this->entityManager->flush();
        }
    }

    protected function changeQualifyPlaces(Competition $competition, Poule $poule, GameState $initialPouleState): void
    {
        $changedPlaces = $this->getChangedQualifyPlaces($competition, $poule, $initialPouleState);
        foreach ($changedPlaces as $changedPlace) {
            $this->entityManager->persist($changedPlace);
            $this->entityManager->flush();
            foreach ($changedPlace->getGames() as $gameIt) {
                $gameIt->setState(GameState::Created);
                if ($gameIt instanceof AgainstGame) {
                    $gameIt->setHomeExtraPoints(0);
                    $gameIt->setAwayExtraPoints(0);
                    $this->againstGameRepos->customSave($gameIt);
                } else {
                    $this->togetherGameRepos->customSave($gameIt);
                }

                if ($gameIt instanceof AgainstGame) {
                    $this->againstScoreRepos->removeScores($gameIt);
                } else {
                    foreach ($gameIt->getPlaces() as $gamePlace) {
                        $this->togetherScoreRepos->removeScores($gamePlace);
                    }
                }
            }
        }
    }
}
