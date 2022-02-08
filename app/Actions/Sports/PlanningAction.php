<?php

declare(strict_types=1);

namespace App\Actions\Sports;

use App\Actions\Action;
use App\QueueService;
use App\Response\ErrorResponse;
use Exception;
use FCToernooi\Tournament;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\SerializerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Selective\Config\Configuration;
use Sports\Planning\Config\Repository as PlanningConfigRepository;
use Sports\Planning\EditMode;
use Sports\Round\Number as RoundNumber;
use Sports\Round\Number\PlanningCreator as RoundNumberPlanningCreator;
use Sports\Round\Number\Repository as RoundNumberRepository;
use Sports\Structure;
use Sports\Structure\Repository as StructureRepository;
use SportsPlanning\Input\Repository as InputRepository;
use SportsPlanning\Planning\Repository as PlanningRepository;

final class PlanningAction extends Action
{
    public function __construct(
        LoggerInterface $logger,
        SerializerInterface $serializer,
        private PlanningRepository $repos,
        private InputRepository $inputRepos,
        private StructureRepository $structureRepos,
        private RoundNumberRepository $roundNumberRepos,
        private PlanningConfigRepository $planningConfigRepos,
        private Configuration $config
    ) {
        parent::__construct($logger, $serializer);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param array<string, int|string> $args
     * @return Response
     * @throws Exception
     */
    public function fetch(Request $request, Response $response, array $args): Response
    {
        $roundNumber = $this->getRoundNumberFromRequest($request, $args);
        $json = $this->serializer->serialize($roundNumber->getPoules(), 'json', $this->getSerializationContext());
        return $this->respondWithJson($response, $json);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param array<string, int|string> $args
     * @return Response
     * @throws Exception
     */
    public function progress(Request $request, Response $response, array $args): Response
    {
        $roundNumber = $this->getRoundNumberFromRequest($request, $args);
        $nrOfReferees = $roundNumber->getCompetition()->getReferees()->count();
        $defaultInput = (new RoundNumber\PlanningInputCreator())->create($roundNumber, $nrOfReferees);
        $input = $this->inputRepos->getFromInput($defaultInput);
        $seekingPerc = 0;
        if ($input !== null) {
            $seekingPerc = $input->getSeekingPercentage();
        }
        if ($seekingPerc < 0) {
            $seekingPerc = 0;
        }
        $json = json_encode(['progress' => $seekingPerc]);
        return $this->respondWithJson($response, $json === false ? '' : $json);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param array<string, int|string> $args
     * @return Response
     * @throws Exception
     */
    public function create(Request $request, Response $response, array $args): Response
    {
        /** @var Tournament $tournament */
        $tournament = $request->getAttribute('tournament');

        try {
            $structure = $this->getStructureFromRequest($request, $args);
            $startRoundNumber = $this->getRoundNumberFromRequest($request, $args);

            $roundNumberPlanningCreator = new RoundNumberPlanningCreator(
                $this->inputRepos,
                $this->repos,
                $this->roundNumberRepos
            );
            $roundNumberPlanningCreator->removeFrom($startRoundNumber);
            $queueService = new QueueService($this->config->getArray('queue'));
            $roundNumberPlanningCreator->addFrom($queueService, $startRoundNumber, $tournament->getBreak(), QueueService::MAX_PRIORITY);

            $this->updatePlanningEditMode($startRoundNumber);

            $json = $this->serializer->serialize($structure, 'json');
            return $this->respondWithJson($response, $json);
        } catch (Exception $exception) {
            return new ErrorResponse($exception->getMessage(), 422);
        }
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param array<string, int|string> $args
     * @return Response
     */
    public function reschedule(Request $request, Response $response, array $args): Response
    {
        /** @var Tournament $tournament */
        $tournament = $request->getAttribute('tournament');

        try {
            $roundNumber = $this->getRoundNumberFromRequest($request, $args);
            $scheduler = new RoundNumber\PlanningScheduler($tournament->getBreak());
            $dates = $scheduler->rescheduleGames($roundNumber);

            $this->roundNumberRepos->savePlanning($roundNumber);

            $this->updatePlanningEditMode($roundNumber);

            $json = $this->serializer->serialize($dates, 'json');
            return $this->respondWithJson($response, $json);
        } catch (Exception $exception) {
            return new ErrorResponse($exception->getMessage(), 422);
        }
    }

    /**
     * @param Request $request
     * @param array<string, int|string> $args
     * @return Structure
     * @throws Exception
     */
    protected function getStructureFromRequest(Request $request, array $args): Structure
    {
        /** @var Tournament $tournament */
        $tournament = $request->getAttribute("tournament");

        $competition = $tournament->getCompetition();

        if (array_key_exists('roundNumber', $args) === false) {
            throw new Exception('geen rondenummer opgegeven', E_ERROR);
        }
        $structure = $this->structureRepos->getStructure($competition);
        return $structure;
    }

    /**
     * @param Request $request
     * @param array<string, int|string> $args
     * @return RoundNumber
     * @throws Exception
     */
    protected function getRoundNumberFromRequest(Request $request, array $args): RoundNumber
    {
        /** @var Tournament $tournament */
        $tournament = $request->getAttribute("tournament");

        $competition = $tournament->getCompetition();
        if (array_key_exists('roundNumber', $args) === false) {
            throw new Exception('geen rondenummer opgegeven', E_ERROR);
        }
        $structure = $this->structureRepos->getStructure($competition);
        $roundNumber = $structure->getRoundNumber((int)$args['roundNumber']);
        if ($roundNumber === null) {
            throw new Exception('geen rondenumber gevonden', E_ERROR);
        }
        return $roundNumber;
    }

    protected function updatePlanningEditMode(RoundNumber $roundNumber): void
    {
        $planningConfig = $roundNumber->getPlanningConfig();
        if ($planningConfig !== null && $planningConfig->getEditMode() === EditMode::Manual) {
            $planningConfig->setEditMode(EditMode::Auto);
            $this->planningConfigRepos->save($planningConfig);
        }

        $nextRoundNumber = $roundNumber->getNext();
        if ($nextRoundNumber !== null) {
            $this->updatePlanningEditMode($nextRoundNumber);
        }
    }

    protected function getSerializationContext(): SerializationContext
    {
        $serGroups = ['Default', 'games'];
        return SerializationContext::create()->setGroups($serGroups);
    }
}
