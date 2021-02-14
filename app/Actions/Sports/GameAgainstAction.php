<?php

declare(strict_types=1);

namespace App\Actions\Sports;

use App\Response\ErrorResponse;
use Psr\Log\LoggerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use JMS\Serializer\SerializerInterface;
use Sports\Score\Against\Repository as AgainstScoreRepository;
use Sports\Game\Against\Repository as AgainstGameRepository;
use Sports\Place\Repository as PlaceRepository;
use Sports\Structure\Repository as StructureRepository;
use Sports\Place;
use Sports\Poule;
use Sports\Poule\Repository as PouleRepository;
use App\Actions\Action;
use Sports\Competition;
use Sports\Score\Creator as GameScoreCreator;
use Sports\Game\Against as AgainstGame;
use Sports\State;
use Sports\Qualify\Service as QualifyService;

final class GameAgainstAction extends Action
{
    protected AgainstGameRepository $gameRepos;
    protected PouleRepository $pouleRepos;
    protected PlaceRepository $placeRepos;
    protected StructureRepository $structureRepos;
    protected AgainstScoreRepository $scoreRepos;

    public function __construct(
        LoggerInterface $logger,
        SerializerInterface $serializer,
        AgainstGameRepository $gameRepos,
        PouleRepository $pouleRepos,
        PlaceRepository $placeRepos,
        StructureRepository $structureRepos,
        AgainstScoreRepository $scoreRepos
    ) {
        parent::__construct($logger, $serializer);
        $this->gameRepos = $gameRepos;
        $this->pouleRepos = $pouleRepos;
        $this->placeRepos = $placeRepos;
        $this->structureRepos = $structureRepos;
        $this->scoreRepos = $scoreRepos;
    }

    public function edit(Request $request, Response $response, $args): Response
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

            /** @var AgainstGame $gameSer */
            $gameSer = $this->serializer->deserialize($this->getRawData(), AgainstGame::class, 'json');

            $game = $this->gameRepos->find((int)$args["gameId"]);
            if ($game === null) {
                throw new \Exception("de pouleplek kan niet gevonden worden o.b.v. id", E_ERROR);
            }
            if ($game->getPoule() !== $poule) {
                throw new \Exception("de poule van de pouleplek komt niet overeen met de verstuurde poule", E_ERROR);
            }

            $this->scoreRepos->removeScores($game);

            $game->setState($gameSer->getState());
            $game->setStartDateTime($gameSer->getStartDateTime());
            $gameScoreCreator = new GameScoreCreator();
            $gameScoreCreator->addAgainstScores($game, $gameSer->getScores()->toArray());

            $this->gameRepos->save($game);

            $changedPlaces = $this->getChangedQualifyPlaces($competition, $game->getPoule(), $initialPouleState);
            foreach ($changedPlaces as $changedPlace) {
                $this->placeRepos->save($changedPlace);
                foreach ($changedPlace->getGames() as $gameIt) {
                    $gameIt->setState(State::Created);
                    $this->gameRepos->save($gameIt);
                    $this->scoreRepos->removeScores($gameIt);
                }
            }

            $json = $this->serializer->serialize($game, 'json');
            return $this->respondWithJson($response, $json);
        } catch (\Exception $e) {
            return new ErrorResponse($e->getMessage(), 422);
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
     * @return array|Place[]
     */
    protected function getChangedQualifyPlaces(Competition $competition, Poule $poule, int $originalPouleState): array
    {

        if (!$this->shouldQualifiersBeCalculated($poule, $originalPouleState)) {
            return [];
        }
        $structure = $this->structureRepos->getStructure($competition);

        $qualifyService = new QualifyService($poule->getRound(), $competition->getRankingRuleSet());
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
