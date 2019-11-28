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

use Voetbal\Planning as PlanningBase;
use Voetbal\Planning\Input;
use Voetbal\Planning\Input as PlanningInput;
use Voetbal\Planning\Input\Service as PlanningInputService;
use Voetbal\Planning\Resources;
use Voetbal\Sport;
use Voetbal\Range as VoetbalRange;
use FCToernooi\Tournament;
use Voetbal\Planning\Service as PlanningService;
use Voetbal\Planning\Repository as PlanningRepository;
use Voetbal\Planning\Input\Repository as PlanningInputRepository;
use Voetbal\Planning\Input as InputPlanning;
use Voetbal\Planning\Config as PlanningConfig;
use Voetbal\Planning\Config\Service as PlanningConfigService;
use Voetbal\Structure\Service as StructureService;
use FCToernooi\Tournament\StructureOptions as TournamentStructureOptions;

use Voetbal\Round\Number as RoundNumber;

$settings = $app->getContainer()->get('settings');
$em = $app->getContainer()->get('em');
$voetbal = $app->getContainer()->get('voetbal');
$planningRepos = $voetbal->getRepository( \Voetbal\Planning::class );
$planningInputRepos = $voetbal->getRepository( \Voetbal\Planning\Input::class );

try {
    createPlanningInputs( $planningRepos, $planningInputRepos );
}
catch( \Exception $e ) {
    echo $e->getMessage() . PHP_EOL;
}

function createPlanningInputs( PlanningRepository $planningRepos, PlanningInputRepository $planningInputRepos )
{
    $structureOptions = new TournamentStructureOptions();
    $structureService = new StructureService( $structureOptions );
    $planningService = new PlanningService();
    $inputService = new PlanningInputService();
    for ($nrOfCompetitors = $structureOptions->getPlaceRange()->min; $nrOfCompetitors <= $structureOptions->getPlaceRange()->max; $nrOfCompetitors++) {
        if( $nrOfCompetitors !== 14 ) {
            continue;
        }
        $nrOfPoules = 1;

        $nrOfPlacesPerPoule = $structureService->getNrOfPlacesPerPoule( $nrOfCompetitors, $nrOfPoules, true );
        while ( $nrOfPlacesPerPoule >= $structureOptions->getPlacesPerPouleRange()->min ) {
            if( $nrOfPlacesPerPoule > $structureOptions->getPlacesPerPouleRange()->max ) {
                $nrOfPlacesPerPoule = $structureService->getNrOfPlacesPerPoule( $nrOfCompetitors, ++$nrOfPoules, true );
                continue;
            }
            $structureConfig = $structureService->getStructureConfig( $nrOfCompetitors, $nrOfPoules );
            echo "saving default inputs for " . $nrOfCompetitors . " competitors [".implode(",", $structureConfig )."] ...." . PHP_EOL;
            for ($nrOfSports = 1; $nrOfSports <= Tournament::MAXNROFSPORTS; $nrOfSports++) {
                for ($nrOfReferees = 0; $nrOfReferees <= Tournament::MAXNROFREFEREES; $nrOfReferees++) {
                    for ($nrOfFields = 1; $nrOfFields <= Tournament::MAXNROFFIELDS; $nrOfFields++) {
                        $sportConfig = getSportConfig( $nrOfSports, $nrOfFields );
                        $selfRefereeTeamupVariations = getSelfRefereeTeamupVariations( $nrOfReferees, $nrOfCompetitors, $structureConfig, $sportConfig );
                        for ($nrOfHeadtohead = 1; $nrOfHeadtohead <= Tournament::PLANNING_MAXNROFHEADTOHEAD; $nrOfHeadtohead++) {
                            foreach( $selfRefereeTeamupVariations as $selfRefereeTeamupVariation ) {
                                $teamup = $selfRefereeTeamupVariation->teamup;
                                $selfReferee = $selfRefereeTeamupVariation->selfReferee;

//                                if ($nrOfCompetitors !== 6 || $nrOfPoules !== 1 || $nrOfSports !== 1 || $nrOfFields !== 3
//                                    || $nrOfHeadtohead !== 1 /*|| $teamup !== false || $selfReferee !== false*/ ) {
//                                    continue;
//                                }


                                $planningInput = $planningInputRepos->get(
                                    $structureConfig, $sportConfig, $nrOfReferees, $teamup, $selfReferee, $nrOfHeadtohead
                                );
                                if( $planningInput !== null ) {
                                    continue;
                                }
                                $planningInput = new InputPlanning(
                                    $structureConfig, $sportConfig, $nrOfReferees, $teamup, $selfReferee, $nrOfHeadtohead
                                );

                                $maxNrOfFieldsInPlanning = $planningInput->getMaxNrOfBatchGames( Resources::REFEREES + Resources::PLACES );
                                if ($nrOfFields > $maxNrOfFieldsInPlanning ) {
                                    continue;
                                }
                                $maxNrOfRefereesInPlanning = $planningInput->getMaxNrOfBatchGames( Resources::FIELDS + Resources::PLACES );
                                if ($nrOfReferees > $maxNrOfRefereesInPlanning) {
                                    continue;
                                }

                                $planningInputRepos->save( $planningInput );
                                // die();
//                                $planning = $inputService->createNextTry( $planningInput );
//
//                                echo
//                                    '   saving default planning for nrOfCompetitors ' . $planning->getStructure()->getNrOfPlaces()
//                                    . ', structure [' . implode( '|', $planningInput->getStructureConfig()) . ']'
//                                    . ', sports ' . count( $planningInput->getSportConfig())
//                                    . ', referees ' . $planningInput->getNrOfReferees()
//                                    . ', fields ' . $planningInput->getNrOfFields()
//                                    . ', teamup ' . ( $planningInput->getTeamup() ? '1' : '0' )
//                                    . ', selfRef ' . ( $planningInput->getSelfReferee() ? '1' : '0' )
//                                    . ', nrOfH2h ' . $planningInput->getNrOfHeadtohead()
//                                    . ', batchGames ' . $planning->getNrOfBatchGames()->min . '->' . $planning->getNrOfBatchGames()->max
//                                    . ', gamesInARow ' . $planning->getMaxNrOfGamesInARow()
//                                    . ', timeout ' . $planning->getTimeoutSeconds()
//                                    . " .. ";
//
//                                $planningService = new PlanningService();
//                                $newState = $planningService->createGames( $planning );
//                                $planning->setState( $newState );
//                                $planningRepos->save( $planning );
//
//                                echo " => saved!" . PHP_EOL;
                            }
                        }
                    }
                }
            }
            // echo " => saved!" . PHP_EOL;
            $nrOfPlacesPerPoule = $structureService->getNrOfPlacesPerPoule( $nrOfCompetitors, ++$nrOfPoules, true );
        }

    }
}

