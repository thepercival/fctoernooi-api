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
use SportsPlanning\Planning;
use SportsPlanning\Planning as PlanningBase;
use SportsPlanning\Planning\Output as PlanningOutput;
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
            ->setName('app:retry-timeout-planning')
            // the short description shown while running "php bin/console list"
            ->setDescription('Retries the timeout-plannings')
            // the full command description shown when running the command with
            // the "--help" option
            ->setHelp('Retries the timeout-plannings');
        parent::configure();

        $this->addArgument('inputId', InputArgument::OPTIONAL, 'input-id');
        $this->addOption('batchGamesRange', null, InputOption::VALUE_OPTIONAL, '1-2');
        $this->addOption('maxNrOfGamesInARow', null, InputOption::VALUE_OPTIONAL, '0');
        $this->addOption('maxTimeoutSeconds', null, InputOption::VALUE_OPTIONAL, '5');
    }

    // waar wil je retry time
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->initLogger($input, 'command-planning-retry-timeout');
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

            $maxTimeOutSeconds = $this->getMaxTimeoutSeconds($input);
            $planningSeeker->setMaxTimeoutSeconds($maxTimeOutSeconds);

            $inputId = $input->getArgument('inputId');
            if (is_string($inputId) && strlen($inputId) > 0) {
                $planningInput = $this->planningInputRepos->find((int)$inputId);
                if ($planningInput === null) {
                    $this->getLogger()->warning('could not find planningInput for inputId "' . $inputId . '"');
                    $this->setStatusToFinishedProcessing();
                    return 0;
                }
                $planningFilter = $this->getPlanningFilter($input);
                if ($planningFilter !== null) {
                    $planning = $planningInput->getPlanning($planningFilter);
                    if ($planning !== null) {
                        $planningSeeker->processPlanning($planning);
                        $this->setStatusToFinishedProcessing();
                        return 0;
                    }
                }
                $this->setStatusToFinishedProcessing();
                $this->processInput($planningInput, $planningSeeker, PlanningType::GamesInARow);
                return 0;
            }

            $planningType = PlanningType::BatchGames;
            $planningInput = $this->planningInputRepos->findTimedout($planningType, $maxTimeOutSeconds);
            if ($planningInput === null) {
                $planningType = PlanningType::GamesInARow;
                $planningInput = $this->planningInputRepos->findTimedout($planningType, $maxTimeOutSeconds);
            }
            if ($planningInput !== null) {
                $this->processInput($planningInput, $planningSeeker, $planningType);
            } else {
                $suffix = 'maxTimeoutSeconds: ' . $this->getMaxTimeoutSeconds($input);
                $this->getLogger()->warning('no timedout-planning found for ' . $suffix);
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
        PlanningType $planningType
    ): void {
        $planningOutput = new PlanningOutput($this->getLogger());
        $planningOutput->outputInput(
            $planningInput,
            'processing timeouts input(' . ((string)$planningInput->getId()) . '): ',
            " .."
        );
        $oldBestPlanning = $planningInput->getBestPlanning(null);
        $this->createSchedules($planningInput);
        if ($planningType === PlanningType::BatchGames) {
            $planningSeeker->processBatchGames($planningInput);
        } else {
            $planningSeeker->processGamesInARow($planningInput);
        }
        if ($oldBestPlanning !== $planningInput->getBestPlanning(null)) {
            $this->sendMailWithSuccessfullTimedoutPlanning($planningInput);
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

    protected function getMaxTimeoutSeconds(InputInterface $input): int
    {
        $maxTimeoutSeconds = $input->getOption('maxTimeoutSeconds');
        if (is_string($maxTimeoutSeconds) && strlen($maxTimeoutSeconds) > 0) {
            return (int)$maxTimeoutSeconds;
        }
        return PlanningBase::MINIMUM_TIMEOUTSECONDS;
    }

    protected function sendMailWithSuccessfullTimedoutPlanning(PlanningInput $planningInput): void
    {
        $msg = 'new planning(timedout) found for inputid: ' . (string)$planningInput->getId();
        $msg .= '(' . $planningInput->getUniqueString() . ')';
        $this->getLogger()->info($msg);
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

        $bestPlanning = $planningInput->getBestPlanning(null);

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
