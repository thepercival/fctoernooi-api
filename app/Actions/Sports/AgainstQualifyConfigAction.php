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
use Sports\Competition\Sport as CompetitionSport;
use Sports\Competition\Sport\Repository as CompetitionSportRepository;
use Sports\Qualify\AgainstConfig as AgainstQualifyConfig;
use Sports\Qualify\AgainstConfig\Repository as QualifyConfigRepository;
use Sports\Round;
use Sports\Structure;
use Sports\Structure\Repository as StructureRepository;

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
            /** @var Tournament $tournament */
            $tournament = $request->getAttribute("tournament");

            $competition = $tournament->getCompetition();

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
                $qualifyConfig = new AgainstQualifyConfig(
                    $competitionSport,
                    $round,
                    $qualifyConfigSer->getPointsCalculation(),
                    $qualifyConfigSer->getWinPoints(),
                    $qualifyConfigSer->getDrawPoints(),
                    $qualifyConfigSer->getWinPointsExt(),
                    $qualifyConfigSer->getDrawPointsExt(),
                    $qualifyConfigSer->getLosePointsExt()
                );
            } else {
                $qualifyConfig->setWinPoints($qualifyConfigSer->getWinPoints());
                $qualifyConfig->setDrawPoints($qualifyConfigSer->getDrawPoints());
                $qualifyConfig->setWinPointsExt($qualifyConfigSer->getWinPointsExt());
                $qualifyConfig->setDrawPointsExt($qualifyConfigSer->getDrawPointsExt());
                $qualifyConfig->setLosePointsExt($qualifyConfigSer->getLosePointsExt());
                $qualifyConfig->setPointsCalculation($qualifyConfigSer->getPointsCalculation());
            }
            $this->qualifyConfigRepos->save($qualifyConfig);

            $this->removeNext($round, $competitionSport);

            $json = $this->serializer->serialize($qualifyConfig, 'json');
            return $this->respondWithJson($response, $json);
        } catch (\Exception $exception) {
            return new ErrorResponse($exception->getMessage(), 422, $this->logger);
        }
    }

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
