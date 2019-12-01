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
// if( $settings['environment'] !== 'development' ) {
//    $output = $settings['logger']['cronjobpath'] . 'planning_create.log';
//    $logger->pushProcessor(new \Monolog\Processor\UidProcessor());
// }
$handler = new \Monolog\Handler\StreamHandler($output, $settings['logger']['level']);
$logger->pushHandler( $handler );

$planningSeeker = new PlanningSeeker( $logger, $planningInputRepos, $planningRepos );

try {
    if( count($argv) !== 2 ) {
        throw new \Exception("first parameter must be intervalMinutes"
            , E_ERROR);
    }


//    $intervalMinutes = filter_var($argv[1], FILTER_VALIDATE_INT);
//    if( $intervalMinutes === false ) {
//        throw new \Exception("first parameter intervalMinutes must be a number", E_ERROR);
//    }
//    if( $intervalMinutes < 10 ) {
//        throw new \Exception("intervalMinutes should be at least 10 minutes", E_ERROR);
//    }
//
//    $startDate = new \DateTimeImmutable();
//
//    $logger->info( "start job at " . $startDate->format("Y-m-d H:i") );
//    $nNrOfSecondsWithMargin = ($intervalMinutes * 60) * 0.9;
//    $endDate = $startDate->modify( "+ " . $nNrOfSecondsWithMargin . " seconds" );
//
//    while( (new \DateTimeImmutable()) < $endDate ) {
        if( $planningInputRepos->isProcessing() ) {
            $logger->info( "still processing, sleeping 10 seconds.." );
            return;
        }

        $planningInput = $planningInputRepos->getFirstUnsuccessful();
        if( $planningInput === null ) {
            return;
        }

        $planningSeeker->process( $planningInput );
        $nrUpdated = addPlannigsToRoundNumbers( $planningInput, $roundNumberRepos, $tournamentRepos, $planningRepos );
        $logger->info( $nrUpdated . " roundnumber(s)-planning updated" );

//        sleep(3);
//        $logger->info( "sleeping 3 seconds.." );
//    }
//    $endDate = new \DateTimeImmutable();
//    $logger->info( "end job at " . $endDate->format("Y-m-d H:i") . ' which started at ' . $startDate->format("Y-m-d H:i") );
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