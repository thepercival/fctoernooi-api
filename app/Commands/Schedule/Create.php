<?php

declare(strict_types=1);

namespace App\Commands\Schedule;

use App\Commands\Schedule as ScheduleCommand;
use Psr\Container\ContainerInterface;
use SportsHelpers\PouleStructure;
use SportsHelpers\Sport\VariantWithFields as SportVariantWithFields;
use SportsPlanning\Input;
use SportsPlanning\Referee\Info as RefereeInfo;
use SportsPlanning\Schedule;
use SportsPlanning\Schedule\Creator as ScheduleCreator;
use SportsPlanning\Schedule\Name as ScheduleName;
use SportsPlanning\Schedule\Output as ScheduleOutput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Create extends ScheduleCommand
{
    private string $customName = 'create-schedule';

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
            ->setDescription('Creates the schedule for the nrOfPlaces')
            // the full command description shown when running the command with
            // the "--help" option
            ->setHelp('Creates the schedule for the nrOfPlaces');
        parent::configure();

        $this->addOption('nrOfPlaces', null, InputOption::VALUE_REQUIRED, '8');

        $this->addOption('nrOfHomePlaces', null, InputOption::VALUE_OPTIONAL);
        $this->addOption('nrOfAwayPlaces', null, InputOption::VALUE_OPTIONAL);
        $this->addOption('nrOfGamePlaces', null, InputOption::VALUE_OPTIONAL);
        $this->addOption('nrOfH2H', null, InputOption::VALUE_OPTIONAL);
        $this->addOption('nrOfGamesPerPlace', null, InputOption::VALUE_OPTIONAL);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $nrOfPlaces = $this->getNrOfPlaces($input);
        $sportVariant = $this->getSportVariant($input);
        $sportsConfigName = (new ScheduleName([$sportVariant]));
        $existingSchedule = $this->scheduleRepos->findOneBy(
            ["nrOfPlaces" => $nrOfPlaces, "sportsConfigName" => $sportsConfigName]
        );

        $loggerName = 'command-' . $this->customName;
        $this->initLogger(
            $this->getLogLevel($input),
            $this->getStreamDef($input, $loggerName),
            $loggerName,
        );

        if ($existingSchedule !== null) {
            // (new ScheduleOutput($this->getLogger()))->output([$existingSchedule]);
            throw new \Exception(
                'schedule(id:' . (string)$existingSchedule->getId() . ') "' . $existingSchedule . '" already exists',
                E_ERROR
            );
        }

        try {
            // against nrOfHomePlaces nrOfAwayPlaces ( nrOfH2H || nrOfGamesPerPlace )
            // together nrOfGamePlaces nrOfGamesPerPlace
            $sportVariantsWithFields = [new SportVariantWithFields($sportVariant, 1)];
            $newSchedule = $this->createSchedule($nrOfPlaces, $sportVariantsWithFields);
            (new ScheduleOutput($this->getLogger()))->output([$newSchedule]);
        } catch (\Exception $exception) {
            $this->getLogger()->error($exception->getMessage());
        }
        return 0;
    }

    /**
     * @param int $nrOfPlaces
     * @param list<SportVariantWithFields> $sportVariantsWithFields
     * @return Schedule
     * @throws \Exception
     */
    protected function createSchedule(
        int $nrOfPlaces,
        array $sportVariantsWithFields
    ): Schedule {
        $scheduleCreator = new ScheduleCreator($this->getLogger());
        $this->getLogger()->info('creating schedule .. ');
        $planningInput = new Input(
            new PouleStructure($nrOfPlaces),
            $sportVariantsWithFields,
            new RefereeInfo(0)
        );
        $schedules = $scheduleCreator->createFromInput($planningInput);
        foreach ($schedules as $schedule) {
            $this->scheduleRepos->save($schedule);
        }
        $schedule = reset($schedules);
        if ($schedule === false) {
            throw new \Exception('no schedule created', E_ERROR);
        }
        return $schedule;
    }
}
