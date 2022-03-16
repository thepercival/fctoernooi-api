<?php

declare(strict_types=1);

namespace App\Commands\Planning;

use App\Commands\Planning as PlanningCommand;
use App\Mailer;
use Doctrine\ORM\EntityManagerInterface;
use FCToernooi\Tournament\Repository as TournamentRepository;
use Monolog\Logger;
use Psr\Container\ContainerInterface;
use Sports\Competition\Repository as CompetitionRepository;
use Sports\Round\Number\Repository as RoundNumberRepository;
use Sports\Structure\Repository as StructureRepository;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Report extends PlanningCommand
{
    protected StructureRepository $structureRepos;
    protected RoundNumberRepository $roundNumberRepos;
    protected TournamentRepository $tournamentRepos;
    protected CompetitionRepository $competitionRepos;
    protected EntityManagerInterface $entityManager;

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
            ->setName('app:report-planning')
            // the short description shown while running "php bin/console list"
            ->setDescription('Sends a mail with planningInputs info')
            // the full command description shown when running the command with
            // the "--help" option
            ->setHelp('Reporting the planningInputs');
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
//            $mailHandler = $this->getMailHandler('planningInputs report', Logger::INFO);
            $logLevel = $this->getLogLevel($input, Logger::INFO);
//            $logger = $this->initLogger($logLevel, 'command-planning-report', $mailHandler);
//            $logger->info('starting 1');
//            $logger->info('starting 2');

//            $hour = (int)(new \DateTimeImmutable())->format('H');
//            $sortAsc = ($hour % 2) === 1;
//            $planningInputs = $this->planningInputRepos->findToRecreate((int)$amount, $sortAsc);
//            $counter = 0;
//            foreach ($planningInputs as $planningInput) {
//                $msg = '################ ';
//                $msg .= 'recreating planninginput ' . ++$counter . '/' . $amount;
//                $msg .= ' ################';
//                $this->getLogger()->info($msg);
//                $this->processPlanningInput($planningInput);
//            }
        } catch (\Exception $exception) {
            if ($this->logger !== null) {
                $this->logger->error($exception->getMessage());
            }
        }
        return 0;
    }

//    protected function processPlanningInput(PlanningInput $planningInput): void
//    {
//        // new PlanningOutput($this->getLogger());
////        $this->planningInputRepos->reset($planningInput);
//
//        $this->createSchedules($planningInput);
//        $schedules = $this->scheduleRepos->findByInput($planningInput);
//
//        $planningSeeker = new PlanningSeeker(
//            $this->getLogger(),
//            $this->planningInputRepos,
//            $this->planningRepos,
//            $this->scheduleRepos
//        );
//        if ($this->disableThrowOnTimeout === true) {
//            $planningSeeker->disableThrowOnTimeout();
//        }
//        $planningSeeker->processInput($planningInput, $schedules);
//
//        $planningInput->setRecreatedAt(new \DateTimeImmutable());
//        $this->planningInputRepos->save($planningInput, true);
//
//        $bestPlanning = $planningInput->getBestPlanning(PlanningType::BatchGames);
//        if ($this->showSuccessful === true) {
//            $planningOutput = new PlanningOutput($this->getLogger());
//            $planningOutput->outputWithGames($bestPlanning, true);
//            $planningOutput->outputWithTotals($bestPlanning, false);
//        }
//    }
}
