<?php
/**
 * Created by PhpStorm.
 * User: coen
 * Date: 5-5-19
 * Time: 18:54
 */

require __DIR__ . '/../vendor/autoload.php';
$settings = require __DIR__ . '/../conf/settings.php';
$app = new \Slim\App($settings);
require __DIR__ . '/../conf/dependencies.php';
require __DIR__ . '/mailHelper.php';

use Monolog\Logger;
use Voetbal\Planning\ConvertService;
use Voetbal\Planning\Input\Service as PlanningInputService;
use Voetbal\Planning\ScheduleService;
use Voetbal\Planning\Seeker as PlanningSeeker;
use Voetbal\Planning\Input as PlanningInput;
use Voetbal\Planning\Service as PlanningService;
use Voetbal\Round\Number\Repository as RoundNumberRepository;
use Voetbal\Planning\Repository as PlanningRepository;
use FCToernooi\Tournament\Repository as TournamentRepository;
use Doctrine\ORM\EntityManager;
use Voetbal\Planning\Resources;
use Voetbal\Planning\Sport\Counter as SportCounter;
use Voetbal\Planning\Sport as PlanningSport;

//function changeResources(Resources $resources ) {
//    $resources->getSportCounters()[0]->test();
//}
//$fields = [];
//$sportsCounters = [new SportCounter( 8, [], [] )];
//$resources = new Resources( $fields, $sportsCounters );
//$copiedResources = $resources->copy();
//changeResources( $copiedResources );
//$x = $resources;
//die();

$settings = $app->getContainer()->get('settings');
$em = $app->getContainer()->get('em');
$voetbal = $app->getContainer()->get('voetbal');
$planningRepos = $voetbal->getRepository( \Voetbal\Planning::class );
$planningInputRepos = $voetbal->getRepository( \Voetbal\Planning\Input::class );
$roundNumberRepos = $voetbal->getRepository( \Voetbal\Round\Number::class );
$planningRepository = $voetbal->getRepository( \Voetbal\Planning::class );
$tournamentRepos = new TournamentRepository($em,$em->getClassMetaData(FCToernooi\Tournament::class));
$planningRepository = $voetbal->getRepository( \Voetbal\Round\Number::class );
$inputService = new PlanningInputService();

$logger = new Logger('planning-create');
$output = 'php://stdout';
  if( $settings['environment'] !== 'development' ) {
    $output = $settings['logger']['cronjobpath'] . 'planning_create.log';
    $logger->pushProcessor(new \Monolog\Processor\UidProcessor());
}
$handler = new \Monolog\Handler\StreamHandler($output, $settings['logger']['level']);
$logger->pushHandler( $handler );

$planningSeeker = new PlanningSeeker( $logger, $planningInputRepos, $planningRepos );

try {
    if( $planningInputRepos->isProcessing() ) {
        $logger->info( "still processing.." );
        return;
    }
    $planningInput = $planningInputRepos->getFirstUnsuccessful();
    if( $planningInput === null ) {
        $logger->info( "nothing to process" );
        return;
    }
    $planningSeeker->process( $planningInput );
    $nrUpdated = addPlannigsToRoundNumbers( $planningInput, $roundNumberRepos, $tournamentRepos, $planningRepos );
    $logger->info( $nrUpdated . " roundnumber(s)-planning updated" );
}
catch ( Exception $e ) {
    echo $e->getMessage() . PHP_EOL;
}

function addPlannigsToRoundNumbers( PlanningInput $planningInput, RoundNumberRepository $roundNumberRepos,
                                    TournamentRepository $tournamentRepos, PlanningRepository $planningRepos): int {
    $nrUpdated = 0;
    $roundNumbers = $roundNumberRepos->findBy( ["hasPlanning" => false ]);
    $inputService = new PlanningInputService();
    $planningService = new PlanningService();
    foreach( $roundNumbers as $roundNumber ) {
        if( !$inputService->areEqual( $inputService->get( $roundNumber ), $planningInput ) ){
            continue;
        }
        $planning =  $planningService->getBestPlanning( $planningInput );
        if( $planning === null ) {
            continue;
        }
        $tournament = $tournamentRepos->findOneBy(["competition" => $roundNumber->getCompetition() ]);
        $convertService = new ConvertService(new ScheduleService($tournament->getBreak()));
        $convertService->createGames($roundNumber, $planning);
        $planningRepos->saveRoundNumber($roundNumber, true);
        $nrUpdated++;
    }
    return $nrUpdated;
}