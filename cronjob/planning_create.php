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
use Voetbal\Planning;
use Voetbal\Planning\Input as PlanningInput;
use Voetbal\Planning\Poule;
use Voetbal\Planning\Service as PlanningService;
use Voetbal\Planning\Input\Service as PlanningInputService;
use Voetbal\Planning\Repository as PlanningRepository;
use Voetbal\Planning\Input\Repository as PlanningInputRepository;
use Voetbal\Game as GameBase;

$settings = $app->getContainer()->get('settings');
$em = $app->getContainer()->get('em');
$voetbal = $app->getContainer()->get('voetbal');
$planningRepos = $voetbal->getRepository( \Voetbal\Planning::class );
$planningInputRepos = $voetbal->getRepository( \Voetbal\Planning\Input::class );
$inputService = new PlanningInputService();

$logger = new Logger('planning-create');
$output = 'php://stdout';
// if( $settings['environment'] !== 'development' ) {
//    $output = $settings['logger']['cronjobpath'] . 'planning_create.log';
//    $logger->pushProcessor(new \Monolog\Processor\UidProcessor());
// }
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
        if( $planningInputRepos->isProcessing() ) {
            $logger->info( "still processing, sleeping 10 seconds.." );
            sleep(10);
            continue;
        }
        $planningInput = $planningInputRepos->getFirstUnsuccessful();
        if( $planningInput === null ) {
            $logger->info( 'processing timeout ..' );
            processPlanningTimeout( $planningRepos, $logger );
        } else {
            $logger->info( 'processing input: ' . planningInputToString( $planningInput ) . " .." );
            processPlanningInput( $planningInput, $inputService, $planningRepos, $planningInputRepos, $logger );

        }
        sleep(1);
        $logger->info( "sleeping 1 seconds.." );
        // break; @FREDDY
    }
    $endDate = new \DateTimeImmutable();
    $logger->info( "end job at " . $endDate->format("Y-m-d H:i") . ' which started at ' . $startDate->format("Y-m-d H:i") );
}
catch ( Exception $e ) {
    echo $e->getMessage() . PHP_EOL;
}

function processPlanningInput( PlanningInput $planningInput,
                               PlanningInputService $inputService, PlanningRepository $planningRepos, PlanningInputRepository $planningInputRepos,
                               Logger $logger )
{
    if( $planningInput->getState() === PlanningInput::STATE_CREATED ) {
        $planningInput->setState( PlanningInput::STATE_TRYING_PLANNINGS );
        $planningInputRepos->save( $planningInput );
        $logger->info( '   update state => STATE_TRYING_PLANNINGS' );
    }
    $planningService = new PlanningService();

    // als nog niet is, dan nieuwe aanmaken
    $minIsMaxPlanning = $planningService->getMinIsMax( $planningInput, Planning::STATE_SUCCESS );
    if( $minIsMaxPlanning === null ) {
        $minIsMaxPlanning = $planningService->createNextMinIsMaxPlanning( $planningInput );
        processPlanningHelper( $minIsMaxPlanning, $planningRepos, false, $logger );
        return processPlanningInput( $planningInput, $inputService, $planningRepos, $planningInputRepos, $logger );
    }

    $planningMaxPlusOne = null;
    if( $minIsMaxPlanning->getMaxNrOfBatchGames() < $minIsMaxPlanning->getInput()->getMaxNrOfBatchGames() ) {
        $planningMaxPlusOne = $planningService->getPlusOnePlanning( $minIsMaxPlanning );
        if( $planningMaxPlusOne === null ) {
            $planningMaxPlusOne = $planningService->createPlusOnePlanning( $minIsMaxPlanning );
            processPlanningHelper( $planningMaxPlusOne, $planningRepos, false, $logger );
            return processPlanningInput( $planningInput, $inputService, $planningRepos, $planningInputRepos, $logger );
        }
    }

    $planning = ($planningMaxPlusOne && $planningMaxPlusOne->getState() === Planning::STATE_SUCCESS) ? $planningMaxPlusOne : $minIsMaxPlanning;

    $planningNextInARow =  $planningService->createNextInARowPlanning( $planning );
    if( $planningNextInARow !== null ) {
        processPlanningHelper( $planningNextInARow, $planningRepos, false, $logger );
        return processPlanningInput( $planningInput, $inputService, $planningRepos, $planningInputRepos, $logger );
    }

    $planningInput->setState( PlanningInput::STATE_ALL_PLANNINGS_TRIED );
    $planningInputRepos->save( $planningInput );
    $logger->info( '   update state => STATE_ALL_PLANNINGS_TRIED' );
}

