<?php

declare(strict_types=1);

namespace App\Commands;

use App\Command;
use App\QueueService\Planning as PlanningQueueService;
use Doctrine\ORM\EntityManagerInterface;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Monolog\Processor\UidProcessor;
use Psr\Container\ContainerInterface;
use Selective\Config\Configuration;
use SportsHelpers\GameMode;
use SportsHelpers\Sport\Variant\Against\GamesPerPlace as AgainstGpp;
use SportsHelpers\Sport\Variant\Against\H2h as AgainstH2h;
use SportsHelpers\Sport\Variant\AllInOneGame;
use SportsHelpers\Sport\Variant\Single;
use SportsPlanning\Input;
use SportsPlanning\Planning\Output as PlanningOutput;
use SportsPlanning\Poule;
use SportsPlanning\Schedule as ScheduleBase;
use SportsPlanning\Combinations\AssignedCounter;
use SportsPlanning\Schedule\Output as ScheduleOutput;
use SportsPlanning\Schedule\Repository as ScheduleRepository;
use SportsPlanning\Input\Repository as InputRepository;
use SportsPlanning\Schedule\Sport as SportSchedule;
use Symfony\Component\Console\Input\InputInterface;

class Schedule extends Command
{
    protected ScheduleRepository $scheduleRepos;
    protected InputRepository $inputRepository;
    protected EntityManagerInterface $entityManager;

    use InputHelper;

    public function __construct(ContainerInterface $container)
    {
        /** @var ScheduleRepository $scheduleRepos */
        $scheduleRepos = $container->get(ScheduleRepository::class);
        $this->scheduleRepos = $scheduleRepos;

        /** @var InputRepository $inputRepos */
        $inputRepos = $container->get(InputRepository::class);
        $this->inputRepository = $inputRepos;

        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(EntityManagerInterface::class);
        $this->entityManager = $entityManager;

        /** @var Configuration $config */
        $config = $container->get(Configuration::class);
        parent::__construct($config);
    }

    protected function getNrOfPlaces(InputInterface $input): int
    {
        $nrOfPlaces = $this->getIntInput($input, 'nrOfPlaces');
        if ($nrOfPlaces === 0) {
            throw new \Exception('incorrect nrOfPlaces "' . $nrOfPlaces . '"', E_ERROR);
        }
        return $nrOfPlaces;
    }

    /**
     * @param InputInterface $input
     * @return GameMode
     * @throws \Exception
     */
    protected function getGameMode(InputInterface $input): GameMode
    {
        $nrOfHomePlaces = $this->getIntParam($input, 'nrOfHomePlaces', 0);
        if ($nrOfHomePlaces > 0) {
            return GameMode::Against;
        }
        $nrOfGamePlaces = $this->getIntParam($input, 'nrOfGamePlaces', 0);
        if ($nrOfGamePlaces > 0) {
            return GameMode::Single;
        }
        return GameMode::AllInOneGame;
    }

    protected function getIntParam(InputInterface $input, string $param, int $default = null): int
    {
        $valueAsString = $input->getOption($param);
        if (!is_string($valueAsString) || strlen($valueAsString) === 0) {
            if ($default !== null) {
                return $default;
            }
            throw new \Exception('incorrect ' . $param . ' "' . $valueAsString . '"', E_ERROR);
        }
        return (int)$valueAsString;
    }

    protected function getSportVariant(
        InputInterface $input
    ): AgainstH2h|AgainstGpp|AllInOneGame|Single {
        $gameMode = $this->getGameMode($input);
        if ($gameMode === GameMode::Against) {
            $nrOfH2H = $this->getIntParam($input, 'nrOfH2H', 0);
            if ($nrOfH2H > 0) {
                return new AgainstH2h(
                    $this->getIntParam($input, 'nrOfHomePlaces'),
                    $this->getIntParam($input, 'nrOfAwayPlaces'),
                    $nrOfH2H
                );
            }
            return new AgainstGpp(
                $this->getIntParam($input, 'nrOfHomePlaces'),
                $this->getIntParam($input, 'nrOfAwayPlaces'),
                $this->getIntParam($input, 'nrOfGamesPerPlace')
            );
        }
        if ($gameMode === GameMode::AllInOneGame) {
            return new AllInOneGame($this->getIntParam($input, 'nrOfGamesPerPlace'));
        }
        return new Single(
            $this->getIntParam($input, 'nrOfGamePlaces'),
            $this->getIntParam($input, 'nrOfGamesPerPlace')
        );
    }

    protected function getMaxDifference(ScheduleBase $schedule): int
    {
        return max($this->getAgainstDifference($schedule), $this->getWithDifference($schedule) );
    }

