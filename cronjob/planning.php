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
use Voetbal\Planning;
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
$planningRepos = $voetbal->getRepository( \Voetbal\Planning::class );
$planningInputRepos = $voetbal->getRepository( \Voetbal\Planning\Input::class );

try {
    $timeoutSeconds = $planningRepos->getMaxTimeoutSeconds();
    $nothingTried = createPlannings( 1, $timeoutSeconds, $planningRepos, $planningInputRepos );
    if( $nothingTried ) {
        $timeoutSeconds *= 2;
        createPlannings( 1, $timeoutSeconds, $planningRepos, $planningInputRepos );
    }
}
catch( \Exception $e ) {
    echo $e->getMessage() . PHP_EOL;
}

function createPlannings( $nrOfBatchGames, $timeoutSeconds
    , PlanningRepository $planningRepos, PlanningInputRepository $planningInputRepos ): bool
{
    $nothingTried = true;
    $maxNrOfCompetitors = 40;
    $maxNrOfSports = 1;
    $maxNrOfReferees = 20;
    $maxNrOfFields = 20;
    $maxNrOfHeadtohead = 2;
    $planningService = new PlanningService();
    $variations = 0;

    for ($nrOfCompetitors = 2; $nrOfCompetitors <= $maxNrOfCompetitors; $nrOfCompetitors++) {
        $nrOfPoules = 0;
        while ( ((int) floor($nrOfCompetitors / ++$nrOfPoules)) >= 2) {

            for ($nrOfSports = 1; $nrOfSports <= $maxNrOfSports; $nrOfSports++) {
                for ($nrOfReferees = 0; $nrOfReferees <= $maxNrOfReferees; $nrOfReferees++) {
                    for ($nrOfFields = 1; $nrOfFields <= $maxNrOfFields; $nrOfFields++) {
                        for ($nrOfHeadtohead = 1; $nrOfHeadtohead <= $maxNrOfHeadtohead; $nrOfHeadtohead++) {
                            // __construct( array $structureConfig, array $fieldConfig, int $nrOfReferees, int $nrOfHeadtohead, bool $teamup, bool $selfReferee ) {
                            $structureConfig = getStructureConfig( $nrOfCompetitors, $nrOfPoules );
                            $sportConfig = getSportConfig( $nrOfSports, $nrOfFields );

                            $selfRefereeTeamupVariations = getSelfRefereeTeamupVariations( $nrOfReferees, $nrOfCompetitors, $structureConfig );
                            foreach( $selfRefereeTeamupVariations as $selfRefereeTeamupVariation ) {
                                $teamup = $selfRefereeTeamupVariation->teamup;
                                $selfReferee = $selfRefereeTeamupVariation->selfReferee;

//                                if ($nrOfCompetitors !== 6 || $nrOfPoules !== 1 || $nrOfSports !== 1 || $nrOfFields !== 1
//                                    || $nrOfHeadtohead !== 1 || $teamup !== false || $selfReferee !== false ) {
//                                    continue;
//                                }

                                $inputPlanning = $planningInputRepos->get(
                                    $structureConfig, $sportConfig, $nrOfReferees, $nrOfHeadtohead, $teamup, $selfReferee
                                );

                                if( $inputPlanning === null ) {
                                    $inputPlanning = new InputPlanning(
                                        $structureConfig, $sportConfig, $nrOfReferees, $nrOfHeadtohead, $teamup, $selfReferee
                                    );
                                    $planningInputRepos->save( $inputPlanning );
                                }

                                if ( $planningRepos->hasEndSuccess( $inputPlanning ) ) {
                                    continue;
                                }


                                if ($nrOfFields > $planningService->getMaxNrOfFieldsUsable( $inputPlanning )) {
                                    continue;
                                }
                                if ($nrOfReferees > $planningService->getMaxNrOfRefereesUsable( $inputPlanning )) {
                                    continue;
                                }

                                $maxNrOfBatchGames = $inputPlanning->getMaxNrOfBatchGames();
                                if( $nrOfBatchGames > $maxNrOfBatchGames ) {
                                    continue;
                                }
                                $maxNrOfGamesInARow = $inputPlanning->getMaxNrOfGamesInARow();

                                $nrOfBatchGamesRange = new VoetbalRange( $nrOfBatchGames, $nrOfBatchGames );
                                if ( $planningRepos->hasTried( $inputPlanning, $nrOfBatchGamesRange ) === false ) {
                                    tryPlanning( $inputPlanning, $nrOfBatchGamesRange, $maxNrOfGamesInARow, $planningRepos, $timeoutSeconds);
                                    $nothingTried = false;
                                }

                                $nrOfBatchGamesRange = new VoetbalRange( $nrOfBatchGames - 1, $nrOfBatchGames );
                                if ( $planningRepos->hasTried( $inputPlanning, $nrOfBatchGamesRange ) === false ) {
                                    tryPlanning( $inputPlanning, $nrOfBatchGamesRange, $maxNrOfGamesInARow, $planningRepos, $timeoutSeconds);
                                    $nothingTried = false;
                                }


                                // loop door alle ranges van 1 t/m $maxNrOfBatchGames, waarbij maxNrOfGamesInARow = max(-1)
                                // dus ( 1-> 1 ), ( 2-> 2 ), ( 1-> 2 ), ( 3-> 3 ), ( 3-> 2 ), ( 4-> 4 )




                                // wanneer bekend is welke succes hoogste succes heeft
                                // dan maxNrOfGamesInARow verlagen, bij een fail of timeout
                                // de vorige endsuccess maken!!

                                //                        $assertConfig = $this->getAssertionsConfig($nrOfCompetitors, $nrOfPoules, $nrOfSports, $nrOfFields, $nrOfHeadtohead);
                                ////                            if ($assertConfig !== null) {

                                //                        $this->checkPlanning($nrOfCompetitors, $nrOfPoules, $nrOfSports, $nrOfFields, $nrOfHeadtohead, $assertConfig, $optimalizationService);
                                //                            }




                                $variations++;
                            }
                        }
                    }
                }
            }
        }
    }
    echo "nrofvariations: " . $variations . PHP_EOL;
    return $nothingTried;
}

