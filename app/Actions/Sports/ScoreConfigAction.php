<?php

declare(strict_types=1);

namespace App\Actions\Sports;

use App\Actions\Action;
use App\Repositories\Sports\CompetitionSportRepository;
use App\Repositories\Sports\StructureRepository;
use App\Response\ErrorResponse;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use FCToernooi\Tournament;
use JMS\Serializer\SerializerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Sports\Competition\CompetitionSport;
use Sports\Round;
use Sports\Score\Config as ScoreConfig;
use Sports\Structure;

/**
 * @api
 */
final class ScoreConfigAction extends Action
{

    public function __construct(
        LoggerInterface $logger,
        SerializerInterface $serializer,
        private EntityManagerInterface $entityManager,
        protected StructureRepository $structureRepos,
        protected CompetitionSportRepository $competiionSportRepos

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

            $this->entityManager->persist($scoreConfig);
            $this->entityManager->flush();

            $this->removeNext($round, $competitionSport);

            $json = $this->serializer->serialize($scoreConfig, 'json');
            return $this->respondWithJson($response, $json);
        } catch (\Exception $exception) {
            return new ErrorResponse($exception->getMessage(), 422, $this->logger);
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
            $this->entityManager->remove($scoreConfig);
            $this->entityManager->flush();
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
        foreach ($structure->getCategories() as $category) {
            $round = $getRound($category->getRootRound());
            if ($round !== null) {
                return $round;
            }
        }
        throw new Exception('de ronde kan niet gevonden worden', E_ERROR);
    }
}
