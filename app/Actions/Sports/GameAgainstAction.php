<?php

declare(strict_types=1);

namespace App\Actions\Sports;

use App\Repositories\Sports\AgainstGameRepository;
use App\Repositories\Sports\AgainstScoreRepository;
use App\Repositories\Sports\StructureRepository;
use App\Repositories\Sports\TogetherGameRepository;
use App\Repositories\Sports\TogetherScoreRepository;
use App\Response\ErrorResponse;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use FCToernooi\Tournament;
use JMS\Serializer\SerializerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Sports\Competition\CompetitionSport;
use Sports\Game\Against as AgainstGame;
use Sports\Game\Place\Against as AgainstGamePlace;
use Sports\Planning\EditMode as PlanningEditMode;
use Sports\Poule;
use Sports\Score\Creator as GameScoreCreator;

/**
 * @api
 */
final class GameAgainstAction extends GameAction
{
    public function __construct(
        LoggerInterface $logger,
        SerializerInterface $serializer,
        AgainstGameRepository $againstGameRepos,
        TogetherGameRepository $togetherGameRepos,
        AgainstScoreRepository $scoreRepos,
        TogetherScoreRepository $togetherScoreRepos,
        StructureRepository $structureRepos,
        EntityManagerInterface $entityManager
    ) {
        parent::__construct(
            $logger,
            $serializer,
            $againstGameRepos,
            $againstGameRepos,
            $togetherGameRepos,
            $scoreRepos,
            $togetherScoreRepos,
            $structureRepos,
            $entityManager
        );
    }

    /**
     * @psalm-suppress UnusedParam
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

            /** @var AgainstGame $gameSer */
            $gameSer = $this->serializer->deserialize($this->getRawData($request), AgainstGame::class, 'json');

            $competitionSport = $this->competitionSportRepos->find($gameSer->getCompetitionSport()->getId());
            if ($competitionSport === null) {
                throw new Exception('de sport van de wedstrijd kan niet gevonden worden', E_ERROR);
            }

            $game = $this->createGame($poule, $gameSer, $competitionSport);
            $this->addBase($game, $gameSer);
            $this->entityManager->persist($game);
            $this->entityManager->flush();

            $json = $this->serializer->serialize($game, 'json');
            return $this->respondWithJson($response, $json);
        } catch (Exception $exception) {
            return new ErrorResponse($exception->getMessage(), 422, $this->logger);
        }
    }

    protected function createGame(Poule $poule, AgainstGame $gameSer, CompetitionSport $competitionSport): AgainstGame
    {
        $game = new AgainstGame(
            $poule,
            $gameSer->getBatchNr(),
            $gameSer->getStartDateTime(),
            $competitionSport,
            $gameSer->getGameRoundNumber()
        );
        foreach ($gameSer->getPlaces() as $gamePlaceSer) {
            $place = $poule->getPlace($gamePlaceSer->getPlace()->getPlaceNr());
            new AgainstGamePlace($game, $place, $gamePlaceSer->getSide());
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

            /** @var AgainstGame $gameSer */
            $gameSer = $this->serializer->deserialize($this->getRawData($request), AgainstGame::class, 'json');

            /** @var AgainstGame $game */
            $game = $this->getGameFromInput($args, $poule);

            $this->againstScoreRepos->removeScores($game);

            $game->setState($gameSer->getState());
            $game->setHomeExtraPoints($gameSer->getHomeExtraPoints());
            $game->setAwayExtraPoints($gameSer->getAwayExtraPoints());

            $gameScoreCreator = new GameScoreCreator();
            $gameScoreCreator->addAgainstScores($game, array_values($gameSer->getScores()->toArray()));

            $this->editBase($game, $gameSer);

            $this->entityManager->persist($game);
            $this->entityManager->flush();

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

            /** @var AgainstGame $game */
            $poule->getAgainstGames()->removeElement($game);
            $this->entityManager->remove($game);
            $this->entityManager->flush();

            return $response->withStatus(200);
        } catch (Exception $exception) {
            return new ErrorResponse($exception->getMessage(), 422, $this->logger);
        }
    }
}
