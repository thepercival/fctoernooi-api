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

const MAXNROFSPORTS = 4;
const MAXNROFREFEREES = 20;
const MAXNROFFIELDS = 20;
const MAXNROFFIELDS_FOR_MULTIPLESPORTS = 6;
const PLANNING_MAXNROFHEADTOHEAD = 2;

$settings = $app->getContainer()->get('settings');
$em = $app->getContainer()->get('em');
$voetbal = $app->getContainer()->get('voetbal');
$planningRepos = $voetbal->getRepository( \Voetbal\Planning::class );
$planningInputRepos = $voetbal->getRepository( \Voetbal\Planning\Input::class );
$planningInputSerivce = new PlanningInputService();

try {
    createPlanningInputs( $planningRepos, $planningInputRepos, $planningInputSerivce );
}
catch( \Exception $e ) {
    echo $e->getMessage() . PHP_EOL;
}

function createPlanningInputs( PlanningRepository $planningRepos, PlanningInputRepository $planningInputRepos, PlanningInputService $planningInputSerivce )
{
    $structureOptions = new TournamentStructureOptions();
    $structureService = new StructureService( $structureOptions );
    for ($nrOfCompetitors = $structureOptions->getPlaceRange()->min; $nrOfCompetitors <= $structureOptions->getPlaceRange()->max; $nrOfCompetitors++) {
        if( $nrOfCompetitors !== 5 ) {
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
            for ($nrOfSports = 1; $nrOfSports <= MAXNROFSPORTS; $nrOfSports++) {
                for ($nrOfReferees = 0; $nrOfReferees <= MAXNROFREFEREES; $nrOfReferees++) {
                    for ($nrOfFields = 1; $nrOfFields <= MAXNROFFIELDS; $nrOfFields++) {
                        if( $nrOfFields < $nrOfSports ) {
                            continue;
                        }
                        $sportConfig = getSportConfig( $nrOfSports, $nrOfFields );
                        $selfRefereeTeamupVariations = getSelfRefereeTeamupVariations( $nrOfReferees, $nrOfCompetitors, $structureConfig, $sportConfig );
                        for ($nrOfHeadtohead = 1; $nrOfHeadtohead <= PLANNING_MAXNROFHEADTOHEAD; $nrOfHeadtohead++) {
                            foreach( $selfRefereeTeamupVariations as $selfRefereeTeamupVariation ) {
                                $teamup = $selfRefereeTeamupVariation->teamup;
                                $selfReferee = $selfRefereeTeamupVariation->selfReferee;
//                                if ($nrOfCompetitors !== 6 || $nrOfPoules !== 1 || $nrOfSports !== 1 || $nrOfFields !== 3
//                                    || $nrOfHeadtohead !== 1 /*|| $teamup !== false || $selfReferee !== false*/ ) {
//                                    continue;
//                                }

                                $multipleSports = count($sportConfig) > 1;
                                $newNrOfHeadtohead = $nrOfHeadtohead;
                                if( $multipleSports ) {
                                    if( count($sportConfig) === 4 && $sportConfig[0]["nrOfFields"] == 1 && $sportConfig[1]["nrOfFields"] == 1
                                        && $sportConfig[2]["nrOfFields"] == 1 && $sportConfig[3]["nrOfFields"] == 1
                                        && $teamup === false && $selfReferee === false && $nrOfHeadtohead === 1 && $structureConfig == [3]  ) {
                                        $e = 2;
                                    }
                                    $newNrOfHeadtohead = $planningInputSerivce->getSufficientNrOfHeadtohead(
                                        $nrOfHeadtohead,
                                        min($structureConfig),
                                        $teamup,
                                        $selfReferee,
                                        $sportConfig );
                                }
                                $planningInput = $planningInputRepos->get(
                                    $structureConfig, $sportConfig, $nrOfReferees, $teamup, $selfReferee, $newNrOfHeadtohead
                                );
                                if( $planningInput !== null ) {
                                    continue;
                                }
                                $planningInput = new InputPlanning(
                                    $structureConfig, $sportConfig, $nrOfReferees, $teamup, $selfReferee, $newNrOfHeadtohead
                                );

                                if( !$multipleSports ) {
                                    $maxNrOfFieldsInPlanning = $planningInput->getMaxNrOfBatchGames( Resources::REFEREES + Resources::PLACES );
                                    if ($nrOfFields > $maxNrOfFieldsInPlanning ) {
                                        continue;
                                    }
                                } else {
                                    if ($nrOfFields > MAXNROFFIELDS_FOR_MULTIPLESPORTS ) {
                                        continue;
                                    }
                                }

                                $maxNrOfRefereesInPlanning = $planningInput->getMaxNrOfBatchGames( Resources::FIELDS + Resources::PLACES );
                                if ($nrOfReferees > $maxNrOfRefereesInPlanning) {
                                    continue;
                                }

                                $planningInputRepos->save( $planningInput );
                                // die();
                            }
                        }
                    }
                }
            }
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
    $nrOfFieldsPerSport = (int)ceil($nrOfFields/$nrOfSports);
    for ($sportNr = 1; $sportNr <= $nrOfSports; $sportNr++) {
        $sports[] = [ "nrOfFields" => $nrOfFieldsPerSport, "nrOfGamePlaces" => Sport::TEMPDEFAULT ];
        $nrOfFields -= $nrOfFieldsPerSport;
        if( ( $nrOfFieldsPerSport * ( $nrOfSports - $sportNr )  ) > $nrOfFields ) {
            $nrOfFieldsPerSport--;
        }
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