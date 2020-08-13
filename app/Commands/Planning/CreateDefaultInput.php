<?php

namespace App\Commands\Planning;

use App\QueueService;
use Psr\Container\ContainerInterface;
use App\Command;
use Selective\Config\Configuration;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use SportsPlanning\Input;
use SportsPlanning\Input\Repository as PlanningInputRepository;
use FCToernooi\Tournament\StructureRanges as TournamentStructureRanges;
use SportsPlanning\Input\Service as PlanningInputService;
use Sports\Planning\Input\Iterator as PlanningInputIterator;
use SportsHelpers\Range;
use SportsHelpers\SportConfig as SportConfigHelper;
use Sports\Place\Range as PlaceRange;

class CreateDefaultInput extends Command
{
    /**
     * @var PlanningInputRepository
     */
    protected $planningInputRepos;
    /**
     * @var PlanningInputService
     */
    protected $planningInputSerivce;

    public function __construct(ContainerInterface $container)
    {
        // $settings = $container->get('settings');
        $this->planningInputRepos = $container->get(PlanningInputRepository::class);
        $this->planningInputSerivce = new PlanningInputService();
        parent::__construct($container->get(Configuration::class));
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
        parent::configure();

        $this->addOption('placesRange', null, InputOption::VALUE_OPTIONAL, '6-6');
        $this->addOption('sendCreatePlanningMessage', null, InputOption::VALUE_OPTIONAL, 'true');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->initLogger($input, 'cron-planning-create-default-input');
        return $this->createPlanningInputs($input);
    }

    protected function createPlanningInputs(InputInterface $input): int
    {
        $tournamentStructureRanges = new TournamentStructureRanges();
        $pouleRange = new Range(1, 16);
        $planningInputIterator = new PlanningInputIterator(
            $this->getPlaceRange($input, $tournamentStructureRanges),
            $pouleRange,
            new Range(1, 1), // sports
            new Range(1, 10),// fields
            new Range(0, 10),// referees
            new Range(1, 2),// headtohead
        );
        $sendCreatePlanningMessage = false;
        if (strlen($input->getOption("sendCreatePlanningMessage")) > 0) {
            $sendCreatePlanningMessage = filter_var(
                $input->getOption("sendCreatePlanningMessage"),
                FILTER_VALIDATE_BOOL
            );
        }
        $queueService = new QueueService($this->config->getArray('queue'));
        $showNrOfPlaces = [];
        while ($planningInput = $planningInputIterator->increment()) {

            if (array_key_exists($planningInput->getNrOfPlaces(), $showNrOfPlaces) === false) {
                $this->logger->info("TRYING NROFPLACES: " . $planningInput->getNrOfPlaces());
                $showNrOfPlaces[$planningInput->getNrOfPlaces()] = true;
            }

            if ($this->planningInputRepos->getFromInput($planningInput) === null) {
                $this->planningInputRepos->save($planningInput);
                $this->logger->info($this->inputToString($planningInput));
                $this->logger->info("created");
                if ($sendCreatePlanningMessage) {
                    $queueService->sendCreatePlannings($planningInput);
                }
            }
        }
        return 0;
    }

    protected function getPlaceRange(
        InputInterface $input,
        TournamentStructureRanges $tournamentStructureRanges
    ): PlaceRange {
        $placeRange = $tournamentStructureRanges->getFirstPlaceRange();
        if (strlen($input->getOption("placesRange")) > 0) {
            $minMax = explode('-', $input->getOption('placesRange'));
            $placeRange->min = (int)$minMax[0];
            $placeRange->max = (int)$minMax[1];
        }
        return $placeRange;
    }

    protected function inputToString(Input $planningInput): string
    {
        $sports = array_map(
            function (SportConfigHelper $sportConfigHelper): string {
                return '' . $sportConfigHelper->getNrOfFields();
            },
            $planningInput->getSportConfigHelpers()
        );
        return 'structure [' . implode('|', $planningInput->getStructureConfig()) . ']'
            . ', sports [' . implode(',', $sports) . ']'
            . ', referees ' . $planningInput->getNrOfReferees()
            . ', teamup ' . ($planningInput->getTeamup() ? '1' : '0')
            . ', selfRef ' . $planningInput->getSelfReferee()
            . ', nrOfH2h ' . $planningInput->getNrOfHeadtohead();
    }


//    protected function addInput(
//        array $structureConfig,
//        array $sportConfig,
//        int $nrOfReferees,
//        int $nrOfFields,
//        bool $teamup,
//        bool $selfReferee,
//        int $nrOfHeadtohead
//    ) {
//        /*if ($nrOfCompetitors === 6 && $nrOfPoules === 1 && $nrOfSports === 1 && $nrOfFields === 2
//            && $nrOfReferees === 0 && $nrOfHeadtohead === 1 && $teamup === false && $selfReferee === false ) {
//            $w1 = 1;
//        } else*/ /*if ($nrOfCompetitors === 12 && $nrOfPoules === 2 && $nrOfSports === 1 && $nrOfFields === 4
//            && $nrOfReferees === 0 && $nrOfHeadtohead === 1 && $teamup === false && $selfReferee === false ) {
//            $w1 = 1;
//        } else {
//            continue;
//        }*/
//
//        $multipleSports = count($sportConfig) > 1;
//        $newNrOfHeadtohead = $nrOfHeadtohead;
//        if ($multipleSports) {
//            //                                    if( count($sportConfig) === 4 && $sportConfig[0]["nrOfFields"] == 1 && $sportConfig[1]["nrOfFields"] == 1
//            //                                        && $sportConfig[2]["nrOfFields"] == 1 && $sportConfig[3]["nrOfFields"] == 1
//            //                                        && $teamup === false && $selfReferee === false && $nrOfHeadtohead === 1 && $structureConfig == [3]  ) {
//            //                                        $e = 2;
//            //                                    }
//            $newNrOfHeadtohead = $this->planningInputSerivce->getSufficientNrOfHeadtohead(
//                $nrOfHeadtohead,
//                min($structureConfig),
//                $teamup,
//                $selfReferee,
//                $sportConfig
//            );
//        }
//        $planningInput = $this->planningInputRepos->get(
//            $structureConfig,
//            $sportConfig,
//            $nrOfReferees,
//            $teamup,
//            $selfReferee,
//            $newNrOfHeadtohead
//        );
//        if ($planningInput !== null) {
//            return;
//        }
//        $planningInput = new PlanningInput(
//            $structureConfig,
//            $sportConfig,
//            $nrOfReferees,
//            $teamup,
//            $selfReferee,
//            $newNrOfHeadtohead
//        );
//
//        if (!$multipleSports) {
//            $maxNrOfFieldsInPlanning = $planningInput->getMaxNrOfBatchGames(
//                Resources::REFEREES + Resources::PLACES
//            );
//            if ($nrOfFields > $maxNrOfFieldsInPlanning) {
//                return;
//            }
//        } else {
//            if ($nrOfFields > self::MAXNROFFIELDS_FOR_MULTIPLESPORTS) {
//                return;
//            }
//        }
//
//        $maxNrOfRefereesInPlanning = $planningInput->getMaxNrOfBatchGames(
//            Resources::FIELDS + Resources::PLACES
//        );
//        if ($nrOfReferees > $maxNrOfRefereesInPlanning) {
//            return;
//        }
//
//        $this->planningInputRepos->save($planningInput);
//        // die();
//    }
}