    protected function getAgainstDifference(ScheduleBase $schedule): int
    {
        $poule = $schedule->getPoule();

        $sportVariants = array_values($schedule->createSportVariants()->toArray());

        $assignedCounter = new AssignedCounter($poule, $sportVariants);
        foreach( $schedule->getSportSchedules() as $sportSchedule) {
            $sportVariant = $sportSchedule->createVariant();
            if (!($sportVariant instanceof AgainstGpp)) {
                continue;
            }
            $homeAways = $sportSchedule->convertGamesToHomeAways();
            $assignedCounter->assignHomeAways($homeAways);
        }
        return $assignedCounter->getAgainstAmountDifference();
    }

    protected function getWithDifference(ScheduleBase $schedule): int
    {
        $poule = $schedule->getPoule();

        $sportVariants = array_values($schedule->createSportVariants()->toArray());

        $assignedCounter = new AssignedCounter($poule, $sportVariants);
        foreach( $schedule->getSportSchedules() as $sportSchedule) {
            $sportVariant = $sportSchedule->createVariant();
            if (!($sportVariant instanceof AgainstGpp) /*&& !($sportVariant instanceof Single)*/) {
                continue;
            }
            $homeAways = $sportSchedule->convertGamesToHomeAways();
            $assignedCounter->assignHomeAways($homeAways);
        }
        return $assignedCounter->getWithAmountDifference();
    }

    /**
     * @param ScheduleBase $schedule
     * @return list<AgainstGpp>
     */
    protected function getAgainstGppSportVariants(ScheduleBase $schedule): array
    {
        $sportVariants = array_values($schedule->createSportVariants()->toArray());
        return array_values(array_filter($sportVariants, function(AgainstGpp|AgainstH2h|Single|AllInOneGame $sportVariant): bool {
            return $sportVariant instanceof AgainstGpp;
        }));
    }

    /**
     * @param ScheduleBase $schedule
     * @return list<Input>
     * @throws \Exception
     */
    public function recalculateInputs(ScheduleBase $schedule): array {
        $planningInputs = $this->inputRepository->findByScheduleSports($schedule);
        // remove and call queue
        $queueService = new PlanningQueueService($this->config->getArray('queue'));
        $planningOutput = new PlanningOutput($this->getLogger());

        $inputsRecalculating = [];
        foreach ($planningInputs as $planningInput) {
            if( !$this->hasGroupWithNrOfPlaces($planningInput, $schedule) ) {
                continue;
            }
            $inputsRecalculating[] = $planningInput;
            $queueService->sendCreatePlannings($planningInput);
            $planningOutput->outputInput($planningInput, 'send recalculate-message to queue for');
//            $this->entityManager->clear();
        }
        return $inputsRecalculating;
    }

    protected function hasGroupWithNrOfPlaces(Input $planningInput, ScheduleBase $schedule): bool {
        foreach( $planningInput->getPoules() as $poule ) {
            if( $poule->getPlaces()->count() === $schedule->getPoule()->getPlaces()->count() ) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param ScheduleBase $schedule
     * @param ScheduleBase $newSchedule
     * @param list<Input> $inputs
     * @return void
     * @throws \Exception
     */
    protected function logEnhancement(ScheduleBase $schedule, ScheduleBase $newSchedule, array $inputs): void {
        $stream = fopen('php://memory', 'r+');
        if ($stream === false && $this->mailer === null) {
            throw new \Exception('no stream or mailer available');
        }

        if ($this->config->getString("environment") === "production") {
            $logger = new Logger('successfully-enhanced-schedule-output-logger');
            $logger->pushProcessor(new UidProcessor());
            $handler = new StreamHandler($stream, Logger::INFO);
            $logger->pushHandler($handler);
        } else {
            $logger = $this->getLogger();
        }

        $scheduleOutput = new ScheduleOutput($logger);
        $logger->info('OLD SCHEDULE : margin => ' . $schedule->getSucceededMargin() . ' , diff(against/with) => ' . $this->getMaxDifference($schedule) . '(' . $this->getAgainstDifference($schedule) . '/' . $this->getWithDifference($schedule) . ')');
        $logger->info('');
        $scheduleOutput->output([$schedule]);
        $scheduleOutput->outputTotals([$schedule]);

        $logger->info('');
        $logger->info('NEW SCHEDULE : margin => ' . $newSchedule->getSucceededMargin() . ' , diff(against/with) => ' . $this->getMaxDifference($newSchedule) . '(' . $this->getAgainstDifference($newSchedule) . '/' . $this->getWithDifference($newSchedule) . ')');
        $logger->info('');
        $scheduleOutput->output([$newSchedule]);
        $scheduleOutput->outputTotals([$newSchedule]);

        $logger->info('');
        $logger->info('INPUTS RECALCULATING : ' );
        foreach ($inputs as $input) {
            $logger->info('     ' . $input->getId() . ' : ' . $input->getUniqueString());
        }

        if ($this->mailer !== null && $this->config->getString("environment") === "production") {
            rewind($stream);
            $emailBody = stream_get_contents($stream/*$handler->getStream()*/);
            $this->mailer->sendToAdmin(
                'schedule enhanced successfully',
                $emailBody === false ? 'unable to convert stream into string' : $emailBody,
                true
            );
        }
    }
}
