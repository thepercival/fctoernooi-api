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
use Voetbal\Planning as PlanningBase;
use Voetbal\Planning\Input as PlanningInput;
use Voetbal\Planning\Input\Service as PlanningInputService;
use Voetbal\Planning\Seeker as PlanningSeeker;

$settings = $app->getContainer()->get('settings');
$em = $app->getContainer()->get('em');
$voetbal = $app->getContainer()->get('voetbal');
$planningRepos = $voetbal->getRepository( \Voetbal\Planning::class );
$planningInputRepos = $voetbal->getRepository( \Voetbal\Planning\Input::class );
$inputService = new PlanningInputService();

$logger = new Logger('planning-timeout-create');
$output = 'php://stdout';
// if( $settings['environment'] !== 'development' ) {
//    $output = $settings['logger']['cronjobpath'] . 'planning_create.log';
//    $logger->pushProcessor(new \Monolog\Processor\UidProcessor());
// }
$handler = new \Monolog\Handler\StreamHandler($output, $settings['logger']['level']);
$logger->pushHandler( $handler );
$logger->info( "start process" );
$planningSeeker = new PlanningSeeker( $logger, $planningInputRepos, $planningRepos );

try {
    $planning = $planningRepos->getTimeout();
    if ($planning === null) {
        $logger->info("   all plannings(also timeout) are tried");
        return;
    }
    if( array_key_exists(1, $argv) ) {
        $planning = $planningRepos->find( (int) $argv[1] );
    }
    $planningSeeker->processTimeout( $planning );
//    if( $planning->getState() !== PlanningBase::STATE_SUCCESS ) {
//        return;
//    }
    // update planninginputs
    for ( $reverseGCD = 2 ; $reverseGCD <= 8 ; $reverseGCD++ ) {

        $reverseGCDInputTmp = $inputService->getReverseGCDInput( $planning->getInput(), $reverseGCD );
        $reverseGCDInput = $planningInputRepos->getFromInput( $reverseGCDInputTmp );
        if( $reverseGCDInput === null ) {
            continue;
        }

        $plannings = $reverseGCDInput->getPlannings();
        while ( $plannings->count() > 0 ) {
            $removePlanning = $plannings->first();
            $plannings->removeElement( $removePlanning );
            $planningRepos->remove( $removePlanning );
        }

        $reverseGCDInput->setState( PlanningInput::STATE_CREATED );
        $planningInputRepos->save( $reverseGCDInput );
    }
}
catch ( Exception $e ) {
    $logger->error( $e->getMessage()  );
}