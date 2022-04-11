<?php

declare(strict_types=1);

namespace App\Commands\Planning;

use App\Commands\Planning as PlanningCommand;
use App\Mailer;
use Doctrine\ORM\EntityManagerInterface;
use FCToernooi\Tournament\Repository as TournamentRepository;
use Psr\Container\ContainerInterface;
use Sports\Competition\Repository as CompetitionRepository;
use Sports\Round\Number\Repository as RoundNumberRepository;
use Sports\Structure\Repository as StructureRepository;
use SportsPlanning\Input as PlanningInput;
use SportsPlanning\Planning\Output as PlanningOutput;
use SportsPlanning\Planning\Type as PlanningType;
use SportsPlanning\Seeker as PlanningSeeker;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Recreate extends PlanningCommand
{
    private string $customName = 'recreate-planning';
    protected StructureRepository $structureRepos;
    protected RoundNumberRepository $roundNumberRepos;
    protected TournamentRepository $tournamentRepos;
    protected CompetitionRepository $competitionRepos;
    protected EntityManagerInterface $entityManager;

    protected bool $showSuccessful = false;
    protected bool $disableThrowOnTimeout = false;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);

        /** @var Mailer|null $mailer */
        $mailer = $container->get(Mailer::class);
        $this->mailer = $mailer;

        /** @var StructureRepository $structureRepos */
        $structureRepos = $container->get(StructureRepository::class);
        $this->structureRepos = $structureRepos;

        /** @var RoundNumberRepository $roundNumberRepos */
        $roundNumberRepos = $container->get(RoundNumberRepository::class);
        $this->roundNumberRepos = $roundNumberRepos;

        /** @var TournamentRepository $tournamentRepos */
        $tournamentRepos = $container->get(TournamentRepository::class);
        $this->tournamentRepos = $tournamentRepos;

        /** @var CompetitionRepository $competitionRepos */
        $competitionRepos = $container->get(CompetitionRepository::class);
        $this->competitionRepos = $competitionRepos;

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
            ->setDescription('Recreates the plannings from the inputs')
            // the full command description shown when running the command with
            // the "--help" option
            ->setHelp('Recreates the plannings from the inputs');
        parent::configure();

        $this->addArgument('inputId', InputArgument::OPTIONAL, 'input-id');
        $this->addOption('showSuccessful', null, InputOption::VALUE_NONE, 'show successful planning');
        $this->addOption('disableThrowOnTimeout', null, InputOption::VALUE_NONE, 'show successful planning');
        $this->addOption('batchGamesRange', null, InputOption::VALUE_OPTIONAL, '1-2');
        $this->addOption('maxNrOfGamesInARow', null, InputOption::VALUE_OPTIONAL, '0');
        $this->addOption('amount', null, InputOption::VALUE_OPTIONAL, '10');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $loggerName = 'command-' . $this->customName;
            $this->initLogger(
                $this->getLogLevel($input),
                $this->getStreamDef($input, $loggerName),
                $loggerName,
            );

            $this->getLogger()->info('starting command app:planning-recreate');
            $showSuccessful = $input->getOption('showSuccessful');
            $this->showSuccessful = is_bool($showSuccessful) ? $showSuccessful : false;
            $disableThrowOnTimeout = $input->getOption('disableThrowOnTimeout');
            $this->disableThrowOnTimeout = is_bool($disableThrowOnTimeout) ? $disableThrowOnTimeout : false;

            $inputId = $input->getArgument('inputId');
            if (is_string($inputId) && strlen($inputId) > 0) {
                $planningInput = $this->planningInputRepos->find($inputId);
                if ($planningInput !== null && $planningInput->getRecreatedAt() === null) {
                    $this->processPlanningInput($planningInput);
                } else {
                    $this->getLogger()->error('planningInput ' . $inputId . ' not found');
                }
                return 0;
            }
            /** @var string|null $amount */
            $amount = $input->getOption('amount');
            if ($amount === null) {
                $amount = '10';
            }
            $hour = (int)(new \DateTimeImmutable())->format('H');
            $sortAsc = ($hour % 2) === 1;
            $planningInputs = $this->planningInputRepos->findToRecreate((int)$amount, $sortAsc);
            $counter = 0;
            foreach ($planningInputs as $planningInput) {
                $msg = '################ ';
                $msg .= 'recreating planninginput ' . ++$counter . '/' . $amount;
                $msg .= ' ################';
                $this->getLogger()->info($msg);
                $this->processPlanningInput($planningInput);
            }
        } catch (\Exception $exception) {
            if ($this->logger !== null) {
                $this->logger->error($exception->getMessage());
            }
        }
        return 0;
    }

    protected function processPlanningInput(PlanningInput $planningInput): void
    {
        // new PlanningOutput($this->getLogger());
//        $this->planningInputRepos->reset($planningInput);

        $this->createSchedules($planningInput);
        $schedules = $this->scheduleRepos->findByInput($planningInput);

        $planningSeeker = new PlanningSeeker(
            $this->getLogger(),
            $this->planningInputRepos,
            $this->planningRepos,
            $this->scheduleRepos
        );
        if ($this->disableThrowOnTimeout === true) {
            $planningSeeker->disableThrowOnTimeout();
        }
        $planningSeeker->processInput($planningInput, $schedules);

        $planningInput->setRecreatedAt(new \DateTimeImmutable());
        $this->planningInputRepos->save($planningInput, true);

        $bestPlanning = $planningInput->getBestPlanning(PlanningType::BatchGames);
        if ($this->showSuccessful === true) {
            $planningOutput = new PlanningOutput($this->getLogger());
            $planningOutput->outputWithGames($bestPlanning, true);
            $planningOutput->outputWithTotals($bestPlanning, false);
        }
    }
}
