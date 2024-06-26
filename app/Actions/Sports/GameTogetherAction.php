<?php

declare(strict_types=1);

namespace App\Actions\Sports;

use App\Response\ErrorResponse;
use Exception;
use FCToernooi\Tournament;
use JMS\Serializer\SerializerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Sports\Competition\Sport as CompetitionSport;
use Sports\Competition\Sport\Repository as CompetitionSportRepository;
use Sports\Game\Place\Together as TogetherGamePlace;
use Sports\Game\Together as TogetherGame;
use Sports\Game\Together\Repository as TogetherGameRepository;
use Sports\Game\Against\Repository as AgainstGameRepository;
use Sports\Place\Repository as PlaceRepository;
use Sports\Planning\EditMode as PlanningEditMode;
use Sports\Poule;
use Sports\Poule\Repository as PouleRepository;
use Sports\Score\Against\Repository as AgainstScoreRepository;
use Sports\Score\Creator as GameScoreCreator;
use Sports\Score\Together\Repository as TogetherScoreRepository;
use Sports\Structure\Repository as StructureRepository;

final class GameTogetherAction extends GameAction
{
    public function __construct(
        LoggerInterface $logger,
        SerializerInterface $serializer,
        AgainstGameRepository $againstGameRepos,
        TogetherGameRepository $togetherGameRepos,
        PouleRepository $pouleRepos,
        PlaceRepository $placeRepos,
        StructureRepository $structureRepos,
        AgainstScoreRepository $scoreRepos,
        TogetherScoreRepository $togetherScoreRepos,
        CompetitionSportRepository $competitionSportRepos
    ) {
        parent::__construct(
            $logger,
            $serializer,
            $togetherGameRepos,
            $againstGameRepos,
            $togetherGameRepos,
            $pouleRepos,
            $placeRepos,
            $structureRepos,
            $scoreRepos,
            $togetherScoreRepos,
            $competitionSportRepos
        );
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
            /** @var Tournament $tournament */
            $tournament = $request->getAttribute("tournament");

            $competition = $tournament->getCompetition();

            $poule = $this->getPouleFromInput($request, $competition);

            $planningConfig = $poule->getRound()->getNumber()->getValidPlanningConfig();
            if ($planningConfig->getEditMode() === PlanningEditMode::Auto) {
                throw new Exception('de wedstrijd kan niet verwijderd worden omdat automatische modus aan staat', E_ERROR);
            }

            /** @var TogetherGame $gameSer */
            $gameSer = $this->serializer->deserialize($this->getRawData($request), TogetherGame::class, 'json');

            $competitionSport = $this->competitionSportRepos->find($gameSer->getCompetitionSport()->getId());
            if ($competitionSport === null) {
                throw new Exception('de sport van de wedstrijd kan niet gevonden worden', E_ERROR);
            }
            $game = $this->createGame($poule, $gameSer, $competitionSport);
            $this->addBase($game, $gameSer);
            $this->togetherGameRepos->save($game);

            $json = $this->serializer->serialize($game, 'json');
            return $this->respondWithJson($response, $json);
        } catch (Exception $exception) {
            return new ErrorResponse($exception->getMessage(), 422, $this->logger);
        }
    }

    protected function createGame(Poule $poule, TogetherGame $gameSer, CompetitionSport $competitionSport): TogetherGame
    {
        $game = new TogetherGame($poule, $gameSer->getBatchNr(), $gameSer->getStartDateTime(), $competitionSport);
        $game->setState($gameSer->getState());
        foreach ($gameSer->getPlaces() as $gamePlaceSer) {
            $place = $poule->getPlace($gamePlaceSer->getPlace()->getPlaceNr());
            new TogetherGamePlace($game, $place, $gamePlaceSer->getGameRoundNumber());
        }
        return $game;
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param array<string, int|string> $args
     * @return Response
     */
    public function edit(Request $request, Response $response, array $args): Response
    {
        try {
            /** @var Tournament $tournament */
            $tournament = $request->getAttribute("tournament");

            $competition = $tournament->getCompetition();

            $poule = $this->getPouleFromInput($request, $competition);
            $initialPouleState = $poule->getGamesState();

            /** @var TogetherGame $gameSer */
            $gameSer = $this->serializer->deserialize($this->getRawData($request), TogetherGame::class, 'json');

            /** @var TogetherGame $game */
            $game = $this->getGameFromInput($args, $poule);

            foreach ($game->getPlaces() as $gamePlace) {
                $this->togetherScoreRepos->removeScores($gamePlace);
            }

            $game->setState($gameSer->getState());
            $game->setStartDateTime($gameSer->getStartDateTime());
            $gameScoreCreator = new GameScoreCreator();
            $getSerGamePlace = function (TogetherGamePlace $gamePlace) use ($gameSer): TogetherGamePlace {
                foreach ($gameSer->getPlaces() as $gamePlaceSer) {
                    if ($gamePlaceSer->getId() === $gamePlace->getId()) {
                        return $gamePlaceSer;
                    }
                }
                throw new Exception('de wedstrijdplek kon niet gevonden worden', E_ERROR);
            };
            foreach ($game->getPlaces() as $gamePlace) {
                $scores = array_values($getSerGamePlace($gamePlace)->getScores()->toArray());
                $gameScoreCreator->addTogetherScores($gamePlace, $scores);
            }

            $this->editBase($game, $gameSer);

            $this->togetherGameRepos->save($game);

            $this->changeQualifyPlaces($competition, $game->getPoule(), $initialPouleState);

            $json = $this->serializer->serialize($game, 'json');
            return $this->respondWithJson($response, $json);
        } catch (Exception $exception) {
            return new ErrorResponse($exception->getMessage(), 422, $this->logger);
        }
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param array<string, int|string> $args
     * @return Response
     */
    public function remove(Request $request, Response $response, array $args): Response
    {
        try {
            /** @var Tournament $tournament */
            $tournament = $request->getAttribute("tournament");

            $competition = $tournament->getCompetition();

            $poule = $this->getPouleFromInput($request, $competition);

            $planningConfig = $poule->getRound()->getNumber()->getValidPlanningConfig();
            if ($planningConfig->getEditMode() === PlanningEditMode::Auto) {
                throw new Exception('de wedstrijd kan niet verwijderd worden omdat automatische modus aan staat', E_ERROR);
            }

            $game = $this->getGameFromInput($args, $poule);
            if (count($poule->getGames()) < 2) {
                throw new Exception('de wedstrijd kan niet verwijderd worden omdat het de laatste poule-wedstrijd is', E_ERROR);
            }

            /** @var TogetherGame $game */
            $poule->getTogetherGames()->removeElement($game);
            $this->togetherGameRepos->remove($game);

            return $response->withStatus(200);
        } catch (Exception $exception) {
            return new ErrorResponse($exception->getMessage(), 422, $this->logger);
        }
    }
}
