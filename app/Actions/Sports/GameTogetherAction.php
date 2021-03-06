<?php
declare(strict_types=1);

namespace App\Actions\Sports;

use App\Response\ErrorResponse;
use Exception;
use Psr\Log\LoggerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use JMS\Serializer\SerializerInterface;
use Sports\Competition\Sport\Repository as CompetitionSportRepository;
use Sports\Game\Together\Repository as TogetherGameRepository;
use Sports\Score\Against\Repository as AgainstScoreRepository;
use Sports\Score\Together\Repository as TogetherScoreRepository;
use Sports\Game\Place\Together as TogetherGamePlace;
use Sports\Place\Repository as PlaceRepository;
use Sports\Structure\Repository as StructureRepository;
use Sports\Poule;
use Sports\Poule\Repository as PouleRepository;
use Sports\Competition;
use Sports\Competition\Sport as CompetitionSport;
use Sports\Score\Creator as GameScoreCreator;
use Sports\Game\Together as TogetherGame;

final class GameTogetherAction extends GameAction
{
    public function __construct(
        LoggerInterface $logger,
        SerializerInterface $serializer,
        TogetherGameRepository $gameRepos,
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
            /** @var Competition $competition */
            $competition = $request->getAttribute("tournament")->getCompetition();

            $poule = $this->getPouleFromInput($request, $competition);
            $initialPouleState = $poule->getState();

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
                throw new \Exception("de pouleplek kon niet gevonden worden", E_ERROR);
            };
            foreach ($game->getPlaces() as $gamePlace) {
                $scores = array_values($getSerGamePlace($gamePlace)->getScores()->toArray());
                $gameScoreCreator->addTogetherScores($gamePlace, $scores);
            }

            $this->editBase($game, $gameSer);

            $this->gameRepos->save($game);

            $this->changeQualifyPlaces($competition, $game->getPoule(), $initialPouleState);

            $json = $this->serializer->serialize($game, 'json');
            return $this->respondWithJson($response, $json);
        } catch (\Exception $exception) {
            return new ErrorResponse($exception->getMessage(), 422);
        }
    }
}
