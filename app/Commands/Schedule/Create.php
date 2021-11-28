<?php

declare(strict_types=1);

namespace App\Commands\Schedule;

use App\Commands\Schedule as ScheduleCommand;
use Psr\Container\ContainerInterface;
use SportsPlanning\Combinations\GamePlaceStrategy;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Create extends ScheduleCommand
{
    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);
    }

    protected function configure(): void
    {
        $this
            // the name of the command (the part after "bin/console")
            ->setName('app:create-schedule')
            // the short description shown while running "php bin/console list"
            ->setDescription('Creates the schedule for the nrOfPlaces')
            // the full command description shown when running the command with
            // the "--help" option
            ->setHelp('Creates the schedule for the nrOfPlaces');
        parent::configure();

        $this->addOption('nrOfPlaces', null, InputOption::VALUE_REQUIRED, '8');
        $defaultValue = '[{"nrOfHomePlaces":1,"nrOfAwayPlaces":1,"nrOfH2H":1}]';
        $this->addOption('sportConfigName', null, InputOption::VALUE_OPTIONAL, $defaultValue);
        $defaultValue = (string)GamePlaceStrategy::EquallyAssigned;
        $this->addOption('gamePlaceStrategy', null, InputOption::VALUE_OPTIONAL, $defaultValue);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $nrOfPlaces = $this->getNrOfPlaces($input);
        $gamePlaceStrategy = $this->getGamePlaceStrategy($input);
        $sportsConfigName = $this->getSportsConfigName($input);
        $existingSchedule = $this->scheduleRepos->findOneBy([
                                                                "nrOfPlaces" => $nrOfPlaces,
                                                                "gamePlaceStrategy" => $gamePlaceStrategy,
                                                                "sportsConfigName" => $sportsConfigName
                                                            ]);
        if ($existingSchedule === null) {
            throw new \Exception('schedule not found', E_ERROR);
        }

        try {
            $this->initLogger($input, 'command-schedule-create');
        } catch (\Exception $exception) {
            if ($this->logger !== null) {
                $this->logger->error($exception->getMessage());
            }
        }
        return 0;
    }


}