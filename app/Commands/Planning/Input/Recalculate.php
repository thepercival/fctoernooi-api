<?php

declare(strict_types=1);

namespace App\Commands\Planning\Input;

use App\Commands\Planning as PlanningCommand;
use App\QueueService\Planning as PlanningQueueService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Container\ContainerInterface;
use SportsPlanning\Input\Service as PlanningInputService;
use SportsPlanning\Planning\Output as PlanningOutput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Recalculate extends PlanningCommand
{
    private string $customName = 'recalculate-planning-inputs';
    protected PlanningInputService $planningInputSerivce;
    protected EntityManagerInterface $entityManager;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);

        $this->planningInputSerivce = new PlanningInputService();

        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(EntityManagerInterface::class);
        $this->entityManager = $entityManager;
    }

    protected function configure(): void
    {
        $this
            // the name of the command (the part after "bin/console")
            ->setName('app:' . $this->customName)
            // the short description shown while running "php bin/console list"
            ->setDescription('Recalculates the planning-inputs')
            // the full command description shown when running the command with
            // the "--help" option
            ->setHelp('Recalculates the planning-inputs');
        parent::configure();

        $this->addOption('id-range', null, InputOption::VALUE_REQUIRED, '1-1000');
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
            $this->recalculatePlanningInputs($input);
        } catch (\Exception $exception) {
            if ($this->logger !== null) {
                $this->logger->error($exception->getMessage());
            }
        }
        return 0;
    }

    protected function recalculatePlanningInputs(InputInterface $input): void
    {
        $idRange = $this->getInputRange($input, 'id-range');
        if ($idRange === null) {
            throw new \Exception('no id-range found', E_ERROR);
        }
        $queueService = new PlanningQueueService($this->config->getArray('queue'));
        $planningOutput = new PlanningOutput($this->getLogger());

        foreach ($idRange->toArray() as $id) {
            $planningInput = $this->planningInputRepos->find($id);
            if ($planningInput === null) {
                $this->getLogger()->info('no planningInput found for id  "' . $id . '" ');
                continue;
            }

            $queueService->sendCreatePlannings($planningInput);
            $planningOutput->outputInput($planningInput, 'send recalculate-message to queue');
            $this->entityManager->clear();
        }
    }
}