function planningInputToString( PlanningInput $planningInput ): string {
    return 'structure [' . implode( '|', $planningInput->getStructureConfig()) . ']'
        . ', sports ' . count( $planningInput->getSportConfig())
        . ', referees ' . $planningInput->getNrOfReferees()
        . ', fields ' . $planningInput->getNrOfFields()
        . ', teamup ' . ( $planningInput->getTeamup() ? '1' : '0' )
        . ', selfRef ' . ( $planningInput->getSelfReferee() ? '1' : '0' )
        . ', nrOfH2h ' . $planningInput->getNrOfHeadtohead();
}

function planningToString( Planning $planning, bool $withInput ): string {
    $output = 'batchGames ' . $planning->getNrOfBatchGames()->min . '->' . $planning->getNrOfBatchGames()->max
        . ', gamesInARow ' . $planning->getMaxNrOfGamesInARow()
        . ', timeout ' . $planning->getTimeoutSeconds();
    if( $withInput ) {
        return planningInputToString( $planning->getInput() ) . ', ' . $output;
    }
    return $output;
}

function processPlanningTimeout( PlanningRepository $planningRepos, Logger $logger )
{
    $planning = $planningRepos->getTimeout();
    if ($planning === null) {
        $logger->info("   all plannings(also timeout) are tried");
        return;
    }
    processPlanningHelper( $planning, $planningRepos, true, $logger );
    if( $planning->getState() === Planning::STATE_SUCCESS && $planning->getMaxNrOfGamesInARow() > 1 ) {
        $nextPlanning = $planning->increase( true );
        $nextPlanning->setState( Planning::STATE_TIMEOUT);
        $planningRepos->save( $nextPlanning );
    }
}

function processPlanningHelper( Planning $planning, PlanningRepository $planningRepos, bool $timeout, Logger $logger )
{
    // $planning->setState( Planning::STATE_PROCESSING );
    if( $timeout ) {
        $logger->info( '   ' . planningToString( $planning, $timeout ) . " timeout => " . $planning->getTimeoutSeconds() * Planning::TIMEOUT_MULTIPLIER  );
        $planning->setTimeoutSeconds($planning->getTimeoutSeconds() * Planning::TIMEOUT_MULTIPLIER);
        $planningRepos->save( $planning );
    }
    $output = '   ' . planningToString( $planning, $timeout ) . " trying .. ";
    try {
        $planningService = new PlanningService();
        $newState = $planningService->createGames( $planning );
        $planning->setState( $newState );
        $planningRepos->save( $planning );

        $stateDescription = $planning->getState() === Planning::STATE_FAILED ? "failed" :
            ( $planning->getState() === Planning::STATE_TIMEOUT ? "timeout(".$planning->getTimeoutSeconds().")" : "success" );

        $logger->info( $output . " => " . $stateDescription );
    } catch( \Exception $e ) {
        $logger->error( $output . " => " . $e->getMessage() );
    }

//    if( $planning->getState() === Planning::STATE_SUCCESS ) {
//        $sortedGames = $planning->getStructure()->getGames( GameBase::ORDER_BY_BATCH );
//        $planningOutput = new Voetbal\Planning\Output( $logger );
//        $planningOutput->consoleGames( $sortedGames );
//    }
}





