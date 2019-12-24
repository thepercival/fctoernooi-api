<?php
/**
 * Created by PhpStorm.
 * User: coen
 * Date: 14-11-17
 * Time: 14:04
 */

namespace App\Actions\Voetbal;

use App\Actions\Action;
use Doctrine\ORM\EntityManager;
use JMS\Serializer\Serializer;
use League\Period\Period;
use Voetbal\Round\Number as RoundNumber;
use Voetbal\Game\Service as GameService;
use Voetbal\Planning\ConvertService;
use Voetbal\Planning\Repository as PlanningRepository;
use Voetbal\Structure\Repository as StructureRepository;
use Voetbal\Planning\ScheduleService;
use Voetbal\Poule\Repository as PouleRepository;
use Voetbal\Competition\Repository as CompetitionRepository;
use Voetbal\Planning\Input\Service as PlanningInputService;
use Voetbal\Planning\Input\Repository as InputRepository;
use VoetbalApp\Action\PostSerialize\RefereeService as DeserializeRefereeService;

final class PlanningAction extends Action
{
    /**
     * @var GameService
     */
    protected $gameService;
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
     * @var PouleRepository
     */
    protected $pouleRepos;
    /**
     * @var CompetitionRepository
     */
    protected $competitionRepos;
    /**
     * @var Serializer
     */
    protected $serializer;
    /**
     * @var EntityManager
     */
    protected $em;
    /**
     * @var DeserializeRefereeService
     */
    protected $deserializeRefereeService;

    public function __construct(
        GameService $gameService,
        PlanningRepository $repos,
        InputRepository $inputRepos,
        StructureRepository $structureRepos,
        PouleRepository $pouleRepos,
        CompetitionRepository $competitionRepos,
        Serializer $serializer,
        EntityManager $em
    ) {
        $this->gameService = $gameService;
        $this->repos = $repos;
        $this->inputRepos = $inputRepos;
        $this->structureRepos = $structureRepos;
        $this->pouleRepos = $pouleRepos;
        $this->competitionRepos = $competitionRepos;
        $this->serializer = $serializer;
        $this->em = $em;
        $this->deserializeRefereeService = new DeserializeRefereeService();
    }

    public function fetch( $request, $response, $args)
    {
        list($structure, $roundNumber, $blockedPeriod) = $this->getFromRequest( $request );
        return $response
            ->withHeader('Content-Type', 'application/json;charset=utf-8')
            ->write( $this->serializer->serialize( $structure, 'json') );
        ;
    }

    protected function getBlockedPeriodFromInput( $request ): ?Period {
        if( $request->getParam('blockedperiodstart') === null || $request->getParam('blockedperiodend') === null ) {
            return null;
        }
        $startDateTime = \DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s.u\Z', $request->getParam('blockedperiodstart'));
        $endDateTime = \DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s.u\Z', $request->getParam('blockedperiodend'));
        return new Period( $startDateTime, $endDateTime );
    }

    /**
     * do game remove and add for multiple games
     *
     */
    public function add($request, $response, $args)
    {
        try {
            list($structure, $roundNumber, $blockedPeriod) = $this->getFromRequest( $request );

            $this->createPlanning( $roundNumber, $blockedPeriod );

            return $response
                ->withStatus(201)
                ->withHeader('Content-Type', 'application/json;charset=utf-8')
                ->write($this->serializer->serialize($structure, 'json'));
        } catch (\Exception $e) {
            return $response->withStatus(422)->write($e->getMessage());
        }
    }

    protected function createPlanning( RoundNumber $roundNumber, Period $blockedPeriod = null ) {
        $this->repos->removeRoundNumber( $roundNumber );

        $inputService = new PlanningInputService();
        $defaultPlanningInput = $inputService->get( $roundNumber );
        $planningInput = $this->inputRepos->getFromInput( $defaultPlanningInput );
        if( $planningInput === null ) {
            $planningInput = $this->inputRepos->save( $defaultPlanningInput );
        }
        $planning = $planningInput->getBestPlanning();

        $hasPlanning = false;
        if( $planning !== null ) {
            $convertService = new ConvertService(new ScheduleService($blockedPeriod));
            $convertService->createGames($roundNumber, $planning);
            $hasPlanning = true;
        }
        $this->repos->saveRoundNumber($roundNumber, $hasPlanning);
        if( $roundNumber->hasNext() ) {
            $this->createPlanning( $roundNumber->getNext(), $blockedPeriod );
        }
    }

    /**
     * do game remove and add for multiple games
     */
    public function edit($request, $response, $args)
    {
        try {
            list($structure, $roundNumber, $blockedPeriod) = $this->getFromRequest( $request );
            $scheduleService = new ScheduleService( $blockedPeriod );
            $dates = $scheduleService->rescheduleGames( $roundNumber );

            $this->repos->saveRoundNumber( $roundNumber );

            return $response
                ->withStatus(201)
                ->withHeader('Content-Type', 'application/json;charset=utf-8')
                ->write($this->serializer->serialize($dates, 'json'));;
        } catch (\Exception $e) {
            return $response->withStatus(422)->write($e->getMessage());
        }
    }

    protected function getFromRequest( $request ): array {
        /** @var \Voetbal\Competition|null $competition */
        $competition = $this->competitionRepos->find( (int) $request->getParam("competitionid") );
        if ($competition === null) {
            throw new \Exception("er kan geen competitie worden gevonden o.b.v. de invoergegevens", E_ERROR);
        }
        $roundNumberAsValue = (int)$request->getParam("roundnumber");
        if ( $roundNumberAsValue === 0 ) {
            throw new \Exception("geen rondenummer opgegeven", E_ERROR);
        }
        /** @var \Voetbal\Structure $structure */
        $structure = $this->structureRepos->getStructure( $competition );
        $roundNumber = $structure->getRoundNumber( $roundNumberAsValue );
        $blockedPeriod = $this->getBlockedPeriodFromInput( $request );

        return [$structure, $roundNumber, $blockedPeriod];
    }
}