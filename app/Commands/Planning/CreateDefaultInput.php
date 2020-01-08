<?php

namespace App\Commands\Planning;

use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Voetbal\Planning\Repository as PlanningRepository;
use Voetbal\Planning\Input\Repository as PlanningInputRepository;

use Voetbal\Planning\Input;
use Voetbal\Planning\Input\Service as PlanningInputService;
use Voetbal\Planning\Resources;
use Voetbal\Sport;
use Voetbal\Planning\Input as InputPlanning;
use Voetbal\Planning\Config\Service as PlanningConfigService;
use Voetbal\Structure\Service as StructureService;
use FCToernooi\Tournament\StructureOptions as TournamentStructureOptions;

class CreateDefaultInput extends Command
{
    /**
     * @var PlanningInputRepository
     */
    protected $planningInputRepos;

    const MAXNROFSPORTS = 1;
    const MAXNROFREFEREES = 20;
    const MAXNROFFIELDS = 20;
    const MAXNROFFIELDS_FOR_MULTIPLESPORTS = 6;
    const PLANNING_MAXNROFHEADTOHEAD = 2;

    public function __construct(ContainerInterface $container)
    {
        // $settings = $container->get('settings');
        $this->planningInputRepos = $container->get(PlanningInputRepository::class);
        parent::__construct();
    }

    protected function configure()
    {
        $this
            // the name of the command (the part after "bin/console")
            ->setName('app:create-default-planning-input')
            // the short description shown while running "php bin/console list"
            ->setDescription('Creates the default planning-inputs')
            // the full command description shown when running the command with
            // the "--help" option
            ->setHelp('Creates the default planning-inputs');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $planningInputSerivce = new PlanningInputService();
        return $this->createPlanningInputs($planningInputSerivce);
    }

    protected function createPlanningInputs(PlanningInputService $planningInputSerivce): int
    {
        $structureOptions = new TournamentStructureOptions();
        $structureService = new StructureService($structureOptions);
        for (
            $nrOfCompetitors = $structureOptions->getPlaceRange(
            )->min; $nrOfCompetitors <= $structureOptions->getPlaceRange()->max; $nrOfCompetitors++
        ) {
            //        if( !($nrOfCompetitors === 6 || $nrOfCompetitors === 12) ) {
            //            continue;
            //        }
            $nrOfPoules = 1;

            $nrOfPlacesPerPoule = $structureService->getNrOfPlacesPerPoule($nrOfCompetitors, $nrOfPoules, true);
            while ($nrOfPlacesPerPoule >= $structureOptions->getPlacesPerPouleRange()->min) {
                if ($nrOfPlacesPerPoule > $structureOptions->getPlacesPerPouleRange()->max) {
                    $nrOfPlacesPerPoule = $structureService->getNrOfPlacesPerPoule(
                        $nrOfCompetitors,
                        ++$nrOfPoules,
                        true
                    );
                    continue;
                }
                $structureConfig = $structureService->getStructureConfig($nrOfCompetitors, $nrOfPoules);
                echo "saving default inputs for " . $nrOfCompetitors . " competitors [" . implode(
                        ",",
                        $structureConfig
                    ) . "] ...." . PHP_EOL;
                for ($nrOfSports = 1; $nrOfSports <= self::MAXNROFSPORTS; $nrOfSports++) {
                    for ($nrOfReferees = 0; $nrOfReferees <= self::MAXNROFREFEREES; $nrOfReferees++) {
                        for ($nrOfFields = 1; $nrOfFields <= self::MAXNROFFIELDS; $nrOfFields++) {
                            if ($nrOfFields < $nrOfSports) {
                                continue;
                            }
                            $sportConfig = $this->getSportConfig($nrOfSports, $nrOfFields);
                            $selfRefereeTeamupVariations = $this->getSelfRefereeTeamupVariations(
                                $nrOfReferees,
                                $nrOfCompetitors,
                                $structureConfig,
                                $sportConfig
                            );
                            for ($nrOfHeadtohead = 1; $nrOfHeadtohead <= self::PLANNING_MAXNROFHEADTOHEAD; $nrOfHeadtohead++) {
                                foreach ($selfRefereeTeamupVariations as $selfRefereeTeamupVariation) {
//                                    $teamup = $selfRefereeTeamupVariation->teamup;
//                                    $selfReferee = $selfRefereeTeamupVariation->selfReferee;
//                                    /*if ($nrOfCompetitors === 6 && $nrOfPoules === 1 && $nrOfSports === 1 && $nrOfFields === 2
//                                        && $nrOfReferees === 0 && $nrOfHeadtohead === 1 && $teamup === false && $selfReferee === false ) {
//                                        $w1 = 1;
//                                    } else*/ /*if ($nrOfCompetitors === 12 && $nrOfPoules === 2 && $nrOfSports === 1 && $nrOfFields === 4
//                                        && $nrOfReferees === 0 && $nrOfHeadtohead === 1 && $teamup === false && $selfReferee === false ) {
//                                        $w1 = 1;
//                                    } else {
//                                        continue;
//                                    }*/
//
//                                    $multipleSports = count($sportConfig) > 1;
//                                    $newNrOfHeadtohead = $nrOfHeadtohead;
//                                    if ($multipleSports) {
//                                        //                                    if( count($sportConfig) === 4 && $sportConfig[0]["nrOfFields"] == 1 && $sportConfig[1]["nrOfFields"] == 1
//                                        //                                        && $sportConfig[2]["nrOfFields"] == 1 && $sportConfig[3]["nrOfFields"] == 1
//                                        //                                        && $teamup === false && $selfReferee === false && $nrOfHeadtohead === 1 && $structureConfig == [3]  ) {
//                                        //                                        $e = 2;
//                                        //                                    }
//                                        $newNrOfHeadtohead = $planningInputSerivce->getSufficientNrOfHeadtohead(
//                                            $nrOfHeadtohead,
//                                            min($structureConfig),
//                                            $teamup,
//                                            $selfReferee,
//                                            $sportConfig
//                                        );
//                                    }
//                                    $planningInput = $this->planningInputRepos->get(
//                                        $structureConfig,
//                                        $sportConfig,
//                                        $nrOfReferees,
//                                        $teamup,
//                                        $selfReferee,
//                                        $newNrOfHeadtohead
//                                    );
//                                    if ($planningInput !== null) {
//                                        continue;
//                                    }
//                                    $planningInput = new InputPlanning(
//                                        $structureConfig,
//                                        $sportConfig,
//                                        $nrOfReferees,
//                                        $teamup,
//                                        $selfReferee,
//                                        $newNrOfHeadtohead
//                                    );
//
//                                    if (!$multipleSports) {
//                                        $maxNrOfFieldsInPlanning = $planningInput->getMaxNrOfBatchGames(
//                                            Resources::REFEREES + Resources::PLACES
//                                        );
//                                        if ($nrOfFields > $maxNrOfFieldsInPlanning) {
//                                            continue;
//                                        }
//                                    } else {
//                                        if ($nrOfFields > self::MAXNROFFIELDS_FOR_MULTIPLESPORTS) {
//                                            continue;
//                                        }
//                                    }
//
//                                    $maxNrOfRefereesInPlanning = $planningInput->getMaxNrOfBatchGames(
//                                        Resources::FIELDS + Resources::PLACES
//                                    );
//                                    if ($nrOfReferees > $maxNrOfRefereesInPlanning) {
//                                        continue;
//                                    }
//
//                                    $this->planningInputRepos->save($planningInput);
//                                    // die();
                                }
                            }
                        }
                    }
                }
                $nrOfPlacesPerPoule = $structureService->getNrOfPlacesPerPoule($nrOfCompetitors, ++$nrOfPoules, true);
            }
        }
        return 0;
    }

