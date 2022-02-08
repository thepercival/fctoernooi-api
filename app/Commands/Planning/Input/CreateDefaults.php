<?php

declare(strict_types=1);

namespace App\Commands\Planning\Input;

use App\Commands\Planning as PlanningCommand;
use App\QueueService;
use Doctrine\ORM\EntityManagerInterface;
use FCToernooi\Tournament\CustomPlaceRanges as TournamentStructureRanges;
use Psr\Container\ContainerInterface;
use SportsHelpers\PlaceRanges;
use SportsHelpers\SportRange;
use SportsPlanning\Input;
use SportsPlanning\Input\Iterator as PlanningInputIterator;
use SportsPlanning\Input\Service as PlanningInputService;
use SportsPlanning\Planning\Output as PlanningOutput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CreateDefaults extends PlanningCommand
{
    protected PlanningInputService $planningInputSerivce;
    protected EntityManagerInterface $entityManager;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);

        $this->planningInputSerivce = new PlanningInputService();

        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(EntityManagerInterface::class);
        $this->entityManager = $entityManager;
    }

    protected function configure(): void
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
        $this->initLogger($input, 'command-create-default-planning-input');
        return $this->createPlanningInputs($input);
    }

    protected function createPlanningInputs(InputInterface $input): int
    {
        $planningInputIterator = new PlanningInputIterator(
            $this->getPlacesRange($input),
            new SportRange(PlaceRanges::MinNrOfPlacesPerPoule, TournamentStructureRanges::MaxNrOfPlacesPerPouleSmall),
            new SportRange(1, 64),
            new SportRange(0, 10),// referees
            new SportRange(1, 10),// fields
            new SportRange(1, 2),// gameAmount
        );
        $recreate = $input->getOption('recreate');
        $recreate = is_bool($recreate) ? $recreate : false;
        $onlySelfReferee = $input->getOption('onlySelfReferee');
        $onlySelfReferee = is_bool($onlySelfReferee) ? $onlySelfReferee : false;
        $queueService = new QueueService($this->config->getArray('queue'));
        $showNrOfPlaces = [];
        $planningOutput = new PlanningOutput($this->getLogger());
        while ($planningInputIt = $planningInputIterator->current()) {
            if ($onlySelfReferee && !$planningInputIt->selfRefereeEnabled()) {
                $planningInputIterator->next();
                continue;
            }
            if (array_key_exists($planningInputIt->getNrOfPlaces(), $showNrOfPlaces) === false) {
                $this->getLogger()->info('TRYING NROFPLACES: ' . $planningInputIt->getNrOfPlaces());
                $showNrOfPlaces[$planningInputIt->getNrOfPlaces()] = true;
            }

            $planningInputDb = $this->planningInputRepos->getFromInput($planningInputIt);
            if ($planningInputDb === null) {
                $planningInputDb = $this->createPlanningInput($planningInputIt);
                $queueService->sendCreatePlannings($planningInputDb);
                $planningOutput->outputInput($planningInputDb, 'created + message ');
            } elseif ($recreate) {
                // $this->planningInputRepos->reset($planningInputDb);
                $queueService->sendCreatePlannings($planningInputDb);
                $planningOutput->outputInput($planningInputDb, 'reset + message ');
            } /*else {
                $planningOutput->outputInput($planningInputDb, "no action ");
            } */

            $planningInputIterator->next();
            $this->entityManager->clear();
        }
        return 0;
    }

    protected function createPlanningInput(Input $planningInput): Input
    {
        $this->planningInputRepos->save($planningInput);
        $this->planningInputRepos->createBatchGamesPlannings($planningInput);
        return $planningInput;
    }
}
