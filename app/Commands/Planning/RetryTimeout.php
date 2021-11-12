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
use SportsPlanning\Planning as PlanningBase;
use SportsPlanning\Planning\Output as PlanningOutput;
use SportsPlanning\Schedule\Repository as ScheduleRepository;
use SportsPlanning\Seeker as PlanningSeeker;
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
        $this->scheduleRepos = $container->get(ScheduleRepository::class);
        $this->mailer = $container->get(Mailer::class);
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
        $this->addOption('maxTimeoutSeconds', null, InputOption::VALUE_OPTIONAL, '5');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->initLogger($input, 'command-planning-retry-timeout');
        $planningSeeker = new PlanningSeeker(
            $this->getLogger(),
            $this->planningInputRepos,
            $this->planningRepos,
            $this->scheduleRepos
        );
        $planningSeeker->enableTimedout($this->getMaxTimeoutSeconds($input));

        try {
            if (!$this->setStatusToStartProcessingTimedout()) {
                $this->getLogger()->info('still processing..');
                $this->setStatusToFinishedProcessingTimedout();
                return 0;
            }

            $planningInput = $this->getPlanningInputFromInput($input);
            $this->createSchedules($planningInput);
            $oldBestPlanning = $planningInput->getBestPlanning();
            $planningSeeker->processInput($planningInput);
            if ($oldBestPlanning !== $planningInput->getBestPlanning()) {
                $this->sendMailWithSuccessfullTimedoutPlanning($planningInput);
            }
            $this->setStatusToFinishedProcessingTimedout();
        } catch (Exception $exception) {
            if ($this->logger !== null) {
                $this->logger->error($exception->getMessage());
            }
        }
        return 0;
    }

    protected function getPlanningInputFromInput(InputInterface $input): PlanningInput
    {
        $inputId = $input->getArgument('inputId');
        if (is_string($inputId) && strlen($inputId) > 0) {
            $planningInput = $this->planningInputRepos->find((int)$inputId);
            if ($planningInput === null) {
                throw new Exception('no timedout-planninginput found for inputId ' . $inputId, E_ERROR);
            }
            return $planningInput;
        }
        $maxTimeOutSeconds = $this->getMaxTimeoutSeconds($input);
        $planningInput = $this->getTimedoutInput(true, $maxTimeOutSeconds);
        if ($planningInput === null) {
            $planningInput = $this->getTimedoutInput(false, $maxTimeOutSeconds);
        }

        if ($planningInput === null) {
            $suffix = 'maxTimeoutSeconds: ' . $maxTimeOutSeconds;
            throw new Exception('no timedout-planning found for ' . $suffix, E_ERROR);
        }
        return $planningInput;
    }

    protected function getTimedoutInput(bool $batchGames, int $maxTimeOutSeconds): PlanningInput|null
    {
        if ($batchGames) {
            return $this->planningInputRepos->findBatchGamestTimedout($maxTimeOutSeconds);
        }
        return $this->planningInputRepos->findGamesInARowTimedout($maxTimeOutSeconds);
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

        $bestPlanning = $planningInput->getBestPlanning();

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

    protected function setStatusToStartProcessingTimedout(): bool
    {
        $fileName = $this->getStatusFileName();
        if (file_exists($fileName)) {
            return false;
        }
        file_put_contents($fileName, 'processing');
        return true;
    }

    protected function setStatusToFinishedProcessingTimedout(): void
    {
        unlink($this->getStatusFileName());
    }

    protected function getStatusFileName(): string
    {
        $loggerSettings = $this->config->getArray('logger');
        return $loggerSettings['path'] . 'timedout-processing.status';
    }
}
