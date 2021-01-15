<?php

declare(strict_types=1);

namespace App\Actions\Sports;

use App\Response\ErrorResponse;
use Exception;
use Psr\Log\LoggerInterface;
use JMS\Serializer\SerializerInterface;
use Sports\Competition\Sport\Repository as CompetitionSportRepository;
use Sports\Round;
use Sports\Round\Number as RoundNumber;
use Sports\Sport;
use Sports\Structure;
use Sports\Structure\Repository as StructureRepository;
use Sports\Qualify\AgainstConfig\Repository as QualifyConfigRepository;
use Sports\Qualify\AgainstConfig\Service as QualifyConfigService;
use Sports\Sport\Repository as SportRepository;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Actions\Action;
use Sports\Competition;
use Sports\Competition\Sport as CompetitionSport;
use Sports\Qualify\AgainstConfig as QualifyAgainstConfig;

final class QualifyAgainstConfigAction extends Action
{
    protected CompetitionSportRepository $competiionSportRepos;
    protected StructureRepository $structureRepos;
    protected QualifyConfigRepository $qualifyConfigRepos;

    public function __construct(
        LoggerInterface $logger,
        SerializerInterface $serializer,
        CompetitionSportRepository $competiionSportRepos,
        StructureRepository $structureRepos,
        QualifyConfigRepository $qualifyConfigRepos
    ) {
        parent::__construct($logger, $serializer);
        $this->competiionSportRepos = $competiionSportRepos;
        $this->structureRepos = $structureRepos;
        $this->qualifyConfigRepos = $qualifyConfigRepos;
    }

    public function add(Request $request, Response $response, $args): Response
    {
        try {
            /** @var Competition $competition */
            $competition = $request->getAttribute("tournament")->getCompetition();

            /** @var QualifyAgainstConfig $qualifyConfigSer */
            $qualifyConfigSer = $this->serializer->deserialize($this->getRawData(), QualifyAgainstConfig::class, 'json');

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
            if ($round->getQualifyConfig($competitionSport) !== null) {
                throw new \Exception("er zijn al qualify-instellingen aanwezig", E_ERROR);
            }

            $qualifyConfig = new QualifyAgainstConfig($competitionSport, $round );
            $qualifyConfig->setWinPoints($qualifyConfigSer->getWinPoints());
            $qualifyConfig->setDrawPoints($qualifyConfigSer->getDrawPoints());
            $qualifyConfig->setWinPointsExt($qualifyConfigSer->getWinPointsExt());
            $qualifyConfig->setDrawPointsExt($qualifyConfigSer->getDrawPointsExt());
            $qualifyConfig->setLosePointsExt($qualifyConfigSer->getLosePointsExt());
            $qualifyConfig->setPointsCalculation($qualifyConfigSer->getPointsCalculation());

            $this->qualifyConfigRepos->save($qualifyConfig);

            $this->removeNext($round, $competitionSport);

            $json = $this->serializer->serialize($qualifyConfig, 'json');
            return $this->respondWithJson($response, $json);
        } catch (\Exception $e) {
            return new ErrorResponse($e->getMessage(), 422);
        }
    }

    public function edit(Request $request, Response $response, $args): Response
    {
        try {
            /** @var Competition $competition */
            $competition = $request->getAttribute("tournament")->getCompetition();

            $structure = $this->structureRepos->getStructure($competition); // to init next/previous

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

            /** @var QualifyAgainstConfig $qualifyConfigSer */
            $qualifyConfigSer = $this->serializer->deserialize(
                $this->getRawData(),
                QualifyAgainstConfig::class,
                'json'
            );

            /** @var QualifyAgainstConfig|null $qualifyConfig */
            $qualifyConfig = $this->qualifyConfigRepos->find((int)$args['qualifyconfigId']);
            if ($qualifyConfig === null) {
                throw new \Exception("er zijn geen qualify-instellingen gevonden om te wijzigen", E_ERROR);
            }

            $qualifyConfig->setWinPoints($qualifyConfigSer->getWinPoints());
            $qualifyConfig->setDrawPoints($qualifyConfigSer->getDrawPoints());
            $qualifyConfig->setWinPointsExt($qualifyConfigSer->getWinPointsExt());
            $qualifyConfig->setDrawPointsExt($qualifyConfigSer->getDrawPointsExt());
            $qualifyConfig->setLosePointsExt($qualifyConfigSer->getLosePointsExt());
            $qualifyConfig->setPointsCalculation($qualifyConfigSer->getPointsCalculation());
            $this->qualifyConfigRepos->save($qualifyConfig);

            $this->removeNext($round, $qualifyConfig->getCompetitionSport());

            $json = $this->serializer->serialize($qualifyConfig, 'json');
            return $this->respondWithJson($response, $json);
        } catch (\Exception $e) {
            return new ErrorResponse($e->getMessage(), 422);
        }
    }

    protected function removeNext(Round $round, CompetitionSport $competitionSport)
    {
        foreach( $round->getChildren() as $childRound ) {
            $qualifyConfig = $childRound->getQualifyConfig($competitionSport);
            if ($qualifyConfig === null) {
                continue;
            }
            $childRound->getQualifyConfigs()->removeElement($qualifyConfig);
            $this->qualifyConfigRepos->remove($qualifyConfig);
            $this->removeNext( $childRound, $competitionSport );
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
