<?php
declare(strict_types=1);

namespace App\Actions\Sports;

use App\Response\ErrorResponse;
use Exception;
use Psr\Log\LoggerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use JMS\Serializer\SerializerInterface;
use Sports\Competition\Field;
use Sports\Competition\Referee;
use Sports\Competition\Sport as CompetitionSport;
use Sports\Competition\Sport\Repository as CompetitionSportRepository;
use Sports\Game\Against as AgainstGame;
use Sports\Round;
use Sports\Round\Number as RoundNumber;
use Sports\Planning\EditMode as PlanningEditMode;
use Sports\Score\Together\Repository as TogetherScoreRepository;
use Sports\Score\Against\Repository as AgainstScoreRepository;
use Sports\Game\Together\Repository as TogetherGameRepository;
use Sports\Game\Against\Repository as AgainstGameRepository;
use Sports\Game\Place\Together as TogetherGamePlace;
use Sports\Place\Repository as PlaceRepository;
use Sports\Structure\Repository as StructureRepository;
use Sports\Place;
use Sports\Poule;
use Sports\Poule\Repository as PouleRepository;
use App\Actions\Action;
use Sports\Competition;
use Sports\Score\Creator as GameScoreCreator;
use Sports\Game\Together as TogetherGame;
use Sports\State;
use Sports\Qualify\Service as QualifyService;

class GameAction extends Action
{
    public function __construct(
        LoggerInterface $logger,
        SerializerInterface $serializer,
        protected TogetherGameRepository|AgainstGameRepository $gameRepos,
        protected PouleRepository $pouleRepos,
        protected PlaceRepository $placeRepos,
        protected StructureRepository $structureRepos,
        protected AgainstScoreRepository $againstScoreRepos,
        protected TogetherScoreRepository $togetherScoreRepos,
        protected CompetitionSportRepository $competitionSportRepos
    ) {
        parent::__construct($logger, $serializer);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param array<string, int|string> $args
     * @return Response
     */
    public function add(Request $request, Response $response, array $args): Response
    {
        try {
            /** @var Competition $competition */
            $competition = $request->getAttribute("tournament")->getCompetition();

            $poule = $this->getPouleFromInput($request, $competition);

            $planningConfig = $poule->getRound()->getNumber()->getValidPlanningConfig();
            if ($planningConfig->getEditMode() === PlanningEditMode::Auto) {
                throw new Exception("de wedstrijd kan niet verwijderd worden omdat automatische modus aan staat", E_ERROR);
            }

            /** @var TogetherGame $gameSer */
            $gameSer = $this->serializer->deserialize($this->getRawData($request), TogetherGame::class, 'json');

            $competitionSport = $this->competitionSportRepos->find($gameSer->getCompetitionSport()->getId());
            if ($competitionSport === null) {
                throw new Exception("de sport van de wedstrijd kan niet gevonden worden", E_ERROR);
            }
            $game = new TogetherGame($poule, $gameSer->getBatchNr(), $gameSer->getStartDateTime(), $competitionSport);
            $game->setState($gameSer->getState());
            foreach ($gameSer->getPlaces() as $gamePlaceSer) {
                $place = $poule->getPlace($gamePlaceSer->getPlace()->getPlaceNr());
                new TogetherGamePlace($game, $place, $gamePlaceSer->getGameRoundNumber());
            }
            $refereePlaceSer = $gameSer->getRefereePlace();
            $refereeSer = $gameSer->getReferee();
            if ($refereePlaceSer !== null) {
                $place = $poule->getRound()->getPlace($refereePlaceSer);
                $game->setRefereePlace($place);
            } elseif ($refereeSer !== null) {
                $referee = $this->getRefereeById($competition, $refereeSer);
                $game->setReferee($referee);
            }
            $fieldSer = $gameSer->getField();
            if ($fieldSer !== null) {
                $field = $this->getFieldById($competitionSport, $fieldSer);
                $game->setField($field);
            }
            $this->gameRepos->save($game);

            $json = $this->serializer->serialize($game, 'json');
            return $this->respondWithJson($response, $json);
        } catch (Exception $exception) {
            return new ErrorResponse($exception->getMessage(), 422);
        }
    }

    protected function editBase(AgainstGame|TogetherGame $game, AgainstGame|TogetherGame $gameSer): void
    {
        $poule = $game->getPoule();
        $roundNumber = $poule->getRound()->getNumber();
        $planningConfig = $roundNumber->getValidPlanningConfig();
        if ($planningConfig->getEditMode() === PlanningEditMode::Manual) {
            $game->setStartDateTime($gameSer->getStartDateTime());
            $refereeStructureLocation = $gameSer->getRefereeStructureLocation();
            $refereeSer = $gameSer->getReferee();
            if ($refereeStructureLocation !== null) {
                $placeMap = $this->getPlaceMap($roundNumber);
                $game->setRefereePlace($placeMap[$refereeStructureLocation]);
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
                $map[$place->getStructureLocation()] = $place;
            }
        }
        return $map;
    }

    protected function getRefereeById(Competition $competition, Referee $referee): Referee|null
    {
        $referees = $competition->getReferees()->filter(fn (Referee $refereeIt) => $referee->getId() === $refereeIt->getId());
        $returnReferee = $referees->first();
        return $returnReferee === false ? null : $returnReferee;
    }

    protected function getFieldById(CompetitionSport $competitionSport, Field $field): Field|null
    {
        $fields = $competitionSport->getFields()->filter(fn (Field $fieldIt) => $field->getId() === $fieldIt->getId());
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
     * @param int $originalPouleState
     * @return list<Place>
     */
    protected function getChangedQualifyPlaces(Competition $competition, Poule $poule, int $originalPouleState): array
    {
        if (!$this->shouldQualifiersBeCalculated($poule, $originalPouleState)) {
            return [];
        }
        $structure = $this->structureRepos->getStructure($competition);

        $qualifyService = new QualifyService($poule->getRound());
        $pouleToFilter = $this->shouldQualifiersBeCalculatedForRound($poule) ? null : $poule;

        $this->removeQualifiedPlaces($qualifyService->resetQualifiers($pouleToFilter));
        return $qualifyService->setQualifiers($pouleToFilter);
    }

    protected function shouldQualifiersBeCalculated(Poule $poule, int $originalPouleState): bool
    {
        return !($originalPouleState !== State::Finished && $poule->getState() !== State::Finished);
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
    protected function removeQualifiedPlaces(array $places): void
    {
        foreach ($places as $place) {
            $this->placeRepos->save($place);
        }
    }

    protected function changeQualifyPlaces(Competition $competition, Poule $poule, int $initialPouleState):void
    {
        $changedPlaces = $this->getChangedQualifyPlaces($competition, $poule, $initialPouleState);
        foreach ($changedPlaces as $changedPlace) {
            $this->placeRepos->save($changedPlace);
            foreach ($changedPlace->getGames() as $gameIt) {
                $gameIt->setState(State::Created);
                $this->gameRepos->save($gameIt);
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
