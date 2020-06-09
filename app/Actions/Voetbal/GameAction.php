<?php
/**
 * Created by PhpStorm.
 * User: coen
 * Date: 14-11-17
 * Time: 14:02
 */

namespace App\Actions\Voetbal;

use App\Response\ErrorResponse;
use Psr\Log\LoggerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use JMS\Serializer\SerializerInterface;
use Voetbal\Game\Score\Repository as GameScoreRepository;
use Voetbal\Game\Repository as GameRepository;
use Voetbal\Place\Repository as PlaceRepository;
use Voetbal\Structure\Repository as StructureRepository;
use Voetbal\Place;
use Voetbal\Poule;
use Voetbal\Poule\Repository as PouleRepository;
use App\Actions\Action;
use Voetbal\Competition;
use Voetbal\Game\Service as GameService;
use Voetbal\Game;
use Voetbal\State;
use Voetbal\Qualify\Service as QualifyService;

final class GameAction extends Action
{
    /**
     * @var GameRepository
     */
    protected $gameRepos;
    /**
     * @var PouleRepository
     */
    protected $pouleRepos;
    /**
     * @var PlaceRepository
     */
    protected $placeRepos;
    /**
     * @var StructureRepository
     */
    protected $structureRepos;
    /**
     * @var GameScoreRepository
     */
    protected $gameScoreRepos;

    public function __construct(
        LoggerInterface $logger,
        SerializerInterface $serializer,
        GameRepository $gameRepos,
        PouleRepository $pouleRepos,
        PlaceRepository $placeRepos,
        StructureRepository $structureRepos,
        GameScoreRepository $gameScoreRepos
    ) {
        parent::__construct($logger, $serializer);
        $this->gameRepos = $gameRepos;
        $this->pouleRepos = $pouleRepos;
        $this->placeRepos = $placeRepos;
        $this->structureRepos = $structureRepos;
        $this->gameScoreRepos = $gameScoreRepos;
    }

    public function edit(Request $request, Response $response, $args): Response
    {
        try {
            /** @var \Voetbal\Competition $competition */
            $competition = $request->getAttribute("tournament")->getCompetition();

            $queryParams = $request->getQueryParams();
            $pouleId = 0;
            if (array_key_exists("pouleId", $queryParams) && strlen($queryParams["pouleId"]) > 0) {
                $pouleId = (int)$queryParams["pouleId"];
            }
            $poule = $this->getPouleFromInput($pouleId, $competition);
            $initialPouleState = $poule->getState();

            /** @var Game $gameSer */
            $gameSer = $this->serializer->deserialize($this->getRawData(), Game::class, 'json');

            $game = $this->gameRepos->find((int)$args["gameId"]);
            if ($game === null) {
                throw new \Exception("de pouleplek kan niet gevonden worden o.b.v. id", E_ERROR);
            }
            if ($game->getPoule() !== $poule) {
                throw new \Exception("de poule van de pouleplek komt niet overeen met de verstuurde poule", E_ERROR);
            }

            $this->gameScoreRepos->removeScores($game);

            $game->setState($gameSer->getState());
            $game->setStartDateTime($gameSer->getStartDateTime());
            $gameService = new GameService();
            $gameService->addScores($game, $gameSer->getScores()->toArray());

            $this->gameRepos->save($game);

            $changedPlaces = $this->getChangedQualifyPlaces($competition, $game, $initialPouleState);
            foreach ($changedPlaces as $changedPlace) {
                $this->placeRepos->save($changedPlace);
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
     * @param Game $game
     * @param int $originalPouleState
     * @return array|Place[]
     */
    protected function getChangedQualifyPlaces(Competition $competition, Game $game, int $originalPouleState): array
    {
        $poule = $game->getPoule();

        if (!$this->shouldQualifiersBeCalculated($poule, $originalPouleState)) {
            return [];
        }
        $structure = $this->structureRepos->getStructure($competition);

        $qualifyService = new QualifyService($poule->getRound(), $competition->getRuleSet());
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
