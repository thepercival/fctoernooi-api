<?php
/**
 * Created by PhpStorm.
 * User: coen
 * Date: 29-3-19
 * Time: 8:00
 */

namespace App\Cronjob;

require __DIR__ . '/../vendor/autoload.php';

$settings = require __DIR__ . '/../conf/settings.php';
$app = new \Slim\App($settings);
require __DIR__ . '/../conf/dependencies.php';
require __DIR__ . '/mailHelper.php';

use FCToernooi\Role;
use FCToernooi\User;
use FCToernooi\Tournament;
use Monolog\Logger;

$settings = $app->getContainer()->get('settings');
$voetbal = $app->getContainer()->get('voetbal');

$logger = new Logger('cronjob-sendhelpemail');
$logger->pushProcessor(new \Monolog\Processor\UidProcessor());
$logger->pushHandler(new \Monolog\Handler\StreamHandler($settings['logger']['cronjobpath'] . 'backup-sponsor-images.log', $settings['logger']['level']));

$sponsorRepos = $voetbal->getRepository( \FCToernooi\Sponsor::class );
$apiUrlPath = $settings["www"]["apiurl"];
$path = $settings["www"]["apiurl-localpath"] . $settings["images"]["sponsors"]["pathpostfix"];
$backupPath = $settings["images"]["sponsors"]["backuppath"] . $settings["images"]["sponsors"]["pathpostfix"];
try {
    if( !is_writable( $backupPath ) ) {
        throw new \Exception("backuppath " . $backupPath . " is not writable", E_ERROR );
    }
    $sponsors = $sponsorRepos->findAll();
    foreach( $sponsors as $sponsor ) {
        $logoUrl = $sponsor->getLogoUrl();
        if( strpos( $logoUrl, $apiUrlPath ) === false ) {
            continue;
        }
        // $logoUrl = $settings["www"]["apiurl-localpath"];
        $logoLocalPath = str_replace( $apiUrlPath, $settings["www"]["apiurl-localpath"], $logoUrl);
        if( !is_readable( $logoLocalPath ) ) {
            throw new \Exception("sponsorimage " . $logoLocalPath . " could not be found", E_ERROR );
        }

        $newPath = str_replace($path,$backupPath,$logoLocalPath);
        if (!copy($logoLocalPath, $newPath)) {
            $logger->addError("failed to copy  " . $logoLocalPath );
        }
    }
}
catch( \Exception $e ) {
    if( $settings->get('environment') === 'production') {
        mailAdmin( $e->getMessage() );
        $logger->addError("GENERAL ERROR: " . $e->getMessage() );
    } else {
        echo $e->getMessage() . PHP_EOL;
    }
}