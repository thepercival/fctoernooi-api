<?php

declare(strict_types=1);

namespace App\Actions\Sports\Planning;

use App\Actions\Action;
use App\Response\ErrorResponse;
use Exception;
use FCToernooi\Tournament;
use JMS\Serializer\SerializerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Sports\Planning\Config as PlanningConfig;
use Sports\Planning\Config\Repository as PlanningConfigRepository;
use Sports\Planning\Config\Service as PlanningConfigService;
use Sports\Round\Number as RoundNumber;
use Sports\Structure\Repository as StructureRepository;

final class ConfigAction extends Action
{
    protected PlanningConfigRepository $planningConfigRepos;
    protected PlanningConfigService $planningConfigService;
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
        $this->planningConfigService = new PlanningConfigService();
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
            /** @var PlanningConfig $planningConfigSer */
            $planningConfigSer = $this->serializer->deserialize($this->getRawData($request), PlanningConfig::class, 'json');

            $structure = $this->structureRepos->getStructure($competition);
            $roundNumber = $structure->getRoundNumber((int)$args['roundNumber']);
            if ($roundNumber === null) {
                throw new Exception('geen rondenummer gevonden', E_ERROR);
            }

            $oldPlanningConfig = $roundNumber->getPlanningConfig();
            $planningConfig = $this->planningConfigService->copy($planningConfigSer, $roundNumber);

            $this->planningConfigRepos->save($planningConfig);

            if ($oldPlanningConfig !== null) {
                $this->planningConfigRepos->remove($oldPlanningConfig);
            }
            $this->removeNext($roundNumber);

            $json = $this->serializer->serialize(true, 'json');
            return $this->respondWithJson($response, $json);
        } catch (Exception $exception) {
            return new ErrorResponse($exception->getMessage(), 422);
        }
    }

    protected function removeNext(RoundNumber $roundNumber): void
    {
        $next = $roundNumber->getNext();
        if ($next === null) {
            return;
        }
        $planningConfig = $next->getPlanningConfig();
        if ($planningConfig !== null) {
            $next->setPlanningConfig(null);
            $this->planningConfigRepos->remove($planningConfig);
        }
        $this->removeNext($next);
    }
}
