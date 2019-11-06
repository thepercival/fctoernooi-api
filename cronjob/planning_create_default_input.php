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
use Voetbal\Sport;
use Voetbal\Range as VoetbalRange;
use FCToernooi\Tournament;
use Voetbal\Planning\Service as PlanningService;
use Voetbal\Planning\Repository as PlanningRepository;
use Voetbal\Planning\Input\Repository as PlanningInputRepository;
use Voetbal\Planning\Input as InputPlanning;
use Voetbal\Planning\Config as PlanningConfig;
use Voetbal\Structure\Service as StructureService;

use Voetbal\Round\Number as RoundNumber;

$settings = $app->getContainer()->get('settings');
$em = $app->getContainer()->get('em');
$voetbal = $app->getContainer()->get('voetbal');
$planningInputRepos = $voetbal->getRepository( \Voetbal\Planning\Input::class );

try {
    createPlanningInputs( $planningInputRepos );
}
catch( \Exception $e ) {
    echo $e->getMessage() . PHP_EOL;
}

function createPlanningInputs( PlanningInputRepository $planningInputRepos )
{
    $structureService = new StructureService( new VoetbalRange( Tournament::MINNROFCOMPETITORS, Tournament::MAXNROFCOMPETITORS ) );
    $planningService = new PlanningService();
    for ($nrOfCompetitors = Tournament::MINNROFCOMPETITORS; $nrOfCompetitors <= Tournament::MAXNROFCOMPETITORS; $nrOfCompetitors++) {
        $nrOfPoules = 0;
        while ( ((int) floor($nrOfCompetitors / ++$nrOfPoules)) >= 2) {
            $structureConfig = $structureService->getStructureConfig( $nrOfCompetitors, $nrOfPoules );
            echo "saving default inputs for " . $nrOfCompetitors . " competitors [".implode(",", $structureConfig )."] ....";
            for ($nrOfSports = 1; $nrOfSports <= Tournament::MAXNROFSPORTS; $nrOfSports++) {
                for ($nrOfReferees = 0; $nrOfReferees <= Tournament::MAXNROFREFEREES; $nrOfReferees++) {
                    $selfRefereeTeamupVariations = getSelfRefereeTeamupVariations( $nrOfReferees, $nrOfCompetitors, $structureConfig );
                    for ($nrOfFields = 1; $nrOfFields <= Tournament::MAXNROFFIELDS; $nrOfFields++) {
                        $sportConfig = getSportConfig( $nrOfSports, $nrOfFields );

                        for ($nrOfHeadtohead = 1; $nrOfHeadtohead <= Tournament::PLANNING_MAXNROFHEADTOHEAD; $nrOfHeadtohead++) {
                            foreach( $selfRefereeTeamupVariations as $selfRefereeTeamupVariation ) {
                                $teamup = $selfRefereeTeamupVariation->teamup;
                                $selfReferee = $selfRefereeTeamupVariation->selfReferee;

//                                if ($nrOfCompetitors !== 6 || $nrOfPoules !== 1 || $nrOfSports !== 1 || $nrOfFields !== 3
//                                    || $nrOfHeadtohead !== 1 /*|| $teamup !== false || $selfReferee !== false*/ ) {
//                                    continue;
//                                }

                                $inputPlanning = $planningInputRepos->get(
                                    $structureConfig, $sportConfig, $nrOfReferees, $nrOfHeadtohead, $teamup, $selfReferee
                                );
                                if( $inputPlanning !== null ) {
                                    continue;
                                }
                                $inputPlanning = new InputPlanning(
                                    $structureConfig, $sportConfig, $nrOfReferees, $nrOfHeadtohead, $teamup, $selfReferee
                                );
                                if ($nrOfFields > $planningService->getMaxNrOfFieldsUsable( $inputPlanning )) {
                                    continue;
                                }
                                if ($nrOfReferees > $planningService->getMaxNrOfRefereesUsable( $inputPlanning )) {
                                    continue;
                                }
                                $planningInputRepos->save( $inputPlanning );
                            }
                        }
                    }
                }
            }
            echo " => saved!" . PHP_EOL;
        }
        if( $nrOfCompetitors >= 10 ) {
            break;
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

function getSelfRefereeTeamupVariations( int $nrOfReferees, int $nrOfPlaces, array $structureConfig ): array {
    $minNrOfPlacesPerPoule = null; $maxNrOfPlacesPerPoule = null;
    foreach( $structureConfig as $nrOfPlacesPerPoule ) {
        if( $minNrOfPlacesPerPoule === null || $nrOfPlacesPerPoule < $minNrOfPlacesPerPoule ) {
            $minNrOfPlacesPerPoule = $nrOfPlacesPerPoule;
        }
        if( $maxNrOfPlacesPerPoule === null || $nrOfPlacesPerPoule > $maxNrOfPlacesPerPoule ) {
            $maxNrOfPlacesPerPoule = $nrOfPlacesPerPoule;
        }
    }

    $variations = [ json_decode( json_encode( ["selfReferee" => false, "teamup" => false ])) ];
    if( $nrOfPlaces > 2 && $nrOfReferees === 0 ) {
        $variations = array_merge( $variations, [
            json_decode( json_encode( ["selfReferee" => true, "teamup" => false ]))
        ] );
    }
    if( $minNrOfPlacesPerPoule >= PlanningConfig::TEAMUP_MIN && $maxNrOfPlacesPerPoule <= PlanningConfig::TEAMUP_MAX ) {
        $variations = array_merge( $variations, [
            json_decode( json_encode( ["selfReferee" => false, "teamup" => true ]))
        ] );
        if( /*$nrOfPlaces > 2 &&*/ $nrOfReferees === 0 ) {
            $variations = array_merge( $variations, [
                json_decode( json_encode( ["selfReferee" => true, "teamup" => true ]))
            ] );
        }
    }
    return $variations;
}