<?php

namespace App\Commands\Planning;

use App\Mailer;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Monolog\Processor\UidProcessor;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Voetbal\Planning\Input as PlanningInput;
use Voetbal\Planning as PlanningBase;
use Voetbal\Output\Planning as PlanningOutput;
use Voetbal\Output\Planning\Batch as BatchOutput;
use Voetbal\Planning\Input\Service as PlanningInputService;
use Voetbal\Planning\Seeker as PlanningSeeker;
use App\Commands\Planning as PlanningCommand;

class RetryTimeout extends PlanningCommand
{
    /**
     * @var Mailer
     */
    protected $mailer;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);
        $this->mailer = $container->get(Mailer::class);
    }

    protected function configure()
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

        $this->addArgument('planningId', InputArgument::OPTIONAL, 'planning-id');
        $this->addOption('structureConfig', null, InputOption::VALUE_OPTIONAL, '6,6');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->initLogger($input, 'cron-retry-timeout-planning');
        $planningSeeker = new PlanningSeeker($this->logger, $this->planningInputRepos, $this->planningRepos);

        try {
            if ($this->planningRepos->isProcessing(PlanningBase::STATE_PROCESSING)) {
                $this->logger->info("still processing..");
                return 0;
            }

            $planning = null;
            if (strlen($input->getArgument('planningId')) > 0) {
                $planning = $this->planningRepos->find((int)$input->getArgument('planningId'));
            } else {
                $structureConfig = $this->getStructureConfig($input);
                $maxTimeOutSeconds = PlanningBase::DEFAULT_TIMEOUTSECONDS * pow( PlanningBase::TIMEOUT_MULTIPLIER, 2 );
                $planning = $this->planningRepos->getTimeout($maxTimeOutSeconds, $structureConfig);
            }

            if ($planning === null) {
                $this->logger->info("no timedout-planning found to retry");
                return 0;
            }

            $planning->setState(PlanningBase::STATE_PROCESSING);
            $this->planningRepos->save($planning);
            $this->logger->info('   update state => STATE_PROCESSING');

//            if( array_key_exists(1, $argv) ) {
//                $planning = $this->planningRepos->find( (int) $argv[1] );
//            }
            $planningSeeker->processTimeout($planning);
            if ($planning->getState() !== PlanningBase::STATE_SUCCESS) {
                return 0;
            }
            $this->sendMailWithSuccessPlanning($planning);

            if ($planning->getInput()->selfRefereeEnabled()) {
                $this->updateSelfReferee($planning->getInput());
            }
            $this->removeWorseTimeout($planning);
            $inputService = new PlanningInputService();
            // update planninginputs
            for ($reverseGCD = 2; $reverseGCD <= 8; $reverseGCD++) {
                $reverseGCDInputTmp = $inputService->getReverseGCDInput($planning->getInput(), $reverseGCD);
                $reverseGCDInput = $this->planningInputRepos->getFromInput($reverseGCDInputTmp);
                if ($reverseGCDInput === null) {
                    continue;
                }

                $plannings = $reverseGCDInput->getPlannings();
                while ($plannings->count() > 0) {
                    $removePlanning = $plannings->first();
                    $plannings->removeElement($removePlanning);
                    $this->planningRepos->remove($removePlanning);
                }

                $reverseGCDInput->setState(PlanningInput::STATE_CREATED);
                $this->planningInputRepos->save($reverseGCDInput);
            }
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
        }
        return 0;
    }

    protected function removeWorseTimeout(PlanningBase $planning)
    {
        $worsePlanning = $this->getWorsePlanning($planning);
        if ($worsePlanning === null) {
            return;
        }

        $this->planningRepos->remove($worsePlanning);
        $range = $worsePlanning->getNrOfBatchGames();
        $this->logger->info(
            '   worse timeout removed => batchGames ' . $range->min . '->' . $range->max . ', gamesInARow ' . $worsePlanning->getMaxNrOfGamesInARow(
            )
        );
    }

    protected function getWorsePlanning(PlanningBase $planning): ?PlanningBase
    {
        $range = $planning->getNrOfBatchGames();

        foreach ($planning->getInput()->getPlannings() as $planningIt) {
            if ($planningIt->getState() === PlanningBase::STATE_TIMEOUT
                && $planningIt->getMinNrOfBatchGames() === $range->min
                && $planningIt->getMaxNrOfBatchGames() === $range->max
                && $planningIt->getMaxNrOfGamesInARow() > $planning->getMaxNrOfGamesInARow()) {
                return $planningIt;
            }
        }
        return null;
    }

    protected function sendMailWithSuccessPlanning(PlanningBase $planning)
    {
        $stream = fopen('php://memory', 'r+');
        $loggerOutput = new Logger('succesfull-retry-planning-output-logger');
        $loggerOutput->pushProcessor(new UidProcessor());
        $handler = new StreamHandler($stream, Logger::INFO);
        $loggerOutput->pushHandler($handler);

        $planningOutput = new PlanningOutput($loggerOutput);
        $planningOutput->output($planning, true);

        $batchOutput = new BatchOutput($loggerOutput);
        $batchOutput->output($planning->createFirstBatch(), "succesful retry planning");

        rewind($stream);
        $this->mailer->sendToAdmin(
            'timeout-planning successful',
            stream_get_contents($stream/*$handler->getStream()*/),
            true
        );
    }

    /**
     * @param InputInterface $input
     * @return array|int[]|null
     */
    protected function getStructureConfig(InputInterface $input): ?array
    {
        $structureConfig = null;
        if (strlen($input->getOption('structureConfig')) > 0) {
            $structureConfigParam = explode(",", $input->getOption('structureConfig'));
            if ($structureConfigParam != false) {
                $structureConfig = [];
                foreach ($structureConfigParam as $nrOfPlaces) {
                    $structureConfig[] = (int)$nrOfPlaces;
                }
            }
        }
        return $structureConfig;
    }
}
