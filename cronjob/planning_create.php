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
require __DIR__ . '/helpers/console.php';

use Monolog\Logger;
use Voetbal\Planning;
use Voetbal\Planning\Input as PlanningInput;
use Voetbal\Planning\Service as PlanningService;
use Voetbal\Planning\Repository as PlanningRepository;
use Voetbal\Planning\Input\Repository as PlanningInputRepository;

$settings = $app->getContainer()->get('settings');
$em = $app->getContainer()->get('em');
$voetbal = $app->getContainer()->get('voetbal');
$planningRepos = $voetbal->getRepository( \Voetbal\Planning::class );
$planningInputRepos = $voetbal->getRepository( \Voetbal\Planning\Input::class );

$logger = new Logger('planning-create');
$output = 'php://stdout';
if( $settings['environment'] !== 'development' ) {
    $output = $settings['logger']['cronjobpath'] . 'planning_create.log';
    $logger->pushProcessor(new \Monolog\Processor\UidProcessor());
}
$handler = new \Monolog\Handler\StreamHandler($output, $settings['logger']['level']);
$logger->pushHandler( $handler );

try {
    if( count($argv) !== 2 ) {
        throw new \Exception("first parameter must be intervalMinutes"
            , E_ERROR);
    }

    $intervalMinutes = filter_var($argv[1], FILTER_VALIDATE_INT);
    if( $intervalMinutes === false ) {
        throw new \Exception("first parameter intervalMinutes must be a number", E_ERROR);
    }
    if( $intervalMinutes < 10 ) {
        throw new \Exception("intervalMinutes should be at least 10 minutes", E_ERROR);
    }

    $startDate = new \DateTimeImmutable();

    $logger->info( "start job at " . $startDate->format("Y-m-d H:i") );
    $nNrOfSecondsWithMargin = ($intervalMinutes * 60) * 0.9;
    $endDate = $startDate->modify( "+ " . $nNrOfSecondsWithMargin . " seconds" );

    while( (new \DateTimeImmutable()) < $endDate ) {
        if( $planningRepos->isProcessing() ) {
            $logger->info( "still processing, sleeping 10 seconds.." );
            sleep(10);
            continue;
        }
        processPlanning( $planningRepos, $planningInputRepos, $logger );
        sleep(1);
        $logger->info( "sleeping 1 seconds.." );
    }
    $endDate = new \DateTimeImmutable();
    $logger->info( "end job at " . $endDate->format("Y-m-d H:i") . ' which started at ' . $startDate->format("Y-m-d H:i") );
}
catch ( Exception $e ) {
    echo $e->getMessage() . PHP_EOL;
}

function processPlanning( PlanningRepository $planningRepos, PlanningInputRepository $planningInputRepos, Logger $logger )
{
    $inputPlanning = $planningInputRepos->getFirstUnsuccessful();
    if( $inputPlanning === null ) {
        throw new \Exception("all plannings are succesfull", E_ERROR );
    }
    $planningToTry = $planningRepos->createNextTry( $inputPlanning );
    if ($planningToTry === null ) {
        if( !$inputPlanning->hasPlanning( Planning::STATE_SUCCESS_PARTIAL ) ) {
            $logger->warning( 'no success plannings which can not occure??' );
         } else if ( $inputPlanning->getState() !== PlanningInput::STATE_FAILED ){
            $logger->warning( 'the planninginput is already successful, no action done' );
        } else {
            $inputPlanning->setState( PlanningInput::STATE_SUCCESS );
            $planningInputRepos->save( $inputPlanning );
            $logger->info( "updating inputstate failed to success" );
        }
        return;
    }
    processPlanningHelper( $planningToTry, $planningRepos, $planningInputRepos, $logger );
}

function processPlanningHelper( Planning $planning, PlanningRepository $planningRepos, PlanningInputRepository $planningInputRepos, Logger $logger )
{
    $planning->setState( Planning::STATE_PROCESSING );
    $planningRepos->save( $planning );
    $inputPlanning = $planning->getInput();
    $logger->info(
        'trying nrOfCompetitors ' . $planning->getStructure()->getNrOfPlaces()
        . ', structure [' . implode( '|', $inputPlanning->getStructureConfig()) . ']'
        . ', sports ' . count( $inputPlanning->getSportConfig())
        . ', referees ' . $inputPlanning->getNrOfReferees()
        . ', fields ' . $inputPlanning->getNrOfFields()
        . ', nrOfH2h ' . $inputPlanning->getNrOfHeadtohead()
        . ', teamup ' . ( $inputPlanning->getTeamup() ? '1' : '0' )
        . ', selfRef ' . ( $inputPlanning->getSelfReferee() ? '1' : '0' )
        . ', batchGames ' . $planning->getNrOfBatchGames()->min . '->' . $planning->getNrOfBatchGames()->max
        . ', gamesInARow ' . $planning->getMaxNrOfGamesInARow()
        . ', timeout ' . $planning->getTimeoutSeconds()
        . " .. " );

    $planningService = new PlanningService();

    $newState = $planningService->createGames( $planning );

    $planning->setState( $newState );
    $planningRepos->save( $planning );

    $logger->info(
        (  $planning->getState() === Planning::STATE_FAILED ? "failed" :
            ( $planning->getState() === Planning::STATE_TIMEOUT ? "timeout(".$planning->getTimeoutSeconds().")" : "success" )
        )
    );

    if( $planning->getState() === Planning::STATE_SUCCESS_PARTIAL ) {
        $sortedGames = $planning->getGames()->toArray();
        uasort( $sortedGames, function( $g1, $g2 ) { return $g1->getBatchNr() - $g2->getBatchNr(); } );
        consoleGames( $logger, $sortedGames );
    } else {
        $r = "failed";
    }
}