    function getSportConfig(int $nrOfSports, int $nrOfFields): array
    {
        $sports = [];
        $nrOfFieldsPerSport = (int)ceil($nrOfFields / $nrOfSports);
        for ($sportNr = 1; $sportNr <= $nrOfSports; $sportNr++) {
            $sports[] = ["nrOfFields" => $nrOfFieldsPerSport, "nrOfGamePlaces" => Sport::TEMPDEFAULT];
            $nrOfFields -= $nrOfFieldsPerSport;
            if (($nrOfFieldsPerSport * ($nrOfSports - $sportNr)) > $nrOfFields) {
                $nrOfFieldsPerSport--;
            }
        }
        return $sports;
    }

    protected function getSelfRefereeTeamupVariations(
        int $nrOfReferees,
        int $nrOfPlaces,
        array $structureConfig,
        array $sportConfig
    ): array {
        $variations = [json_decode(json_encode(["selfReferee" => false, "teamup" => false]))];
        $planningConfigService = new PlanningConfigService();

        $selfRefereeIsAvailable = ($nrOfReferees === 0 && $planningConfigService->canSelfRefereeBeAvailable(
                $nrOfPlaces
            ));
        if ($selfRefereeIsAvailable) {
            $variations = array_merge(
                $variations,
                [
                    json_decode(json_encode(["selfReferee" => true, "teamup" => false]))
                ]
            );
        }

        if ($planningConfigService->canTeamupBeAvailable($structureConfig, $sportConfig)) {
            $variations = array_merge(
                $variations,
                [
                    json_decode(json_encode(["selfReferee" => false, "teamup" => true]))
                ]
            );
            if ($selfRefereeIsAvailable) {
                $variations = array_merge(
                    $variations,
                    [
                        json_decode(json_encode(["selfReferee" => true, "teamup" => true]))
                    ]
                );
            }
        }
        return $variations;
    }
}
