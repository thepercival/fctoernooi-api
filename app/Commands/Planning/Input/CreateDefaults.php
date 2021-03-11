<?php

declare(strict_types=1);

namespace App\Commands\Planning\Input;

use App\Commands\Planning as PlanningCommand;
use App\QueueService;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use SportsPlanning\Input;
use FCToernooi\Tournament\StructureRanges as TournamentStructureRanges;
use SportsPlanning\Input\Service as PlanningInputService;
use SportsPlanning\Input\Iterator as PlanningInputIterator;
use SportsPlanning\Planning;
use SportsHelpers\Range;
use SportsHelpers\SportConfig as SportConfigHelper;
use SportsPlanning\Planning\Output as PlanningOutput;

class CreateDefaults extends PlanningCommand
{
    /**
     * @var PlanningInputService
     */
    protected $planningInputSerivce;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);
        $this->planningInputSerivce = new PlanningInputService();
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
        $this->addOption('recreate', null, InputOption::VALUE_NONE);
        $this->addOption('onlySelfReferee', null, InputOption::VALUE_NONE);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->initLogger($input, 'cron-create-default-planning-input');
        return $this->createPlanningInputs($input);
    }

    protected function createPlanningInputs(InputInterface $input): int
    {
        $tournamentStructureRanges = new TournamentStructureRanges();
        $planningInputIterator = new PlanningInputIterator(
            $this->getPlaceRange($input, $tournamentStructureRanges),
            new Range(1, 64),
            new Range(1, 10),// referees
            new Range(0, 10),// fields
            new Range(1, 2),// gameAmount
        );
        $recreate = $input->getOption("recreate");
        $onlySelfReferee = $input->getOption("onlySelfReferee");
        $queueService = new QueueService($this->config->getArray('queue'));
        $showNrOfPlaces = [];
        $planningOutput = new PlanningOutput($this->logger);
        while ($planningInputIterator->valid()) {
            $planningInputIt = $planningInputIterator->current();
            if ($onlySelfReferee && !$planningInputIt->selfRefereeEnabled()) {
                $planningInputIterator->next();
                continue;
            }
            if (array_key_exists($planningInputIt->getNrOfPlaces(), $showNrOfPlaces) === false) {
                $this->logger->info("TRYING NROFPLACES: " . $planningInputIt->getNrOfPlaces());
                $showNrOfPlaces[$planningInputIt->getNrOfPlaces()] = true;
            }

            $planningInputDb = $this->planningInputRepos->getFromInput($planningInputIt);
            if ($planningInputDb === null) {
                $planningInputDb = $this->createPlanningInput($planningInputIt);
                $queueService->sendCreatePlannings($planningInputDb);
                $planningOutput->outputInput($planningInputDb, "created + message ");
            } elseif ($recreate) {
                $this->planningInputRepos->reset($planningInputDb);
                $queueService->sendCreatePlannings($planningInputDb);
                $planningOutput->outputInput($planningInputDb, "reset + message ");
            } /*else {
                $planningOutput->outputInput($planningInputDb, "no action ");
            } */

            $planningInputIterator->next();
            $this->planningInputRepos->getEM()->clear();
        }
        return 0;
    }

    protected function createPlanningInput(Input $planningInput): Input
    {
        $this->planningInputRepos->save($planningInput);
        $this->planningInputRepos->createBatchGamesPlannings($planningInput);
        return $planningInput;
    }


//    protected function addInput(
//        array $pouleStructure,
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
//            //                                        && $teamup === false && $selfReferee === false && $nrOfHeadtohead === 1 && $pouleStructure == [3]  ) {
//            //                                        $e = 2;
//            //                                    }
//            $newNrOfHeadtohead = $this->planningInputSerivce->getSufficientNrOfHeadtohead(
//                $nrOfHeadtohead,
//                min($pouleStructure),
//                $teamup,
//                $selfReferee,
//                $sportConfig
//            );
//        }
//        $planningInput = $this->planningInputRepos->get(
//            $pouleStructure,
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
//            $pouleStructure,
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