function tryPlanning( InputPlanning $inputPlanning, VoetbalRange $nrOfBatchGamesRange, int $maxNrOfGamesInARow
    , PlanningRepository $planingRepos,int $timeoutSeconds ) {

    for ( $maxNrOfGamesInARowIt = $maxNrOfGamesInARow ; $maxNrOfGamesInARowIt >= 1 ; $maxNrOfGamesInARowIt-- ) {
        if( $maxNrOfGamesInARowIt < $maxNrOfGamesInARow ) {
            $planning = $planingRepos->get( $inputPlanning, $nrOfBatchGamesRange, $maxNrOfGamesInARow );
            if( $planning !== null && $planning->getState() < Planning::STATE_SUCCESS_PARTIAL ) {
                break;
            }
        }
        $planningService = new PlanningService();

        // alles moet naar planningsobjecten gaan

        // vantevoren is al duidelijk hoeveel de h2h wordt deze kunnen dan al worden berekend!!
        // Tijdens het genereren hoeft niet meer gekeken te worden naar meer h2h,
        // sufficienth2h zal dus uit gamegenerator gaan en moet dus al bekend zijn voordat games gegenereerd worden!

        $planning = $planningService->create( $inputPlanning, $nrOfBatchGamesRange, $maxNrOfGamesInARowIt, $timeoutSeconds );

        $planingRepos->save( $planning );

        echo
            'nrOfCompetitors ' . $inputPlanning->getStructure()->getNrOfPlaces()
            . ', structure ' . implode( '|', $inputPlanning->getStructureConfig())
            . ', nrOfSports ' . count( $inputPlanning->getSportConfig())
            . ', nrOfReferees ' . $inputPlanning->getNrOfReferees()
            . ', nrOfFields ' . $inputPlanning->getNrOfFields()
            . ', nrOfHeadtohead ' . $inputPlanning->getNrOfHeadtohead()
            . ', teamup ' . ( $inputPlanning->getTeamup() ? '1' : '0' )
            . ', selfReferee ' . ( $inputPlanning->getSelfReferee() ? '1' : '0' )
            . PHP_EOL;

        die();
    }
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

    if( $nrOfPlaces === 4 ) {
        $x = 2;
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

function getStructureConfig( int $nrOfPlaces, int $nrOfPoules ): array {
    $structureConfig = [];
    $structureService = new StructureService();
    $nrOfPlacesPerPoule = $structureService->getNrOfPlacesPerPoule( $nrOfPlaces, $nrOfPoules, false );
    while( $nrOfPlaces > 0 ) {
        if( $nrOfPlaces >= $nrOfPlacesPerPoule ) {
            $structureConfig[] = $nrOfPlacesPerPoule;
        } else {
            $structureConfig[] = $nrOfPlaces;
        }
        $nrOfPlaces -= $nrOfPlacesPerPoule;
    }
    return $structureConfig;
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
        for ($fieldNr = 1; $fieldNr <= $nrOfFields; $fieldNr++) {
            $sports[] = [ "nrOfFields" => $fieldNr, "nrOfGamePlaces" => Sport::TEMPDEFAULT ];
        }
    }
    return $sports;
}
