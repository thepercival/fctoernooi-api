<?php

declare(strict_types=1);

namespace App\Actions\Sports;

use App\Response\ErrorResponse;
use Exception;
use Psr\Log\LoggerInterface;
use JMS\Serializer\SerializerInterface;
use Sports\Round;
use Sports\Round\Number as RoundNumber;
use Sports\Sport;
use Sports\Structure;
use Sports\Structure\Repository as StructureRepository;
use Sports\Score\Config\Repository as ScoreConfigRepository;
use Sports\Score\Config\Service as ScoreConfigService;
use Sports\Competition\Sport\Repository as CompetitionSportRepository;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Actions\Action;
use Sports\Competition;
use Sports\Competition\Sport as CompetitionSport;
use Sports\Score\Config as ScoreConfig;

final class ScoreConfigAction extends Action
{
    protected CompetitionSportRepository $competiionSportRepos;
    protected StructureRepository $structureRepos;
    protected ScoreConfigRepository $scoreConfigRepos;

    public function __construct(
        LoggerInterface $logger,
        SerializerInterface $serializer,
        CompetitionSportRepository $competiionSportRepos,
        StructureRepository $structureRepos,
        ScoreConfigRepository $scoreConfigRepos
    ) {
        parent::__construct($logger, $serializer);
        $this->competiionSportRepos = $competiionSportRepos;
        $this->structureRepos = $structureRepos;
        $this->scoreConfigRepos = $scoreConfigRepos;
    }

    public function save(Request $request, Response $response, $args): Response
    {
        try {
            /** @var Competition $competition */
            $competition = $request->getAttribute('tournament')->getCompetition();

            /** @var ScoreConfig $scoreConfigSer */
            $scoreConfigSer = $this->serializer->deserialize($this->getRawData(), ScoreConfig::class, 'json');

            if (!array_key_exists('roundId', $args) || strlen($args['roundId']) === 0) {
                throw new \Exception('geen ronde opgegeven', E_ERROR);
            }
            $structure = $this->structureRepos->getStructure($competition);
            $round = $this->getRound($structure, (int)$args['roundId']);

            if (!array_key_exists('competitionSportId', $args) || strlen($args['competitionSportId']) === 0) {
                throw new \Exception('geen sport opgegeven', E_ERROR);
            }
            $competitionSport = $this->competiionSportRepos->find((int)$args['competitionSportId']);
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
                    $scoreConfigSer->getEnabled());
                $nextSer = $scoreConfigSer->getNext();
                if ($nextSer !== null) {
                    new ScoreConfig(
                        $competitionSport,
                        $round,
                        $nextSer->getDirection(),
                        $nextSer->getMaximum(),
                        $nextSer->getEnabled(),
                        $scoreConfig);
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

    protected function removeNext(Round $round, CompetitionSport $competitionSport)
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
        $getRound = function (Round $round) use ($roundId, &$getRound) : ?Round {
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
