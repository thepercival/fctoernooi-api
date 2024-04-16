<?php

declare(strict_types=1);

namespace App\Actions\Sports;

use App\Actions\Action;
use App\Exceptions\DomainRecordBeingCalculatedException;
use App\Exceptions\DomainRecordNotFoundException;
use App\GuzzleClient;
use App\Response\ErrorResponse;
use Exception;
use FCToernooi\CacheService;
use FCToernooi\Recess;
use FCToernooi\Tournament;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\SerializerInterface;
use League\Period\Period;
use Memcached;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Selective\Config\Configuration;
use Sports\Planning\Config\Repository as PlanningConfigRepository;
use Sports\Planning\EditMode;
use Sports\Round\Number as RoundNumber;
use Sports\Round\Number\PlanningAssigner;
use Sports\Round\Number\PlanningScheduler;
use Sports\Round\Number\Repository as RoundNumberRepository;
use Sports\Structure;
use Sports\Structure\Repository as StructureRepository;
use Sports\Round\Number\InputConfigurationCreator;
use SportsPlanning\Input;
use SportsPlanning\Planning;
use SportsPlanning\Referee\Info as PlanningRefereeInfo;

final class PlanningAction extends Action
{
    private GuzzleClient $planningClient;

    public function __construct(
        LoggerInterface $logger,
        SerializerInterface $serializer,
        private StructureRepository $structureRepos,
        private RoundNumberRepository $roundNumberRepos,
        private PlanningConfigRepository $planningConfigRepos,
        Configuration $config,
        Memcached $memcached
    ) {
        parent::__construct($logger, $serializer);
        $url = $config->getString('scheduler.url');
        $apikey = $config->getString('scheduler.apikey');
        $cacheService = new CacheService($memcached, $config->getString('namespace'));
        $this->planningClient = new GuzzleClient($url, $apikey, $cacheService, $serializer, $logger);

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
        try {
            $roundNumber = $this->getRoundNumberFromRequest($request, $args);
            $json = $this->serializer->serialize($roundNumber->getPoules(), 'json', $this->getSerializationContext());
            return $this->respondWithJson($response, $json);
        } catch (Exception $exception) {
            return new ErrorResponse($exception->getMessage(), 422, $this->logger);
        }
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
        try {

            $roundNumber = $this->getRoundNumberFromRequest($request, $args);

            $config = (new InputConfigurationCreator())->create(
                $roundNumber,
                new PlanningRefereeInfo($roundNumber->getRefereeInfo())
            );

            $seekingPercentage = 0;
            try {
                $jsonConfig = $this->serializer->serialize($config, 'json' );
                $seekingPercentage = $this->planningClient->getProgress($jsonConfig);
            } catch (Exception $e) {
                if( $roundNumber->getNumber() === 1 ) {
                    $input = new Input($config);
                    throw new \Exception('de planning "' . $config->getName() . '" kan niet gevonden worden, doe een aanpassing',
                        E_ERROR
                    );
                }
            }


//            $nrOfReferees = $roundNumber->getCompetition()->getReferees()->count();
            // $defaultInput = (new RoundNumber\InputConfigurationCreator())->create($roundNumber, $nrOfReferees);

//            $input = $this->inputRepos->getFromInput($defaultInput);
//            if ($input !== null) {
//                $seekingPerc =  $input->getSeekingPercentage();
//                if ($seekingPerc < 0) {
//                    $seekingPerc = 0;
//                }
//            } else {
//
//            }

            $json = json_encode(['progress' => $seekingPercentage]);
            return $this->respondWithJson($response, $json === false ? '' : $json);
        } catch (Exception $exception) {
            return new ErrorResponse($exception->getMessage(), 422, $this->logger);
        }
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

            $roundNumber = $startRoundNumber;
            while ( $roundNumber !== null ) {
                $this->roundNumberRepos->removePlanning($roundNumber);
                $roundNumber = $roundNumber->getNext();
            }

            $recessPeriods = array_values( array_map( function(Recess $recess): Period {
                return $recess->getPeriod();
            }, $tournament->getRecesses()->toArray() ) );
            $planningAssigner = new PlanningAssigner( new RoundNumber\PlanningScheduler($recessPeriods) );

            $json = '';
            try {
                $roundNumbersWithPlanning = $this->planningClient->getRoundNumbersWithPlanning(
                    $tournament->getCompetition(), $structure->getRoundNumbers(), true);
                foreach( $roundNumbersWithPlanning as $roundNumberWithPlanning ) {
                    $planningAssigner->assignPlanningToRoundNumber(
                        $roundNumberWithPlanning->roundNumber,
                        $roundNumberWithPlanning->planning,
                    );
                    $this->roundNumberRepos->savePlanning($roundNumberWithPlanning->roundNumber);
                }

                $this->updatePlanningEditMode($startRoundNumber);

                $json = $this->serializer->serialize($structure, 'json');
            } catch( DomainRecordNotFoundException $e ) {
            } catch( DomainRecordBeingCalculatedException $e ) {
            }

            return $this->respondWithJson($response, $json);
        } catch (Exception $exception) {
            return new ErrorResponse($exception->getMessage(), 422, $this->logger);
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
            $scheduler = new RoundNumber\PlanningScheduler($tournament->createRecessPeriods());
            $dates = $scheduler->rescheduleGames($roundNumber, $tournament->getCompetition()->getStartDateTime());

            $this->roundNumberRepos->savePlanning($roundNumber);

            $this->updatePlanningEditMode($roundNumber);

            $json = $this->serializer->serialize($dates, 'json');
            return $this->respondWithJson($response, $json);
        } catch (Exception $exception) {
            return new ErrorResponse($exception->getMessage(), 422, $this->logger);
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
