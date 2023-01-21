<?php

declare(strict_types=1);

namespace App\Commands\Schedule;

use App\Commands\Schedule as ScheduleCommand;
use App\Mailer;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Monolog\Processor\UidProcessor;
use Psr\Container\ContainerInterface;

use SportsPlanning\Exception\NoSolutionException;
use SportsPlanning\Input;
use SportsPlanning\Planning\Output as PlanningOutput;
use SportsPlanning\Schedule;
use SportsPlanning\Schedule\Creator as ScheduleCreator;
use SportsPlanning\Schedule\Output as ScheduleOutput;
use SportsPlanning\Exception\TimeoutException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

// php bin/console.php app:update-schedule --nrOfPlaces=10 --toTimeoutState=FirstTryMaxDiff2 --nrOfHomePlaces=2 --nrOfAwayPlaces=2 --nrOfGamesPerPlace=5
class Enhance extends ScheduleCommand
{
    private string $customName = 'enhance-schedule';
    private int $defaultNrToProcess = 5;
    private int $maxNrOfTimeoutSeconds = 160;

    public function __construct(ContainerInterface $container)
    {
        /** @var Mailer|null $mailer */
        $mailer = $container->get(Mailer::class);
        $this->mailer = $mailer;

        parent::__construct($container);
    }

    protected function configure(): void
    {
        $this
            // the name of the command (the part after "bin/console")
            ->setName('app:' . $this->customName)
            // the short description shown while running "php bin/console list"
            ->setDescription('Enhances the schedules')
            // the full command description shown when running the command with
            // the "--help" option
            ->setHelp('Enhances the schedule');

        parent::configure();

        $this->addOption('nrToProcess', null, InputOption::VALUE_OPTIONAL, '' . $this->defaultNrToProcess);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $loggerName = 'command-' . $this->customName;
        $this->initLogger(
            $this->getLogLevel($input),
            $this->getStreamDef($input, $loggerName),
            $loggerName,
        );

        try {
            $nrToProcess = $this->getIntInput($input, 'nrToProcess', $this->defaultNrToProcess);
            $schedules = $this->scheduleRepos->findWithoutMargin($nrToProcess);
            if( count($schedules) > 0 ) {
                foreach( $schedules as $schedule) {
                    $this->initMargin($schedule);
                }
                return 0;
            }

            $schedules = $this->scheduleRepos->findOrderedByNrOfTimeoutSecondsAndMargin($nrToProcess);
            foreach( $schedules as $schedule) {
                $this->getLogger()->info('PROCSESSING SCHEDULE ' .  $schedule . ' ..');
                $this->getLogger()->info('');
                $newSchedule = $this->createWithSmallerMargin($schedule);
                if( $newSchedule === null ) {
                    continue;
                }
                $this->scheduleRepos->remove($schedule,true);
                $this->scheduleRepos->save($newSchedule,true);

                $inputsRecalculating = $this->recalculateInputs($newSchedule);

                $this->logEnhancement($schedule, $newSchedule, $inputsRecalculating);
            }

        } catch (\Exception $exception) {
            if ($this->logger !== null) {
                $this->logger->error($exception->getMessage());
            }
        }
        return 0;
    }

    protected function initMargin(Schedule $schedule): void {
        $margin = (int) ceil($this->getMaxDifference($schedule) / 2);
        $schedule->setSucceededMargin($margin);

        $scheduleOutput = new ScheduleOutput($this->getLogger());
        $this->getLogger()->info('SCHEDULE INIT MARGIN : ' . $margin . ' ( differernce against/with ) : ' . $this->getAgainstDifference($schedule) . ' / ' . $this->getWithDifference($schedule) . ' )');
        $this->getLogger()->info('');
        $scheduleOutput->output([$schedule]);
        $scheduleOutput->outputTotals([$schedule]);

        $this->scheduleRepos->save($schedule, true);
    }

    // vanuit een schedule moet je de maps k

