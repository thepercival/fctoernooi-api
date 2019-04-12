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

use FCToernooi\Role;
use FCToernooi\User;
use FCToernooi\Tournament;
use Monolog\Logger;

$settings = $app->getContainer()->get('settings');
$em = $app->getContainer()->get('em');
$voetbal = $app->getContainer()->get('voetbal');

$logger = new Logger('cronjob-sendhelpemail');
$logger->pushProcessor(new \Monolog\Processor\UidProcessor());
$logger->pushHandler(new \Monolog\Handler\StreamHandler($settings['logger']['cronjobpath'] . 'sendhelpemail.log', $settings['logger']['level']));

$userRepos = $voetbal->getRepository( \FCToernooi\User::class );
$tournamentRepos = $voetbal->getRepository( \FCToernooi\Tournament::class );

try {
    $users = $userRepos->findAll();
    foreach( $users as $user ) {
        if( $user->getHelpSent() === true ) {
            continue;
        }
        $tournaments = $tournamentRepos->findByPermissions( $user, Role::ADMIN );
        if( count( $tournaments ) === 0 ) {
            continue;
        }

        $tournament = reset($tournaments);
        mailHelp( $user, reset($tournaments) );
        $user->setHelpSent( true );
        $userRepos->save( $user );

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

function mailHelp( User $user, Tournament $tournament )
{
    $subject = $tournament->getCompetition()->getLeague()->getName();
    $body = '
        <p>Hallo,</p>
        <p>            
        Als beheerder van <a href="https://www.fctoernooi.nl/">https://www.fctoernooi.nl/</a> zag ik dat je een toernooi hebt aangemaakt op onze website. 
        Mocht je vragen hebben of dan horen we dat graag. Beantwoord dan gewoon deze email of bel me.        
        </p>
        <p>            
        Veel plezier met het gebruik van onze website! De handleiding kun je <a href="https://docs.google.com/document/d/1SYeUJa5yvHZzvacMyJ_Xy4MpHWTWRgAh1LYkEA2CFnM/edit?usp=sharing">hier</a> vinden.
        </p>
        <p>
        met vriendelijke groet,
        <br>
        Coen Dunnink<br>
        06-14363514
        </p>';

    $from = "FCToernooi";
    $fromEmail = "info@fctoernooi.nl";
    $headers  = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8" . "\r\n";
    $headers .= "From: ".$from." <" . $fromEmail . ">" . "\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();
    $params = "-r ".$fromEmail;

    if ( !mail( $user->getEmailaddress(), $subject, $body, $headers, $params) ) {
        // $app->flash("error", "We're having trouble with our mail servers at the moment.  Please try again later, or contact us directly by phone.");
        error_log('Mailer Error!' );
        // $app->halt(500);
    }
    $prepend = "email: " . $user->getEmailaddress() . "<br><br>link: https://www.fctoernooi.nl/toernooi/view/" . $tournament->getId() . "<br><br>";
    if ( !mail( "fctoernooi2018@gmail.com", $subject, $prepend . $body, $headers, $params) ) {
        error_log('Mailer Error!' );
    }
}

