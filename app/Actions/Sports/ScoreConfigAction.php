<?php

declare(strict_types=1);

namespace App\Actions\Sports;

use App\Actions\Action;
use App\Response\ErrorResponse;
use Exception;
use FCToernooi\Tournament;
use JMS\Serializer\SerializerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Sports\Competition;
use Sports\Competition\Sport as CompetitionSport;
use Sports\Competition\Sport\Repository as CompetitionSportRepository;
use Sports\Round;
use Sports\Score\Config as ScoreConfig;
use Sports\Score\Config\Repository as ScoreConfigRepository;
use Sports\Structure;
use Sports\Structure\Repository as StructureRepository;

final class ScoreConfigAction extends Action
{
    public function __construct(
        LoggerInterface $logger,
        SerializerInterface $serializer,
        protected CompetitionSportRepository $competiionSportRepos,
        protected StructureRepository $structureRepos,
        protected ScoreConfigRepository $scoreConfigRepos
    ) {
        parent::__construct($logger, $serializer);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param array<string, int|string> $args
     * @return Response
     */
    public function save(Request $request, Response $response, array $args): Response
    {
        try {
            /** @var Tournament $tournament */
            $tournament = $request->getAttribute("tournament");

            $competition = $tournament->getCompetition();

            /** @var ScoreConfig $scoreConfigSer */
            $scoreConfigSer = $this->serializer->deserialize($this->getRawData($request), ScoreConfig::class, 'json');

            $argRoundId = isset($args['roundId']) ? $args['roundId'] : null;
            if (!is_string($argRoundId) || strlen($argRoundId) === 0) {
                throw new \Exception('geen ronde opgegeven', E_ERROR);
            }
            $structure = $this->structureRepos->getStructure($competition);
            $round = $this->getRound($structure, (int)$argRoundId);

            $argCompetitionSportId = isset($args['competitionSportId']) ? $args['competitionSportId'] : null;
            if (!is_string($argCompetitionSportId) || strlen($argCompetitionSportId) === 0) {
                throw new \Exception('geen sport opgegeven', E_ERROR);
            }
            $competitionSport = $this->competiionSportRepos->find((int)$argCompetitionSportId);
            if ($competitionSport === null) {
                throw new \Exception('de sport kon niet gevonden worden', E_ERROR);
            }
            $scoreConfig = $round->getScoreConfig($competitionSport);
            if ($scoreConfig === null) {
                $scoreConfig = new ScoreConfig(
                    $competitionSport,
                    $round,
                    $scoreConfigSer->getDirection(),
                    $scoreConfigSer->getMaximum(),
                    $scoreConfigSer->getEnabled()
                );
                $nextSer = $scoreConfigSer->getNext();
                if ($nextSer !== null) {
                    new ScoreConfig(
                        $competitionSport,
                        $round,
                        $nextSer->getDirection(),
                        $nextSer->getMaximum(),
                        $nextSer->getEnabled(),
                        $scoreConfig
                    );
                }
            } else {
                $scoreConfig->setMaximum($scoreConfigSer->getMaximum());
                $scoreConfig->setEnabled($scoreConfigSer->getEnabled());
                $next = $scoreConfig->getNext();
                $nextSer = $scoreConfigSer->getNext();
                if ($next !== null && $nextSer !== null) {
                    $next->setMaximum($nextSer->getMaximum());
                    $next->setEnabled($nextSer->getEnabled());
                }
            }

            $this->scoreConfigRepos->save($scoreConfig);

            $this->removeNext($round, $competitionSport);

            $json = $this->serializer->serialize($scoreConfig, 'json');
            return $this->respondWithJson($response, $json);
        } catch (\Exception $exception) {
            return new ErrorResponse($exception->getMessage(), 422);
        }
    }

    protected function removeNext(Round $round, CompetitionSport $competitionSport): void
    {
        foreach ($round->getChildren() as $childRound) {
            $scoreConfig = $childRound->getScoreConfig($competitionSport);
            if ($scoreConfig === null) {
                continue;
            }
            $childRound->getScoreConfigs()->removeElement($scoreConfig);
            $this->scoreConfigRepos->remove($scoreConfig);
            $this->removeNext($childRound, $competitionSport);
        }
    }

    protected function getRound(Structure $structure, int $roundId): Round
    {
        $getRound = function (Round $round) use ($roundId, &$getRound): ?Round {
            if ($round->getId() === $roundId) {
                return $round;
            }
            foreach ($round->getChildren() as $childRound) {
                $retVal = $getRound($childRound);
                if ($retVal !== null) {
                    return $retVal;
                }
            }
            return null;
        };
        $round = $getRound($structure->getRootRound());
        if ($round === null) {
            throw new Exception("de ronde kan niet gevonden worden", E_ERROR);
        }
        return $round;
    }
}
