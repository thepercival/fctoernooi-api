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

    // while( (new \DateTimeImmutable()) < $endDate ) {
    if( $planningInputRepos->isProcessing() ) {
        $logger->info( "still processing, sleeping 10 seconds.." );
        return;
    }
    $planning = $planningRepos->getTimeout();
    if ($planning === null) {
        $logger->info("   all plannings(also timeout) are tried");
        return;
    }
    $planningSeeker->processTimeout( $planning );

//        sleep(3);
//        $logger->info( "sleeping 3 seconds.." );
//    }
//    $endDate = new \DateTimeImmutable();
//    $logger->info( "end job at " . $endDate->format("Y-m-d H:i") . ' which started at ' . $startDate->format("Y-m-d H:i") );
}
catch ( Exception $e ) {
    echo $e->getMessage() . PHP_EOL;
}