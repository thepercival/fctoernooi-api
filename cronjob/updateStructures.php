<?php
/**
 * Created by PhpStorm.
 * User: coen
 * Date: 6-3-18
 * Time: 14:43
 */

namespace App\Cronjob;

require __DIR__ . '/../vendor/autoload.php';

$settings = require __DIR__ . '/../conf/settings.php';
$app = new \Slim\App($settings);
require __DIR__ . '/../conf/dependencies.php';
require __DIR__ . '/mailHelper.php';

use Voetbal\Structure\Service as StructureService;
use FCToernooi\Tournament\StructureOptions as TournamentStructureOptions;
use Voetbal\External\Structure\Creator as StructureCreator;
use Monolog\Logger;

$settings = $app->getContainer()->get('settings');
$em = $app->getContainer()->get('em');
$voetbal = $app->getContainer()->get('voetbal');

$logger = new Logger('cronjob-structures');
$logger->pushProcessor(new \Monolog\Processor\UidProcessor());
$logger->pushHandler(new \Monolog\Handler\StreamHandler($settings['logger']['cronjobpath'] . 'structures.log', $settings['logger']['level']));

try {
    $importer = new StructureCreator(
        new StructureService( new TournamentStructureOptions() ),
        $voetbal,
        $em->getConnection(),
        $logger
    );
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


