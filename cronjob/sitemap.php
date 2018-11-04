<?php
/**
 * Created by PhpStorm.
 * User: coen
 * Date: 4-11-18
 * Time: 15:32
 */

require __DIR__ . '/../vendor/autoload.php';
$settings = require __DIR__ . '/../app/settings.php';
$app = new \Slim\App($settings);
require __DIR__ . '/../app/dependencies.php';
require __DIR__ . '/mailHelper.php';

$settings = $app->getContainer()->get('settings');
$em = $app->getContainer()->get('em');
$voetbal = $app->getContainer()->get('voetbal');

$url = "https://www.fctoernooi.nl/";
$distPath = realpath( __DIR__ . "/../../" ) . "/fctoernooi/dist/";

$tournamentRepos = $voetbal->getRepository( \FCToernooi\Tournament::class );

try {
    $content = $url . PHP_EOL;
    $content .= $url . "user/register/" . PHP_EOL;
    $content .= $url . "user/login/" . PHP_EOL;
    $content .= $url . "user/forgetpassword/" . PHP_EOL;

    $tournaments = $tournamentRepos->findAll();
    foreach( $tournaments as $tournament ) {
        $content .= $url. "toernooi/view/" . $tournament->getId() . PHP_EOL;
    }
    file_put_contents( $distPath . "sitemap.txt", $content );
    chmod ( $distPath . "sitemap.txt", 744 );
    chown ( $distPath . "sitemap.txt", "coen" );
    chgrp ( $distPath . "sitemap.txt", "coen" );
}
catch( \Exception $e ) {
    if( $settings->get('environment') === 'production') {
        mailAdmin( $e->getMessage() );
        $logger->addError("GENERAL ERROR: " . $e->getMessage() );
    } else {
        echo $e->getMessage() . PHP_EOL;
    }
}

?>