    protected function createWithSmallerMargin(Schedule $schedule): Schedule|null {
        $scheduleCreator = new ScheduleCreator($this->getLogger());
        $this->getLogger()->info('enhancing schedule .. ');
//        $scheduleOutput = new ScheduleOutput($this->getLogger());
        $oldSucceededMargin = $schedule->getSucceededMargin();
//        $scheduleOutput->output([$schedule]);
        $nextNrOfTimeoutSeconds = $this->getNextNrOfSecondsBeforeTimeout($schedule->getNrOfTimeoutSecondsTried());
        try {
            $newSchedule = $scheduleCreator->createBetterSchedule($schedule, $oldSucceededMargin - 1, $nextNrOfTimeoutSeconds);
        } catch (TimeoutException $timeoutExc) {
            $this->getLogger()->info('timeout occured: ' . $timeoutExc->getMessage() . ', update nrOfSeconds to ' . $nextNrOfTimeoutSeconds . ' ' . $schedule);
            $schedule->setNrOfTimeoutSecondsTried($nextNrOfTimeoutSeconds);
            $this->scheduleRepos->save($schedule, true);
            return null;
        } catch (NoSolutionException $noSolutionExp) {
            $this->getLogger()->info('no solution found: ' . $noSolutionExp->getMessage() . ', update nrOfSeconds to -1 for ' . $schedule . ' with margin ' . ($oldSucceededMargin - 1));
            $schedule->setNrOfTimeoutSecondsTried(-1);
            $this->scheduleRepos->save($schedule, true);
            return null;
        } catch (\Exception $exception) {
            $this->getLogger()->error($exception->getMessage());
            return null;
        }
        // als de werkelijke diff kleiner is dan opslaan
        // anders margin naar benedenen, als margin al op minimale margin zit, dan nrOfTimeoutSeconds naar -1
        // dan komt deze niet mmeer m


//        $difference = $this->getMaxDifference($schedule);
//        $newDifference = $this->getMaxDifference($newSchedule);
        // if( $newDifference <= $difference ) {
            $newSchedule->setSucceededMargin( $oldSucceededMargin - 1 );
            $newSchedule->setNrOfTimeoutSecondsTried(0);
            return $newSchedule;
        // }
//        if( $this->getMinimalMargin($newSchedule) < ($oldSucceededMargin - 1)  ) {
//            $this->getLogger()->info('margin - 1, new schedule not better(olddiff=>'.$difference.',newdiff=>'.$newDifference.') (nrOfSeconds to 0) ' . $schedule);
//            $schedule->setNrOfTimeoutSecondsTried(0);
//            $schedule->setSucceededMargin($oldSucceededMargin - 1);
//            $this->scheduleRepos->save($schedule, true);
//            return null;
//        }
//        $this->getLogger()->info('new schedule not better and minimal margin reached (olddiff=>'.$margin.',newmargin=>'.$newMargin.') (STOPPED) ' . $schedule);
//        $this->logEnhancement($schedule, $newSchedule);
//        if( $oldSucceededMargin - 1 >= 0) {
//            $schedule->setSucceededMargin($oldSucceededMargin - 1);
//        }
//        $schedule->setNrOfTimeoutSecondsTried(-1);
//        $this->scheduleRepos->save($schedule, true);
//        return null;
    }

    protected function getNextNrOfSecondsBeforeTimeout(int $nrOfSecondsBeforeTimeout): int {
        if( $nrOfSecondsBeforeTimeout === 0 ) {
            return 10;
        }
        return $nrOfSecondsBeforeTimeout * 2;
    }

//    protected function getScheduleDifference(Schedule $schedule): int {
//        $difference = 0;
//        $poule = $schedule->getPoule();
//        foreach ($schedule->getSportSchedules() as $sportSchedule) {
//            $difference += $this->getDifference($poule, $sportSchedule);
//        }
//        return $difference;
//    }


}