/**
 * breid hier configuraties eventueel uit
 *
 * @param $nrOfSports
 * @param $nrOfFields
 * @return array
 */
function getSportConfig( $nrOfSports, $nrOfFields ): array {
    $sports = [];
    for ($sportNr = 1; $sportNr <= $nrOfSports; $sportNr++) {
        // for ($fieldNr = 1; $fieldNr <= $nrOfFields; $fieldNr++) {
            $sports[] = [ "nrOfFields" => $nrOfFields, "nrOfGamePlaces" => Sport::TEMPDEFAULT ];
        // }
    }
    return $sports;
}

function getSelfRefereeTeamupVariations( int $nrOfReferees, int $nrOfPlaces, array $structureConfig, array $sportConfig ): array {
    $variations = [ json_decode( json_encode( ["selfReferee" => false, "teamup" => false ])) ];
    $planningConfigService = new PlanningConfigService();

    $selfRefereeIsAvailable = ( $nrOfReferees === 0 && $planningConfigService->canSelfRefereeBeAvailable( $nrOfPlaces ) );
    if( $selfRefereeIsAvailable ) {
        $variations = array_merge( $variations, [
            json_decode( json_encode( ["selfReferee" => true, "teamup" => false ]))
        ] );
    }

    if( $planningConfigService->canTeamupBeAvailable( $structureConfig, $sportConfig ) ) {
        $variations = array_merge( $variations, [
            json_decode( json_encode( ["selfReferee" => false, "teamup" => true ]))
        ] );
        if( $selfRefereeIsAvailable ) {
            $variations = array_merge( $variations, [
                json_decode( json_encode( ["selfReferee" => true, "teamup" => true ]))
            ] );
        }
    }
    return $variations;
}