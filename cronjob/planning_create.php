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

try {
    if( count($argv) !== 3 ) {
        throw new \Exception("first parameter must be intervalMinutes"
            , E_ERROR);
    }

    $intervalMinutes = filter_var($argv[2], FILTER_VALIDATE_INT);
    if( $intervalMinutes === false ) {
        throw new \Exception("first parameter intervalMinutes must be a number", E_ERROR);
    }

    $startDate = new \DateTimeImmutable();
    $nNrOfSecondsWithMargin = ($intervalMinutes * 60) * 0.9;
    $endDate = $startDate->modify( "+ " . $nNrOfSecondsWithMargin . " seconds" );

    // while( (new \DateTimeImmutable()) < $endDate ) {
        if( $planningRepos->isProcessing() ) {
            sleep(10);
            // continue;
        }
        processPlanning( $planningRepos, $planningInputRepos );
        sleep(1);
    // }
}
catch ( Exception $e ) {
    echo $e->getMessage() . PHP_EOL;
}

function processPlanning( PlanningRepository $planningRepos, PlanningInputRepository $planningInputRepos )
{
    $inputPlanning = $planningInputRepos->getFirstUnsuccessful();
    if( $inputPlanning === null ) {
        throw new \Exception("all plannings are succesfull", E_ERROR );
    }
    $planningToTry = $planningRepos->createNextTry( $inputPlanning );
    if ($planningToTry === null
        && $inputPlanning->getState() === PlanningInput::STATE_FAILED
        && $inputPlanning->hasPlanning( Planning::STATE_SUCCESS_PARTIAL )
    ) {
        $inputPlanning->setState( PlanningInput::STATE_SUCCESS );
        $planningInputRepos->save( $inputPlanning );
        echo "updating inputstate failed to success" . PHP_EOL;
        return;
    }

    processPlanningHelper( $planningToTry, $planningRepos, $planningInputRepos );

}

function processPlanningHelper( Planning $planning, PlanningRepository $planningRepos, PlanningInputRepository $planningInputRepos )
{
    $planning->setState( Planning::STATE_PROCESSING );
    $planningRepos->save( $planning );
    $inputPlanning = $planning->getInput();
    echo
        'trying nrOfCompetitors ' . $planning->getStructure()->getNrOfPlaces()
        . ', structure [' . implode( '|', $inputPlanning->getStructureConfig()) . ']'
        . ', nrOfSports ' . count( $inputPlanning->getSportConfig())
        . ', nrOfReferees ' . $inputPlanning->getNrOfReferees()
        . ', nrOfFields ' . $inputPlanning->getNrOfFields()
        . ', nrOfHeadtohead ' . $inputPlanning->getNrOfHeadtohead()
        . ', teamup ' . ( $inputPlanning->getTeamup() ? '1' : '0' )
        . ', selfReferee ' . ( $inputPlanning->getSelfReferee() ? '1' : '0' )
        . ', nrOfBatchGames ' . $planning->getNrOfBatchGames()->min . '->' . $planning->getNrOfBatchGames()->min
        . ', maxNrOfGamesInARow ' . $planning->getMaxNrOfGamesInARow()
        . ', timeout ' . $planning->getTimeoutSeconds()
        . " .. ";

    $planningService = new PlanningService();

    $newState = $planningService->createGames( $planning );

    $planning->setState( $newState );
    $planningRepos->save( $planning );

    echo
        $planning->getState() === Planning::STATE_FAILED ? "failed" :
        ( $planning->getState() === Planning::STATE_TIMEOUT ? "timeout(".$planning->getTimeoutSeconds().")" : "success" )
        . PHP_EOL;

    if( $planning->getState() === Planning::STATE_SUCCESS_PARTIAL ) {
        consoleGames( $planning->getGames()->toArray() );
    }
}



