<?php

declare(strict_types=1);

namespace App\Actions\Sports;

use App\QueueService;
use App\Response\ErrorResponse;
use Psr\Log\LoggerInterface;
use JMS\Serializer\SerializerInterface;
use App\Actions\Action;
use Doctrine\ORM\EntityManager;
use JMS\Serializer\Serializer;
use League\Period\Period;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Selective\Config\Configuration;
use SportsPlanning\Planning\Repository as PlanningRepository;
use Sports\Round\Number as RoundNumber;
use Sports\Round\Number\Repository as RoundNumberRepository;
use Sports\Structure\Repository as StructureRepository;
use SportsPlanning\Input\Repository as InputRepository;
use App\Actions\Sports\Deserialize\RefereeService as DeserializeRefereeService;
use Sports\Round\Number\PlanningCreator as RoundNumberPlanningCreator;

final class PlanningAction extends Action
{
    /**
     * @var PlanningRepository
     */
    protected $repos;
    /**
     * @var InputRepository
     */
    protected $inputRepos;
    /**
     * @var StructureRepository
     */
    protected $structureRepos;
    /**
     * @var RoundNumberRepository
     */
    protected $roundNumberRepos;
    /**
     * @var DeserializeRefereeService
     */
    protected $deserializeRefereeService;
    /**
     * @var Configuration
     */
    protected $config;

    public function __construct(
        LoggerInterface $logger,
        SerializerInterface $serializer,
        PlanningRepository $repos,
        InputRepository $inputRepos,
        StructureRepository $structureRepos,
        RoundNumberRepository $roundNumberRepos,
        Configuration $config
    ) {
        parent::__construct($logger, $serializer);

        $this->repos = $repos;
        $this->inputRepos = $inputRepos;
        $this->structureRepos = $structureRepos;
        $this->roundNumberRepos = $roundNumberRepos;
        $this->serializer = $serializer;
        $this->deserializeRefereeService = new DeserializeRefereeService();
        $this->config = $config;
    }

    public function fetch(Request $request, Response $response, $args): Response
    {
        list($structure, $roundNumber) = $this->getFromRequest($request, $args);
        $json = $this->serializer->serialize($structure, 'json');
        return $this->respondWithJson($response, $json);
    }

    /**
     * do game remove and add for multiple games
     *
     */
    public function create(Request $request, Response $response, $args): Response
    {
        /** @var \FCToernooi\Tournament $tournament */
        $tournament = $request->getAttribute('tournament');

        try {
            list($structure, $startRoundNumber) = $this->getFromRequest($request, $args);

            $roundNumberPlanningCreator = new RoundNumberPlanningCreator(
                $this->inputRepos,
                $this->repos,
                $this->roundNumberRepos
            );
            $roundNumberPlanningCreator->removeFrom($startRoundNumber);
            $queueService = new QueueService($this->config->getArray('queue'));
            $roundNumberPlanningCreator->addFrom($queueService, $startRoundNumber, $tournament->getBreak());

            $json = $this->serializer->serialize($structure, 'json');
            return $this->respondWithJson($response, $json);
        } catch (\Exception $exception) {
            return new ErrorResponse($exception->getMessage(), 422);
        }
    }

    /**
     * do game remove and add for multiple games
     */
    public function reschedule(Request $request, Response $response, $args): Response
    {
        /** @var \FCToernooi\Tournament $tournament */
        $tournament = $request->getAttribute('tournament');

        try {
            list($structure, $roundNumber) = $this->getFromRequest($request, $args);
            $scheduler = new RoundNumber\PlanningScheduler($tournament->getBreak());
            $dates = $scheduler->rescheduleGames($roundNumber);

            $this->roundNumberRepos->savePlanning($roundNumber);

            $json = $this->serializer->serialize($dates, 'json');
            return $this->respondWithJson($response, $json);
        } catch (\Exception $exception) {
            return new ErrorResponse($exception->getMessage(), 422);
        }
    }

    protected function getFromRequest(Request $request, $args): array
    {
        $competition = $request->getAttribute('tournament')->getCompetition();
        if (array_key_exists('roundNumber', $args) === false) {
            throw new \Exception('geen rondenummer opgegeven', E_ERROR);
        }
        $structure = $this->structureRepos->getStructure($competition);
        if ($structure === null) {
            throw new \Exception('geen structuur opgegeven', E_ERROR);
        }
        $roundNumber = $structure->getRoundNumber((int)$args['roundNumber']);
        return [$structure, $roundNumber];
    }
}
