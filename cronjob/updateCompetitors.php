<?php
/**
 * Created by PhpStorm.
 * User: coen
 * Date: 6-3-18
 * Time: 22:28
 */

require __DIR__ . '/../vendor/autoload.php';
$settings = require __DIR__ . '/../app/settings.php';
$app = new \Slim\App($settings);
// Set up dependencies
require __DIR__ . '/../app/dependencies.php';
require __DIR__ . '/mailHelper.php';

use Monolog\Logger;
use Voetbal\External\Competitor\Importer as CompetitorImporter;

$settings = $app->getContainer()->get('settings');
$em = $app->getContainer()->get('em');
$voetbal = $app->getContainer()->get('voetbal');

$logger = new Logger('cronjob-competitors');
$logger->pushProcessor(new \Monolog\Processor\UidProcessor());
$logger->pushHandler(new \Monolog\Handler\StreamHandler($settings['logger']['cronjobpath'] . 'competitors.log', $settings['logger']['level']));

try {
    $importer = new CompetitorImporter(
        $voetbal->getService( \Voetbal\Competitor::class ),
        $voetbal,
        $em->getConnection(),
        $logger
    );
    $importer->noUpdate();
    $importer->import();
}
catch( \Exception $e ) {
    if( $settings->get('environment') === 'production') {
        mailAdmin( $e->getMessage() );
        $logger->addError("GENERAL ERROR: " . $e->getMessage() );
    } else {
        echo $e->getMessage() . PHP_EOL;
    }
}
