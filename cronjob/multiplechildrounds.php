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

use Voetbal\Competition;
use Voetbal\Round;

$settings = $app->getContainer()->get('settings');
$em = $app->getContainer()->get('em');
$voetbal = $app->getContainer()->get('voetbal');


try {
    // haal alle rondes op met qualifyorder = 2
    $competitionRepos = $voetbal->getRepository( Competition::class );
    $numberRepos = $voetbal->getRepository( Round\Number::class );
    $roundRepos = $voetbal->getRepository( Round::class );

    $competitions = $competitionRepos->findBy(
        [],
        ["id" => "DESC"],
        1
    );
    foreach( $competitions as $competition ) {
        echo "competition: " . $competition->getName() . PHP_EOL;

        $numbers = $numberRepos->findBy(
            ["competition" => $competition ],
            ["number" => "ASC"]
        );
        foreach( $numbers as $number ) {
            $rounds = $roundRepos->findBy(
                ["number" => $number, "qualifyOrderDep" => Round::QUALIFYORDER_RANK]
            );
            foreach( $rounds as $round ) {
                addRounds( $round, $number );
            }
        }


    }

    //
}
catch( \Exception $e ) {
    echo $e->getMessage() . PHP_EOL;
}

function addRounds( Round $round, Round\Number $number )
{
    echo "      round: " . PHP_EOL;
    echo "          number: " . $number->getNumber() . PHP_EOL;
    echo "          id: " . $round->getId() . PHP_EOL;
    echo "          winnerslosers: " . ($round->getWinnersOrlosers() === Round::WINNERS ? "winnaars" : "verliezers") . PHP_EOL;
    echo "          nrofplaces: " . $round->getNrOfPlaces() . PHP_EOL;
    $nrOfPoules = $round->getPoules()->count();
    $previousNrOfPoules = $round->getParent()->getPoules()->count();
    echo "          nrofpoules: " . $nrOfPoules . PHP_EOL;
    echo "          previousnrofpoules: " . $round->getParent()->getPoules()->count() . PHP_EOL;
    $nrOfNewRounds = $round->getNrOfPlaces() / $round->getParent()->getPoules()->count();
    echo "          nrofnewrounds: " . $nrOfNewRounds . PHP_EOL;
    $nrOfNewRoundsToAdd = ($round->getNrOfPlaces() / $round->getParent()->getPoules()->count())-1;
    echo "          nrofnewroundstoadd: " . $nrOfNewRoundsToAdd . PHP_EOL;
    foreach( $round->getPoules() as $poule ) {
        if( $poule->getNumber() <= ($nrOfPoules / $nrOfNewRounds) ) {
            continue;
        }
        echo "          poulenumber to move: " . $poule->getNumber() . PHP_EOL;
    }

    echo "walk through tournaments which may present problem??" . PHP_EOL;

    echo "first make new structure!!";
}