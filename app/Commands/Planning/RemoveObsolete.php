<?php

namespace App\Commands\Planning;

use App\Mailer;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Voetbal\Planning as PlanningBase;
use Voetbal\Planning\Input\Repository as PlanningInputRepository;
use Voetbal\Planning\Repository as PlanningRepository;
use Voetbal\Planning\Input as PlanningInput;
use App\Commands\Planning as PlanningCommand;

class RemoveObsolete extends PlanningCommand
{
    /**
     * @var PlanningInputRepository
     */
    protected $planningInputRepos;
    /**
     * @var PlanningRepository
     */
    protected $planningRepos;
    /**
     * @var Mailer
     */
    protected $mailer;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);
        $this->mailer = $container->get(Mailer::class);
        $this->planningInputRepos = $container->get(PlanningInputRepository::class);
        $this->planningRepos = $container->get(PlanningRepository::class);
    }

    protected function configure()
    {
        $this
            // the name of the command (the part after "bin/console")
            ->setName('app:remove-obsolete-plannings')
            // the short description shown while running "php bin/console list"
            ->setDescription(
                'Removes the plannings which is not the best planning and has a finished input with all plannings without state timeout'
            )
            // the full command description shown when running the command with
            // the "--help" option
            ->setHelp('Removes the obsolete plannings');
        parent::configure();

        $this->addArgument('inputId', InputArgument::OPTIONAL, 'input-id');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->initLogger($input, 'cron-planning-remove-obsolete');
        $planningService = new PlanningBase\Service();
        try {
            // maak een functie die alleen inputs ophaalt met:
            // 1 state = PlanningInput::STATE_ALL_PLANNINGS_TRIED
            // 2 meer dan 1 planning
            // 3 alleen planningen met state failed or success
            $planningInputs = $this->planningInputRepos->findBy(["state" => PlanningInput::STATE_ALL_PLANNINGS_TRIED]);
            foreach ($planningInputs as $planningInput) {
                if (!$this->allPlanningsFinished($planningInput)) {
                    continue;
                }
                $bestPlanning = $planningService->getBestPlanning($planningInput);
                foreach ($planningInput->getPlannings() as $planning) {
                    if ($bestPlanning === $planning) {
                        continue;
                    }
                    $this->planningRepos->remove($planning);
                }
            }
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
        }
        return 0;
    }

    protected function allPlanningsFinished(PlanningInput $planningInput): bool
    {
        foreach ($planningInput->getPlannings() as $planning) {
            if ($planning->getState() !== PlanningBase::STATE_FAILED
                && $planning->getState() !== PlanningBase::STATE_SUCCESS) {
                return false;
            }
        }
        return true;
    }
}
