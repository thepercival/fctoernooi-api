<?php
declare(strict_types=1);

namespace App\Actions\Sports;

use App\Response\ErrorResponse;
use Exception;
use Psr\Log\LoggerInterface;
use JMS\Serializer\SerializerInterface;
use Sports\Competition\Sport\Repository as CompetitionSportRepository;
use Sports\Round;
use Sports\Structure;
use Sports\Structure\Repository as StructureRepository;
use Sports\Qualify\AgainstConfig\Repository as QualifyConfigRepository;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Actions\Action;
use Sports\Competition;
use Sports\Competition\Sport as CompetitionSport;
use Sports\Qualify\AgainstConfig as AgainstQualifyConfig;

final class AgainstQualifyConfigAction extends Action
{
    public function __construct(
        LoggerInterface $logger,
        SerializerInterface $serializer,
        protected CompetitionSportRepository $competiionSportRepos,
        protected StructureRepository $structureRepos,
        protected QualifyConfigRepository $qualifyConfigRepos
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
            /** @var Competition $competition */
            $competition = $request->getAttribute('tournament')->getCompetition();

            /** @var AgainstQualifyConfig $qualifyConfigSer */
            $qualifyConfigSer = $this->serializer->deserialize($this->getRawData($request), AgainstQualifyConfig::class, 'json');

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
            $qualifyConfig = $round->getAgainstQualifyConfig($competitionSport);
            if ($qualifyConfig === null) {
                $qualifyConfig = new AgainstQualifyConfig($competitionSport, $round, $qualifyConfigSer->getPointsCalculation());
            }

            $qualifyConfig->setWinPoints($qualifyConfigSer->getWinPoints());
            $qualifyConfig->setDrawPoints($qualifyConfigSer->getDrawPoints());
            $qualifyConfig->setWinPointsExt($qualifyConfigSer->getWinPointsExt());
            $qualifyConfig->setDrawPointsExt($qualifyConfigSer->getDrawPointsExt());
            $qualifyConfig->setLosePointsExt($qualifyConfigSer->getLosePointsExt());

            $this->qualifyConfigRepos->save($qualifyConfig);

            $this->removeNext($round, $competitionSport);

            $json = $this->serializer->serialize($qualifyConfig, 'json');
            return $this->respondWithJson($response, $json);
        } catch (\Exception $exception) {
            return new ErrorResponse($exception->getMessage(), 422);
        }
    }
//
//    public function edit(Request $request, Response $response, $args): Response
//    {
//        try {
//            /** @var Competition $competition */
//            $competition = $request->getAttribute('tournament')->getCompetition();
//
//            /** @var AgainstQualifyConfig $qualifyConfigSer */
//            $qualifyConfigSer = $this->serializer->deserialize(
//                $this->getRawData($request),
//                AgainstQualifyConfig::class,
//                'json'
//            );
//
//            // $structure = $this->structureRepos->getStructure($competition); // to init next/previous
//
//            if (!array_key_exists('roundId', $args) || strlen($args['roundId']) === 0) {
//                throw new \Exception('geen ronde opgegeven', E_ERROR);
//            }
//            $structure = $this->structureRepos->getStructure($competition);
//            $round = $this->getRound($structure, (int)$args['roundId']);
//
//            if (!array_key_exists('competitionSportId', $args) || strlen($args['competitionSportId']) === 0) {
//                throw new \Exception('geen sport opgegeven', E_ERROR);
//            }
//            $competitionSport = $this->competiionSportRepos->find((int)$args['competitionSportId']);
//            if ($competitionSport === null) {
//                throw new \Exception('de sport kon niet gevonden worden', E_ERROR);
//            }
//            $qualifyConfig = $round->getAgainstQualifyConfig($competitionSport);
//            if ($qualifyConfig === null) {
//                $qualifyConfig = new AgainstQualifyConfig($competitionSport, $round);
//                // throw new \Exception('er zijn al score-instellingen aanwezig', E_ERROR);
//            }
//
//            $qualifyConfig->setWinPoints($qualifyConfigSer->getWinPoints());
//            $qualifyConfig->setDrawPoints($qualifyConfigSer->getDrawPoints());
//            $qualifyConfig->setWinPointsExt($qualifyConfigSer->getWinPointsExt());
//            $qualifyConfig->setDrawPointsExt($qualifyConfigSer->getDrawPointsExt());
//            $qualifyConfig->setLosePointsExt($qualifyConfigSer->getLosePointsExt());
//            $qualifyConfig->setPointsCalculation($qualifyConfigSer->getPointsCalculation());
//            $this->qualifyConfigRepos->save($qualifyConfig);
//
//            $this->removeNext($round, $qualifyConfig->getCompetitionSport());
//
//            $json = $this->serializer->serialize($qualifyConfig, 'json');
//            return $this->respondWithJson($response, $json);
//        } catch (\Exception $exception) {
//            return new ErrorResponse($exception->getMessage(), 422);
//        }
//    }

    protected function removeNext(Round $round, CompetitionSport $competitionSport): void
    {
        foreach ($round->getChildren() as $childRound) {
            $qualifyConfig = $childRound->getAgainstQualifyConfig($competitionSport);
            if ($qualifyConfig === null) {
                continue;
            }
            $childRound->getAgainstQualifyConfigs()->removeElement($qualifyConfig);
            $this->qualifyConfigRepos->remove($qualifyConfig);
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
            throw new Exception('de ronde kan niet gevonden worden', E_ERROR);
        }
        return $round;
    }
}
