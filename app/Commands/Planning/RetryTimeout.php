<?php

namespace App\Commands\Planning;

use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use App\Command;
use Selective\Config\Configuration;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Voetbal\Planning\Repository as PlanningRepository;
use Voetbal\Planning\Input\Repository as PlanningInputRepository;
use Voetbal\Planning;
use Voetbal\Planning\Input as PlanningInput;
use Voetbal\Planning\Input\Service as PlanningInputService;
use Voetbal\Planning\Seeker as PlanningSeeker;

class RetryTimeout extends Command
{
    /**
     * @var PlanningInputRepository
     */
    protected $planningInputRepos;
    /**
     * @var PlanningRepository
     */
    protected $planningRepos;

    public function __construct(ContainerInterface $container)
    {
        // $settings = $container->get('settings');
        $this->planningInputRepos = $container->get(PlanningInputRepository::class);
        $this->planningRepos = $container->get(PlanningRepository::class);
        parent::__construct($container->get(Configuration::class), 'cron-retry-timeout-planning');
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
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $planningSeeker = new PlanningSeeker($this->logger, $this->planningInputRepos, $this->planningRepos);

        try {
            $planning = $this->planningRepos->getTimeout();
            if ($planning === null) {
                $this->logger->info("   all plannings(also timeout) are tried");
                return 0;
            }
//            if( array_key_exists(1, $argv) ) {
//                $planning = $this->planningRepos->find( (int) $argv[1] );
//            }
            $planningSeeker->processTimeout($planning);
            if ($planning->getState() !== Planning::STATE_SUCCESS) {
                return 0;
            }
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
            if ($this->config->getString('environment') === 'production') {
                $this->mailer->sendToAdmin("error creating planning", $e->getMessage());
                $this->logger->error($e->getMessage());
            } else {
                echo $e->getMessage() . PHP_EOL;
            }
        }
        return 0;
    }
}