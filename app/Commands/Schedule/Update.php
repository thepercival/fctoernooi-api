<?php

declare(strict_types=1);

namespace App\Commands\Schedule;

use App\Commands\Schedule as ScheduleCommand;
use Psr\Container\ContainerInterface;
use SportsHelpers\PouleStructure;
use SportsHelpers\Sport\Variant\Against\GamesPerPlace as AgainstGpp;
use SportsHelpers\Sport\Variant\Against\H2h as AgainstH2h;
use SportsHelpers\Sport\Variant\AllInOneGame as AllInOneGame;
use SportsHelpers\Sport\Variant\Single as Single;
use SportsHelpers\Sport\VariantWithFields as SportVariantWithFields;
use SportsPlanning\Input;
use SportsPlanning\Planning\TimeoutConfig;
use SportsPlanning\Referee\Info as RefereeInfo;
use SportsPlanning\Schedule;
use SportsPlanning\Schedule\Creator as ScheduleCreator;
use SportsPlanning\Schedule\Name as ScheduleName;
use SportsPlanning\Schedule\Output as ScheduleOutput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

// php bin/console.php app:update-schedule --nrOfPlaces=10 --toTimeoutState=FirstTryMaxDiff2 --nrOfHomePlaces=2 --nrOfAwayPlaces=2 --nrOfGamesPerPlace=5
class Update extends ScheduleCommand
{
    private string $customName = 'update-schedule';

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
            ->setDescription('Update the schedule for the nrOfPlaces')
            // the full command description shown when running the command with
            // the "--help" option
            ->setHelp('Update the schedule for the nrOfPlaces');

        parent::configure();

        $this->addOption('nrOfPlaces', null, InputOption::VALUE_REQUIRED, '8');
        $defaultValue = '[{"nrOfHomePlaces":1,"nrOfAwayPlaces":1,"nrOfH2H":1}]';
        $this->addOption('sportsConfigName', null, InputOption::VALUE_OPTIONAL, $defaultValue);
        $this->addOption('margin', null, InputOption::VALUE_OPTIONAL, '1');
        $this->addOption('nrOfSecondsBeforeTimeout', null, InputOption::VALUE_OPTIONAL, '5');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $nrOfPlaces = $this->getNrOfPlaces($input);
        $schedule = $this->scheduleRepos->findOneBy(
            ['nrOfPlaces' => $nrOfPlaces, 'sportsConfigName' => $input->getOption('sportsConfigName')]
        );
        $margin = $this->getIntInput($input, 'margin', 0);
        $nrOfSecondsBeforeTimeout = $this->getIntInput($input, 'nrOfSecondsBeforeTimeout', 0);

        $loggerName = 'command-' . $this->customName;
        $this->initLogger(
            $this->getLogLevel($input),
            $this->getStreamDef($input, $loggerName),
            $loggerName,
        );

        if ($schedule === null) {
            throw new \Exception('schedule does not exists',E_ERROR );
        }

        try {
            $scheduleOutput = new ScheduleOutput($this->getLogger());
            $this->getLogger()->info('');
            $this->getLogger()->info('CURRENT SCHEDULE : margin => ' . $schedule->getSucceededMargin() . ' , diff(against/with) => ' . $this->getMaxDifference($schedule) . '(' . $this->getAgainstDifference($schedule) . '/' . $this->getWithDifference($schedule) . ')');
            $this->getLogger()->info('');
            $scheduleOutput->output([$schedule]);
            $scheduleOutput->outputTotals([$schedule]);
            $newSchedule = $this->replaceWithBetterSchedule($schedule, $margin, $nrOfSecondsBeforeTimeout);

            $inputsRecalculating = $this->recalculateInputs($newSchedule);

            $this->logEnhancement($schedule, $newSchedule, $inputsRecalculating);

//            $this->getLogger()->info('');
//            $this->getLogger()->info('NEW SCHEDULE : margin => ' . $margin . ' , diff(against/with) => ' . $this->getMaxDifference($newSchedule) . '(' . $this->getAgainstDifference($newSchedule) . '/' . $this->getWithDifference($newSchedule) . ')');
//            $this->getLogger()->info('');

            $scheduleOutput->output([$newSchedule]);
            $scheduleOutput->outputTotals([$newSchedule]);
        } catch (\Exception $exception) {
            $this->getLogger()->error($exception->getMessage());
        }
        return 0;
    }

    protected function replaceWithBetterSchedule(
        Schedule $schedule,
        int $margin,
        int $nrOfSecondsBeforeTimeout
    ): Schedule {
        $scheduleCreator = new ScheduleCreator($this->getLogger());
        $this->getLogger()->info('updating schedule .. ');

        $newSchedule = $scheduleCreator->createBetterSchedule($schedule, $margin, $nrOfSecondsBeforeTimeout);

        $this->scheduleRepos->remove($schedule, true);
        $newSchedule->setSucceededMargin($margin);
        $this->scheduleRepos->save($newSchedule, true);

        return $newSchedule;
    }
}
