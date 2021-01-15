<?php

declare(strict_types=1);

namespace App\Actions\Sports\Planning;

use App\Response\ErrorResponse;
use Psr\Log\LoggerInterface;
use JMS\Serializer\SerializerInterface;
use App\Actions\Action;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Sports\Planning\Config as PlanningConfig;
use Sports\Competition;
use Sports\Planning\Config\Repository as PlanningConfigRepository;
use Sports\Round\Number as RoundNumber;
use Sports\Structure\Repository as StructureRepository;

final class ConfigAction extends Action
{
    /**
     * @var PlanningConfigRepository
     */
    protected $planningConfigRepos;
    /**
     * @var StructureRepository
     */
    protected $structureRepos;

    public function __construct(
        LoggerInterface $logger,
        SerializerInterface $serializer,
        PlanningConfigRepository $planningConfigRepos,
        StructureRepository $structureRepos
    ) {
        parent::__construct($logger, $serializer);
        $this->planningConfigRepos = $planningConfigRepos;
        $this->structureRepos = $structureRepos;
    }

    public function add(Request $request, Response $response, $args): Response
    {
        try {
            /** @var Competition $competition */
            $competition = $request->getAttribute("tournament")->getCompetition();
            /** @var PlanningConfig $planningConfigSer */
            $planningConfigSer = $this->serializer->deserialize($this->getRawData(), PlanningConfig::class, 'json');

            $structure = $this->structureRepos->getStructure($competition);
            $roundNumber = $structure->getRoundNumber((int)$args["roundnumber"]);
            if ($roundNumber === null) {
                throw new \Exception("geen rondenummer gevonden", E_ERROR);
            }
            if ($roundNumber->getPlanningConfig() !== null) {
                throw new \Exception("er is al een planningconfiguratie aanwezig", E_ERROR);
            }

            $planningConfig = new PlanningConfig($roundNumber);

            $this->updateFromSerialised( $planningConfig, $planningConfigSer );

            $this->planningConfigRepos->save($planningConfig);

            $this->removeNext($roundNumber);

            $json = $this->serializer->serialize(true, 'json');
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
            $roundNumber = $structure->getRoundNumber((int)$args["roundnumber"]);
            if ($roundNumber === null) {
                throw new \Exception("het rondenummer kan niet gevonden worden", E_ERROR);
            }

            /** @var PlanningConfig $planningConfigSer */
            $planningConfigSer = $this->serializer->deserialize($this->getRawData(), PlanningConfig::class, 'json');
            $planningConfig = $roundNumber->getPlanningConfig();
            if ($planningConfig === null) {
                throw new \Exception("er zijn geen plannings-instellingen gevonden om te wijzigen", E_ERROR);
            }

            $this->updateFromSerialised( $planningConfig, $planningConfigSer );

            $this->planningConfigRepos->save($planningConfig);

            $this->removeNext($roundNumber);

            $json = $this->serializer->serialize($planningConfig, 'json');
            return $this->respondWithJson($response, $json);
        } catch (\Exception $e) {
            return new ErrorResponse($e->getMessage(), 422);
        }
    }

    protected function updateFromSerialised( PlanningConfig $config, PlanningConfig $configSerialised ) {
        $config->setGameMode($configSerialised->getGameMode());
        $config->setExtension($configSerialised->getExtension());
        $config->setEnableTime($configSerialised->getEnableTime());
        $config->setMinutesPerGame($configSerialised->getMinutesPerGame());
        $config->setMinutesPerGameExt($configSerialised->getMinutesPerGameExt());
        $config->setMinutesBetweenGames($configSerialised->getMinutesBetweenGames());
        $config->setMinutesAfter($configSerialised->getMinutesAfter());
        $config->setSelfReferee($configSerialised->getSelfReferee());
    }

    protected function removeNext(RoundNumber $roundNumber)
    {
        while ($roundNumber->hasNext()) {
            $roundNumber = $roundNumber->getNext();
            $planningConfig = $roundNumber->getPlanningConfig();
            if ($planningConfig === null) {
                continue;
            }
            $roundNumber->setPlanningConfig(null);
            $this->planningConfigRepos->remove($planningConfig);
        }
    }
}
