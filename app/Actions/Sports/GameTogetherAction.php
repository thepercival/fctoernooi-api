<?php

declare(strict_types=1);

namespace App\Actions\Sports;

use App\Response\ErrorResponse;
use Psr\Log\LoggerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use JMS\Serializer\SerializerInterface;
use Sports\Score\Together\Repository as TogetherScoreRepository;
use Sports\Game\Together\Repository as TogetherGameRepository;
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

final class GameTogetherAction extends Action
{

    public function __construct(
        LoggerInterface $logger,
        SerializerInterface $serializer,
        protected TogetherGameRepository $gameRepos,
        protected PouleRepository $pouleRepos,
        protected PlaceRepository $placeRepos,
        protected StructureRepository $structureRepos,
        protected TogetherScoreRepository $scoreRepos
    ) {
        parent::__construct($logger, $serializer);
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

            $queryParams = $request->getQueryParams();
            $pouleId = 0;
            if (array_key_exists("pouleId", $queryParams) && strlen($queryParams["pouleId"]) > 0) {
                $pouleId = (int)$queryParams["pouleId"];
            }
            $poule = $this->getPouleFromInput($pouleId, $competition);
            $initialPouleState = $poule->getState();

            /** @var TogetherGame $gameSer */
            $gameSer = $this->serializer->deserialize($this->getRawData(), TogetherGame::class, 'json');

            $game = $this->gameRepos->find((int)$args["gameId"]);
            if ($game === null) {
                throw new \Exception("de pouleplek kan niet gevonden worden o.b.v. id", E_ERROR);
            }
            if ($game->getPoule() !== $poule) {
                throw new \Exception("de poule van de pouleplek komt niet overeen met de verstuurde poule", E_ERROR);
            }

            foreach ($game->getPlaces() as $gamePlace) {
                $this->scoreRepos->removeScores($gamePlace);
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
                $gameScoreCreator->addTogetherScores($gamePlace, $getSerGamePlace($gamePlace)->getScores()->toArray());
            }

            $this->gameRepos->save($game);

            $changedPlaces = $this->getChangedQualifyPlaces($competition, $game->getPoule(), $initialPouleState);
            foreach ($changedPlaces as $changedPlace) {
                $this->placeRepos->save($changedPlace);
                foreach ($changedPlace->getGames() as $gameIt) {
                    $gameIt->setState(State::Created);
                    $this->gameRepos->save($gameIt);
                    foreach ($gameIt->getPlaces() as $gamePlace) {
                        $this->scoreRepos->removeScores($gamePlace);
                    }
                }
            }

            $json = $this->serializer->serialize($game, 'json');
            return $this->respondWithJson($response, $json);
        } catch (\Exception $exception) {
            return new ErrorResponse($exception->getMessage(), 422);
        }
    }

    protected function getPouleFromInput(int $pouleId, Competition $competition): Poule
    {
        $poule = $this->pouleRepos->find($pouleId);
        if ($poule === null) {
            throw new \Exception("er kan poule worden gevonden o.b.v. de invoergegevens", E_ERROR);
        }
        if ($poule->getRound()->getNumber()->getCompetition() !== $competition) {
            throw new \Exception("de competitie van de poule komt niet overeen met de verstuurde competitie", E_ERROR);
        }
        return $poule;
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
        return $qualifyService->setQualifiers($pouleToFilter);
    }

    protected function shouldQualifiersBeCalculated(Poule $poule, int $originalPouleState): bool
    {
        return !($originalPouleState !== State::Finished && $poule->getState() !== State::Finished);
    }

    protected function shouldQualifiersBeCalculatedForRound(Poule $poule): bool
    {
        foreach ($poule->getRound()->getQualifyGroups() as $qualifyGroup) {
            if ($qualifyGroup->getNrOfToPlacesTooMuch() > 0) {
                return true;
            }
        }
        return false;
    }
}
