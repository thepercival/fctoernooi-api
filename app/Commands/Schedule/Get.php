<?php

declare(strict_types=1);

namespace App\Commands\Schedule;

use App\Commands\Schedule as ScheduleCommand;
use Psr\Container\ContainerInterface;
use SportsPlanning\Schedule\Name as ScheduleName;
use SportsPlanning\Schedule\Output as ScheduleOutput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Get extends ScheduleCommand
{
    private string $customName = 'get-schedule';

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);
    }

    protected function configure(): void
    {
        $this
            // the name of the command (the part after "bin/console")
            ->setName('app:' . $this->customName)
            // the short description shown while running "php bin/console list"
            ->setDescription('Gets the schedule for the nrOfPlaces')
            // the full command description shown when running the command with
            // the "--help" option
            ->setHelp('Gets the schedule for the nrOfPlaces');
        parent::configure();

        $this->addOption('nrOfPlaces', null, InputOption::VALUE_REQUIRED, '8');
        $defaultValue = '[{"nrOfHomePlaces":1,"nrOfAwayPlaces":1,"nrOfH2H":1}]';
        $this->addOption('sportsConfigName', null, InputOption::VALUE_OPTIONAL, $defaultValue);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $nrOfPlaces = $this->getNrOfPlaces($input);
        $sportsConfigName = (string)new ScheduleName([$this->getSportVariant($input)]);
        $existingSchedule = $this->scheduleRepos->findOneBy(
            ['nrOfPlaces' => $nrOfPlaces, 'sportsConfigName' => $sportsConfigName]
        );
        if ($existingSchedule === null) {
            throw new \Exception('schedule not found', E_ERROR);
        }

        try {
            $loggerName = 'command-' . $this->customName;
            $this->initLogger(
                $this->getLogLevel($input),
                $this->getStreamDef($input, $loggerName),
                $loggerName,
            );
            (new ScheduleOutput($this->getLogger()))->output([$existingSchedule]);
        } catch (\Exception $exception) {
            if ($this->logger !== null) {
                $this->logger->error($exception->getMessage());
            }
        }
        return 0;
    }
}
