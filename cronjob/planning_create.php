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

use Voetbal\Planning;
use Voetbal\Planning\Service as PlanningService;
use Voetbal\Planning\Repository as PlanningRepository;
use Voetbal\Planning\Input\Repository as PlanningInputRepository;

$settings = $app->getContainer()->get('settings');
$em = $app->getContainer()->get('em');
$voetbal = $app->getContainer()->get('voetbal');
$planningRepos = $voetbal->getRepository( \Voetbal\Planning::class );
$planningInputRepos = $voetbal->getRepository( \Voetbal\Planning\Input::class );

try {
    if( count($argv) !== 2 ) {
        throw new \Exception("first parameter must be doTimeouts" .
            ", third parameter must be intervalMinutes"
            , E_ERROR);
    }
    $doTimeouts = filter_var($argv[2], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    if( $doTimeouts === null ) {
        throw new \Exception("second parameter doTimeouts must be true or false", E_ERROR);
    }
    $intervalMinutes = filter_var($argv[3], FILTER_VALIDATE_INT);
    if( $intervalMinutes === false ) {
        throw new \Exception("third parameter timoutSeconds must be a number", E_ERROR);
    }

    $startDate = new \DateTimeImmutable();
    $nNrOfSecondsWithMargin = ($intervalMinutes * 60) * 0.9;
    $endDate = $startDate->modify( "+ " . $nNrOfSecondsWithMargin . " seconds" );

    while( (new \DateTimeImmutable()) < $endDate ) {
        if( $planningRepos->isProcessing() ) {
            sleep(10);
            continue;
        }
        processPlanning( $doTimeouts, $planningRepos, $planningInputRepos );
        sleep(1);
    }
}
catch ( Exception $e ) {
    echo $e->getMessage() . PHP_EOL;
}

function processPlanning( bool $doTimeouts, PlanningRepository $planningRepos, PlanningInputRepository $planningInputRepos )
{
    $planningToTry = null;

    // get a planning
    if ($doTimeouts) {
        //  with state Planning::STATE_TIMEOUT
    } else {
        $inputPlanning = $planningInputRepos->getFirstUnsuccesfull();
        if( $inputPlanning === null ) {
            throw new \Exception("all plannings are succesfull", E_ERROR );
        }
        $planningToTry = $planningRepos->createNextTry( $inputPlanning );
        if ($planningToTry === null) {
            throw new \Exception("should always be possible to create", E_ERROR );
        }
    }

    // process it!!
    processPlanningHelper( $planningToTry, $planningRepos, $planningInputRepos );

    // check if there is an new succes planning, also check if all plannings haven been tried!
    if ($planningToTry->getState() === Planning::STATE_FAILED || $planningToTry->getState() === Planning::STATE_TIMEOUT ) {
        // nrofbatchgames should be
        $planningRepos->updateSuccess( $planningToTry->getInput() );
    }
}

function processPlanningHelper( Planning $planning, PlanningRepository $planningRepos, PlanningInputRepository $planningInputRepos )
{
    $planning->setState( Planning::STATE_PROCESSING );
    $planningRepos->save( $planning );
    $inputPlanning = $planning->getInput();
    echo
        'trying nrOfCompetitors ' . $inputPlanning->getStructure()->getNrOfPlaces()
        . ', structure ' . implode( '|', $inputPlanning->getStructureConfig())
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

    $planningService->createGames( $planning );
    $planningRepos->save( $planning );

    echo
        $planning->getState() === Planning::STATE_FAILED ? "failed" :
        ( $planning->getState() === Planning::STATE_TIMEOUT ? "timeout(".$planning->getTimeoutSeconds().")" : "success" )
        . PHP_EOL;
}

