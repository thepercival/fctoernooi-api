<?php

declare(strict_types=1);

namespace App\Actions\Sports\Planning;

use App\Response\ErrorResponse;
use Exception;
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

/**
 * Class ConfigAction
 * @package App\Actions\Sports\Planning
 */
final class ConfigAction extends Action
{
    protected PlanningConfigRepository $planningConfigRepos;
    protected StructureRepository $structureRepos;

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

    /**
     * @param Request $request
     * @param Response $response
     * @param mixed $args
     * @return Response
     */
    public function save(Request $request, Response $response, $args): Response
    {
        try {
            /** @var Competition $competition */
            $competition = $request->getAttribute('tournament')->getCompetition();
            /** @var PlanningConfig $planningConfigSer */
            $planningConfigSer = $this->serializer->deserialize($this->getRawData(), PlanningConfig::class, 'json');

            $structure = $this->structureRepos->getStructure($competition);
            $roundNumber = $structure->getRoundNumber((int)$args['roundNumber']);
            if ($roundNumber === null) {
                throw new Exception('geen rondenummer gevonden', E_ERROR);
            }
            $planningConfig = $roundNumber->getPlanningConfig();
            if ($planningConfig === null) {
                $planningConfig = new PlanningConfig($roundNumber);
            }

            $planningConfig->setGameMode($planningConfigSer->getGameMode());
            $planningConfig->setExtension($planningConfigSer->getExtension());
            $planningConfig->setEnableTime($planningConfigSer->getEnableTime());
            $planningConfig->setMinutesPerGame($planningConfigSer->getMinutesPerGame());
            $planningConfig->setMinutesPerGameExt($planningConfigSer->getMinutesPerGameExt());
            $planningConfig->setMinutesBetweenGames($planningConfigSer->getMinutesBetweenGames());
            $planningConfig->setMinutesAfter($planningConfigSer->getMinutesAfter());
            $planningConfig->setSelfReferee($planningConfigSer->getSelfReferee());

            $this->planningConfigRepos->save($planningConfig);

            $this->removeNext($roundNumber);

            $json = $this->serializer->serialize(true, 'json');
            return $this->respondWithJson($response, $json);
        } catch (Exception $exception) {
            return new ErrorResponse($exception->getMessage(), 422);
        }
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
