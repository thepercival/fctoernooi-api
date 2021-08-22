<?php
declare(strict_types=1);

namespace App\Actions\Sports;

use App\Response\ErrorResponse;
use Exception;
use Psr\Log\LoggerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use JMS\Serializer\SerializerInterface;
use Sports\Score\Against\Repository as AgainstScoreRepository;
use Sports\Score\Together\Repository as TogetherScoreRepository;
use Sports\Game\Against\Repository as AgainstGameRepository;
use Sports\Place\Repository as PlaceRepository;
use Sports\Competition\Sport\Repository as CompetitionSportRepository;
use Sports\Structure\Repository as StructureRepository;
use Sports\Game\Place\Against as AgainstGamePlace;
use Sports\Poule;
use Sports\Competition\Sport as CompetitionSport;
use Sports\Poule\Repository as PouleRepository;
use Sports\Competition;
use Sports\Planning\EditMode as PlanningEditMode;
use Sports\Score\Creator as GameScoreCreator;
use Sports\Game\Against as AgainstGame;

final class GameAgainstAction extends GameAction
{
    public function __construct(
        LoggerInterface $logger,
        SerializerInterface $serializer,
        AgainstGameRepository $gameRepos,
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
            $gameRepos,
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
            /** @var Competition $competition */
            $competition = $request->getAttribute('tournament')->getCompetition();

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
            $this->gameRepos->save($game);

            $json = $this->serializer->serialize($game, 'json');
            return $this->respondWithJson($response, $json);
        } catch (Exception $exception) {
            return new ErrorResponse($exception->getMessage(), 422);
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
            /** @var Competition $competition */
            $competition = $request->getAttribute('tournament')->getCompetition();

            $poule = $this->getPouleFromInput($request, $competition);
            $initialPouleState = $poule->getState();

            /** @var AgainstGame $gameSer */
            $gameSer = $this->serializer->deserialize($this->getRawData($request), AgainstGame::class, 'json');

            /** @var AgainstGame $game */
            $game = $this->getGameFromInput($args, $poule);

            $this->againstScoreRepos->removeScores($game);

            $game->setState($gameSer->getState());

            $gameScoreCreator = new GameScoreCreator();
            $gameScoreCreator->addAgainstScores($game, array_values($gameSer->getScores()->toArray()));

            $this->editBase($game, $gameSer);

            $this->gameRepos->save($game);

            $this->changeQualifyPlaces($competition, $game->getPoule(), $initialPouleState);

            $json = $this->serializer->serialize($game, 'json');
            return $this->respondWithJson($response, $json);
        } catch (Exception $exception) {
            return new ErrorResponse($exception->getMessage(), 422);
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
            /** @var Competition $competition */
            $competition = $request->getAttribute('tournament')->getCompetition();

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
            $this->gameRepos->remove($game);

            return $response->withStatus(200);
        } catch (Exception $exception) {
            return new ErrorResponse($exception->getMessage(), 422);
        }
    }
}
