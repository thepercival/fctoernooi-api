<?php
declare(strict_types=1);

namespace App\Actions\Sports;

use App\QueueService;
use App\Response\ErrorResponse;
use Exception;
use Psr\Log\LoggerInterface;
use JMS\Serializer\SerializerInterface;
use App\Actions\Action;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Selective\Config\Configuration;
use Sports\Planning\EditMode;
use SportsPlanning\Planning;
use SportsPlanning\Planning\Repository as PlanningRepository;
use Sports\Round\Number as RoundNumber;
use FCToernooi\Tournament;
use Sports\Round\Number\Repository as RoundNumberRepository;
use Sports\Structure\Repository as StructureRepository;
use Sports\Structure;
use SportsPlanning\Input\Repository as InputRepository;
use App\Actions\Sports\Deserialize\RefereeService as DeserializeRefereeService;
use Sports\Round\Number\PlanningCreator as RoundNumberPlanningCreator;

final class PlanningAction extends Action
{
//    private DeserializeRefereeService $deserializeRefereeService;

    public function __construct(
        LoggerInterface $logger,
        SerializerInterface $serializer,
        private PlanningRepository $repos,
        private InputRepository $inputRepos,
        private StructureRepository $structureRepos,
        private RoundNumberRepository $roundNumberRepos,
        private Configuration $config
    ) {
        parent::__construct($logger, $serializer);
//        $this->deserializeRefereeService = new DeserializeRefereeService();
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
        $json = $this->serializer->serialize($roundNumber->getPoules(), 'json');
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
        $progressPerc = 0;
        if ($input !== null) {
            $nrToBeProcessed = $input->getPlanningsWithState(Planning::STATE_TOBEPROCESSED)->count();
            $total = $input->getPlannings()->count();
            $progressPerc = (int)((($total - $nrToBeProcessed) / $total) * 100);
            if ($progressPerc === 100 && !$roundNumber->allPoulesHaveGames()) {
                $progressPerc--;
            }
        }
        return $this->respondWithJson($response, json_encode(['progress' => $progressPerc]));
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
            $roundNumberPlanningCreator->addFrom($queueService, $startRoundNumber, $tournament->getBreak());

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
        $competition = $request->getAttribute('tournament')->getCompetition();
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
        $competition = $request->getAttribute('tournament')->getCompetition();
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
            $this->roundNumberRepos->save($planningConfig);
        }

        $nextRoundNumber = $roundNumber->getNext();
        if ($nextRoundNumber !== null) {
            $this->updatePlanningEditMode($nextRoundNumber);
        }
    }
}
