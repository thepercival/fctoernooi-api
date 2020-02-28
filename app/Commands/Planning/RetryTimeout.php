<?php

namespace App\Commands\Planning;

use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use App\Command;
use Selective\Config\Configuration;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Voetbal\Planning\Input;
use Voetbal\Planning\Input as PlanningInput;
use Voetbal\Planning\Repository as PlanningRepository;
use Voetbal\Planning\Input\Repository as PlanningInputRepository;
use Voetbal\Planning;
use Voetbal\Planning\Input\Service as PlanningInputService;
use Voetbal\Planning\Resource\RefereePlaceService;
use Voetbal\Planning\Seeker as PlanningSeeker;
use Voetbal\Planning\Service as PlanningService;
use App\Commands\Planning as PlanningCommand;
use Voetbal\Range as VoetbalRange;

class RetryTimeout extends PlanningCommand
{
    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);
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
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->initLogger($input, 'cron-retry-timeout-planning');
        $this->initMailer($this->logger);
        $planningSeeker = new PlanningSeeker($this->logger, $this->planningInputRepos, $this->planningRepos);

        try {
            if ($this->planningRepos->isProcessing(Planning::STATE_PROCESSING)) {
                $this->logger->info("still processing..");
                return 0;
            }

            $planning = $this->planningRepos->getTimeout();
            // $planning = $this->planningRepos->find( 61241 );
            if ($planning === null) {
                $this->logger->info("   all plannings(also timeout) are tried");
                return 0;
            }

            $planning->setState(Planning::STATE_PROCESSING);
            $this->planningRepos->save($planning);
            $this->logger->info('   update state => STATE_PROCESSING');

//            if( array_key_exists(1, $argv) ) {
//                $planning = $this->planningRepos->find( (int) $argv[1] );
//            }
            $planningSeeker->processTimeout($planning);
            if ($planning->getState() !== Planning::STATE_SUCCESS) {
                return 0;
            }
            if ($planning->getInput()->getSelfReferee()) {
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
            if ($this->config->getString('environment') === 'production') {
                $this->mailer->sendToAdmin("error creating timeout planning", $e->getMessage());
            }
        }
        return 0;
    }

    protected function removeWorseTimeout(Planning $planning)
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

    protected function getWorsePlanning(Planning $planning): ?Planning
    {
        $range = $planning->getNrOfBatchGames();

        foreach ($planning->getInput()->getPlannings() as $planningIt) {
            if ($planningIt->getState() === Planning::STATE_TIMEOUT
                && $planningIt->getMinNrOfBatchGames() === $range->min
                && $planningIt->getMaxNrOfBatchGames() === $range->max
                && $planningIt->getMaxNrOfGamesInARow() > $planning->getMaxNrOfGamesInARow()) {
                return $planningIt;
            }
        }
        return null;
    }

}