<?php

declare(strict_types=1);

namespace App\Commands\Planning;

use App\Commands\Planning as PlanningCommand;
use App\Mailer;
use Exception;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Monolog\Processor\UidProcessor;
use Psr\Container\ContainerInterface;
use SportsHelpers\PouleStructure;
use SportsPlanning\Input as PlanningInput;
use SportsPlanning\Planning\Filter as PlanningFilter;
use SportsPlanning\Planning\Output as PlanningOutput;
use SportsPlanning\Planning\TimeoutConfig;
use SportsPlanning\Planning\TimeoutState;
use SportsPlanning\Planning\Type as PlanningType;
use SportsPlanning\Schedule\Repository as ScheduleRepository;
use SportsPlanning\Seeker as PlanningSeeker;
use SportsPlanning\Seeker\Timeout as PlanningTimeoutSeeker;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class RetryTimeout extends PlanningCommand
{
    private string $customName = 'retry-timeout-planning';
    protected ScheduleRepository $scheduleRepos;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);

        /** @var ScheduleRepository $scheduleRepos */
        $scheduleRepos = $container->get(ScheduleRepository::class);
        $this->scheduleRepos = $scheduleRepos;

        /** @var Mailer|null $mailer */
        $mailer = $container->get(Mailer::class);
        $this->mailer = $mailer;
    }

    protected function configure(): void
    {
        $this
            // the name of the command (the part after "bin/console")
            ->setName('app:' . $this->customName)
            // the short description shown while running "php bin/console list"
            ->setDescription('Retries the timeout-plannings')
            // the full command description shown when running the command with
            // the "--help" option
            ->setHelp('Retries the timeout-plannings');
        parent::configure();

        $default = PlanningType::BatchGames->name;
        $this->addOption('planningType', null, InputOption::VALUE_REQUIRED, $default);
        $default = TimeoutState::Time1xNoSort->value;
        $this->addOption('timeoutState', null, InputOption::VALUE_REQUIRED, $default);

        $this->addArgument('inputId', InputArgument::OPTIONAL, 'input-id');
        $this->addOption('batchGamesRange', null, InputOption::VALUE_OPTIONAL, '1-2');
        $this->addOption('maxNrOfGamesInARow', null, InputOption::VALUE_OPTIONAL, '0');
    }

    // waar wil je retry time
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $loggerName = 'command-' . $this->customName;
        $this->initLogger(
            $this->getLogLevel($input),
            $this->getStreamDef($input, $loggerName),
            $loggerName,
        );

        $planningSeeker = new PlanningTimeoutSeeker(
            $this->getLogger(),
            $this->planningInputRepos,
            $this->planningRepos,
            $this->scheduleRepos
        );

        try {
            if (!$this->setStatusToStartProcessing()) {
                $this->getLogger()->info('still processing..');
                $this->setStatusToFinishedProcessing();
                return 0;
            }

            $planningType = $this->getPlanningType($input);
            $timeoutState = $this->getTimeoutState($input);

            $inputId = $input->getArgument('inputId');
            if (is_string($inputId) && strlen($inputId) > 0) {
                return $this->processInputManual(
                    (int)$inputId,
                    $this->getPlanningFilter($input),
                    $planningSeeker,
                    $planningType,
                    $timeoutState
                );
            }

            $planningInput = $this->planningInputRepos->findTimedout($planningType, $timeoutState);
            $msg = 'type "' . $planningType->name . '" and timeoutState "' . $timeoutState->value . '"';
            if ($planningInput !== null) {
                $this->getLogger()->info('################ retry for ' . $msg . ' ################');
                $this->processInput($planningInput, $planningSeeker, $planningType, $timeoutState);
            } else {
                $this->getLogger()->warning('no planning found for ' . $msg);
            }

            $this->setStatusToFinishedProcessing();
        } catch (Exception $exception) {
            if ($this->logger !== null) {
                $this->logger->error($exception->getMessage());
            }
        }
        return 0;
    }


    protected function processInput(
        PlanningInput $planningInput,
        PlanningTimeoutSeeker $planningSeeker,
        PlanningType $planningType,
        TimeoutState $timeoutState,
    ): void {
        $planningOutput = new PlanningOutput($this->getLogger());
        $planningOutput->outputInput(
            $planningInput,
            'processing timeouts input(' . ((string)$planningInput->getId()) . '): ',
            '..'
        );
        $oldBestPlanning = $planningInput->getBestPlanning(null);
        $this->createSchedules($planningInput);
        if ($planningType === PlanningType::BatchGames) {
            $planningSeeker->processBatchGames($planningInput, $timeoutState);
        } else {
            $planningSeeker->processGamesInARow($planningInput, $timeoutState);
        }
        $newBestPlanning = $planningInput->getBestPlanning(null);
        if ($oldBestPlanning !== $newBestPlanning) {
            $this->sendMailWithSuccessfullTimedoutPlanning($planningInput);

            if ($planningType === PlanningType::BatchGames) {
                $planningSeeker = new PlanningSeeker(
                    $this->getLogger(),
                    $this->planningInputRepos,
                    $this->planningRepos,
                    $this->scheduleRepos
                );
                $schedules = $this->scheduleRepos->findByInput($planningInput);
                $planningSeeker->processGamesInARowPlannings($planningInput, $schedules);
            }
        }
    }

    protected function processInputManual(
        int $inputId,
        PlanningFilter|null $planningFilter,
        PlanningTimeoutSeeker $planningSeeker,
        PlanningType $planningType,
        TimeoutState $timeoutState
    ): int {
        $planningInput = $this->planningInputRepos->find($inputId);
        if ($planningInput === null) {
            $this->getLogger()->warning('could not find planningInput for inputId "' . $inputId . '"');
            $this->setStatusToFinishedProcessing();
            return 0;
        }

        if ($planningFilter !== null) {
            $planning = $planningInput->getPlanning($planningFilter);
            if ($planning !== null) {
                $planningSeeker->processPlanning($planning);
                $this->setStatusToFinishedProcessing();
                return 0;
            }
        }
        $this->processInput($planningInput, $planningSeeker, $planningType, $timeoutState);
        $this->setStatusToFinishedProcessing();
        return 0;
    }

    protected function getPlanningType(InputInterface $input): PlanningType
    {
        $planningType = $input->getOption('planningType');
        if ($planningType === null) {
            throw new \Exception('unknown planningtype', E_ERROR);
        }
        $planningType = strtolower($planningType);
        if ($planningType === strtolower(PlanningType::BatchGames->name)) {
            return PlanningType::BatchGames;
        } elseif ($planningType === strtolower(PlanningType::GamesInARow->name)) {
            return PlanningType::GamesInARow;
        }
        throw new \Exception('unknown planningtype', E_ERROR);
    }

    protected function getTimeoutState(InputInterface $input): TimeoutState
    {
        $timeoutState = $input->getOption('timeoutState');
        if ($timeoutState === null) {
            throw new \Exception('unknown planningtype', E_ERROR);
        }
        $timeoutState = strtolower($timeoutState);
        if ($timeoutState === strtolower(TimeoutState::Time1xNoSort->value)) {
            return TimeoutState::Time1xNoSort;
        } elseif ($timeoutState === strtolower(TimeoutState::Time4xSort->value)) {
            return TimeoutState::Time4xSort;
        } elseif ($timeoutState === strtolower(TimeoutState::Time4xNoSort->value)) {
            return TimeoutState::Time4xNoSort;
        } elseif ($timeoutState === strtolower(TimeoutState::Time10xSort->value)) {
            return TimeoutState::Time10xSort;
        } elseif ($timeoutState === strtolower(TimeoutState::Time10xNoSort->value)) {
            return TimeoutState::Time10xNoSort;
        }
        return (new TimeoutConfig())->nextTimeoutState(null);
    }

    protected function sendMailWithSuccessfullTimedoutPlanning(PlanningInput $planningInput): void
    {
        // CMD OUTPUT
        $planningOutput = new PlanningOutput($this->getLogger());
        $msg = 'new planning(timedout) found for ';
        $bestPlanning = $planningInput->getBestPlanning(null);
        $planningOutput->outputWithGames($bestPlanning, true, $msg);
        $planningOutput->outputWithTotals($bestPlanning, false);

        $stream = fopen('php://memory', 'r+');
        if ($stream === false || $this->mailer === null) {
            return;
        }
        $loggerOutput = new Logger('successful-retry-planning-output-logger');
        $loggerOutput->pushProcessor(new UidProcessor());
        $handler = new StreamHandler($stream, Logger::INFO);
        $loggerOutput->pushHandler($handler);

        $planningOutput = new PlanningOutput($loggerOutput);
        $planningOutput->outputInput($planningInput, null, 'is successful');

        $planningOutput = new PlanningOutput($loggerOutput);
        $planningOutput->outputWithGames($bestPlanning, true);
        $planningOutput->outputWithTotals($bestPlanning, false);

        rewind($stream);
        $emailBody = stream_get_contents($stream/*$handler->getStream()*/);
        $this->mailer->sendToAdmin(
            'timeout-planning successful',
            $emailBody === false ? 'unable to convert stream into string' : $emailBody,
            true
        );
    }

    /**
     * @param InputInterface $input
     * @return PouleStructure|null
     */
    protected function getPouleStructure(InputInterface $input): ?PouleStructure
    {
        $pouleStructureParam = $input->getOption('pouleStructure');
        if (!is_string($pouleStructureParam) || strlen($pouleStructureParam) === 0) {
            return null;
        }
        $pouleStructureArray = explode(',', $pouleStructureParam);
        $pouleStructure = [];
        foreach ($pouleStructureArray as $nrOfPlaces) {
            $pouleStructure[] = (int)$nrOfPlaces;
        }
        return new PouleStructure(...$pouleStructure);
    }

    protected function setStatusToStartProcessing(): bool
    {
        $fileName = $this->getStatusFileName();
        if (file_exists($fileName)) {
            return false;
        }
        file_put_contents($fileName, 'processing');
        return true;
    }

    protected function setStatusToFinishedProcessing(): void
    {
        unlink($this->getStatusFileName());
    }

    protected function getStatusFileName(): string
    {
        $loggerSettings = $this->config->getArray('logger');
        return $loggerSettings['path'] . 'timedout-processing.status';
    }
}
