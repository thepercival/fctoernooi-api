<?php

declare(strict_types=1);

namespace App\Commands\Planning;

use App\Commands\Planning as PlanningCommand;
use App\Mailer;
use App\QueueService;
use Doctrine\ORM\EntityManager;
use Exception;
use FCToernooi\Tournament\Repository as TournamentRepository;
use Interop\Queue\Consumer;
use Interop\Queue\Message;
use Psr\Container\ContainerInterface;
use Sports\Competition;
use Sports\Competition\Repository as CompetitionRepository;
use Sports\Round\Number as RoundNumber;
use Sports\Round\Number\PlanningCreator as RoundNumberPlanningCreator;
use Sports\Round\Number\Repository as RoundNumberRepository;
use Sports\Structure\Repository as StructureRepository;
use SportsPlanning\Input as PlanningInput;
use SportsPlanning\Planning;
use SportsPlanning\Planning\Filter as PlanningFilter;
use SportsPlanning\Planning\Output as PlanningOutput;
use SportsPlanning\Seeker as PlanningSeeker;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Create extends PlanningCommand
{
    protected StructureRepository $structureRepos;
    protected RoundNumberRepository $roundNumberRepos;
    protected TournamentRepository $tournamentRepos;
    protected CompetitionRepository $competitionRepos;
    protected EntityManager $entityManager;

    protected bool $showSuccessful = false;
    protected bool $disableThrowOnTimeout = false;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);
        $this->mailer = $container->get(Mailer::class);
        $this->structureRepos = $container->get(StructureRepository::class);
        $this->roundNumberRepos = $container->get(RoundNumberRepository::class);
        $this->tournamentRepos = $container->get(TournamentRepository::class);
        $this->competitionRepos = $container->get(CompetitionRepository::class);
        $this->entityManager = $container->get(EntityManager::class);
    }

    protected function configure(): void
    {
        $this
            // the name of the command (the part after "bin/console")
            ->setName('app:create-planning')
            // the short description shown while running "php bin/console list"
            ->setDescription('Creates the plannings from the inputs')
            // the full command description shown when running the command with
            // the "--help" option
            ->setHelp('Creates the plannings from the inputs');
        parent::configure();

        $this->addArgument('inputId', InputArgument::OPTIONAL, 'input-id');
        $this->addOption('showSuccessful', null, InputOption::VALUE_NONE, 'show successful planning');
        $this->addOption('disableThrowOnTimeout', null, InputOption::VALUE_NONE, 'show successful planning');
        $this->addOption('batchGamesRange', null, InputOption::VALUE_OPTIONAL, '1-2');
        $this->addOption('maxNrOfGamesInARow', null, InputOption::VALUE_OPTIONAL, '0');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $this->initLogger($input, 'command-planning-create');
            $this->getLogger()->info('starting command app:planning-create');
            $showSuccessful = $input->getOption('showSuccessful');
            $this->showSuccessful = is_bool($showSuccessful) ? $showSuccessful : false;
            $disableThrowOnTimeout = $input->getOption('disableThrowOnTimeout');
            $this->disableThrowOnTimeout = is_bool($disableThrowOnTimeout) ? $disableThrowOnTimeout : false;

            $queueService = new QueueService($this->config->getArray('queue'));
            $inputId = $input->getArgument('inputId');
            if (is_string($inputId) && strlen($inputId) > 0) {
                $planningFilter = $this->getPlanningFilter($input);
                return $this->processSinglePlanningInput((int)$inputId, $queueService, $planningFilter);
            }
            $timeoutInSeconds = 295;
            $queueService->receive($this->getReceiver($queueService), $timeoutInSeconds);
        } catch (\Exception $exception) {
            if ($this->logger !== null) {
                $this->logger->error($exception->getMessage());
            }
        }
        return 0;
    }

    protected function getReceiver(QueueService $queueService): callable
    {
        return function (Message $message, Consumer $consumer) use ($queueService): void {
            // process message
            try {
                $eventPriority = $message->getHeader('priority');
                $this->getLogger()->info('------ EXECUTING WITH PRIORITY ' . $eventPriority . ' ------');

                $content = json_decode($message->getBody());
                $competition = null;
                if (property_exists($content, "competitionId")) {
                    $competition = $this->competitionRepos->find((int)$content->competitionId);
                }
                $roundNumberAsValue = null;
                if (property_exists($content, "roundNumber")) {
                    $roundNumberAsValue = (int)$content->roundNumber;
                }
                $planningInput = $this->planningInputRepos->find((int)$content->inputId);
                if ($planningInput !== null) {
                    $this->planningInputRepos->reset($planningInput);
                    $this->processInput(
                        $queueService,
                        $planningInput,
                        $competition,
                        $roundNumberAsValue,
                        $eventPriority
                    );
                } else {
                    $this->getLogger()->info('planningInput ' . $content->inputId . ' not found');
                }
                $consumer->acknowledge($message);
            } catch (\Exception $exception) {
                if ($this->logger !== null) {
                    $this->logger->error($exception->getMessage());
                }
                $consumer->reject($message);
            }
        };
    }

    protected function processInput(
        QueueService $queueService,
        PlanningInput $planningInput,
        Competition|null $competition,
        int|null $roundNumberAsValue,
        int $eventPriority
    ): void {
        // new PlanningOutput($this->getLogger());

        $this->createSchedules($planningInput);

        $planningSeeker = new PlanningSeeker($this->getLogger(), $this->planningInputRepos, $this->planningRepos, $this->scheduleRepos);
        if ($this->disableThrowOnTimeout === true) {
            $planningSeeker->disableThrowOnTimeout();
        }
        $planningSeeker->processInput($planningInput);
        $bestPlanning = $planningInput->getBestPlanning();
        if ($this->showSuccessful === true) {
            $planningOutput = new PlanningOutput($this->getLogger());
            $planningOutput->outputWithGames($bestPlanning, true);
            $planningOutput->outputWithTotals($bestPlanning, false);
        }
        if ($competition !== null and $roundNumberAsValue !== null) {
            $this->updateRoundNumberWithPlanning($queueService, $competition, $roundNumberAsValue, $eventPriority);
        }
    }

    protected function updateRoundNumberWithPlanning(
        QueueService $queueService,
        Competition $competition,
        int $roundNumberAsValue,
        int $eventPriority,
    ): void {
        $this->getLogger()->info('update roundnumber ' . $roundNumberAsValue . " and competitionid " . ((string)$competition->getId()) . ' with new planning');

        $this->refreshCompetition($competition);
        $roundNumber = $this->getRoundNumber($competition, $roundNumberAsValue);

        $tournament = $this->tournamentRepos->findOneBy(["competition" => $roundNumber->getCompetition()]);
        $roundNumberPlanningCreator = new RoundNumberPlanningCreator(
            $this->planningInputRepos,
            $this->planningRepos,
            $this->roundNumberRepos,
            $this->getLogger()
        );
        try {
            if ($tournament === null) {
                throw new \Exception('no tournament found for competitionid ' . ((string)$roundNumber->getCompetition()->getId()), E_ERROR);
            }
            $this->entityManager->getConnection()->beginTransaction();
            $roundNumberPlanningCreator->addFrom($queueService, $roundNumber, $tournament->getBreak(), $eventPriority - 1);
            $this->entityManager->getConnection()->commit();
        } catch (Exception $exception) {
            $this->entityManager->getConnection()->rollBack();
            throw $exception;
        }
    }

    protected function getRoundNumber(Competition $competition, int $roundNumberAsValue): RoundNumber
    {
        $structure = $this->structureRepos->getStructure($competition);
        $roundNumber = $structure->getRoundNumber($roundNumberAsValue);
        if ($roundNumber === null) {
            throw new \Exception(
                "roundnumber " . $roundNumberAsValue . " not found for competitionid " . ((string)$competition->getId()),
                E_ERROR
            );
        }
        return $roundNumber;
    }

    protected function refreshCompetition(Competition $competition): void
    {
        $this->entityManager->refresh($competition);
        foreach ($competition->getSports() as $sport) {
            $this->entityManager->refresh($sport);
        }
        $roundNumber = $this->getRoundNumber($competition, 1);
        $this->refreshRoundNumber($roundNumber);
    }

    protected function refreshRoundNumber(RoundNumber $roundNumber): void
    {
        $this->entityManager->refresh($roundNumber);

        $this->entityManager->refresh($roundNumber);
        foreach ($roundNumber->getRounds() as $round) {
            $this->entityManager->refresh($round);
            foreach ($round->getPoules() as $poule) {
                $this->entityManager->refresh($poule);
//                foreach ($poule->getAgainstGames() as $game) {
//                    $this->entityManager->refresh($game);
//                }
            }
        }
        $planningConfig =$roundNumber->getPlanningConfig();
        if ($planningConfig !== null) {
            $this->entityManager->refresh($planningConfig);
        }
        foreach ($roundNumber->getValidGameAmountConfigs() as $gameAmountConfig) {
            $this->entityManager->refresh($gameAmountConfig);
        }

        $nextRoundNumber = $roundNumber->getNext();
        if ($nextRoundNumber !== null) {
            $this->refreshRoundNumber($nextRoundNumber);
        }
    }

    protected function processSinglePlanningInput(
        int $inputId,
        QueueService $queueService,
        PlanningFilter|null $filter
    ): int {
        $planningInput = $this->planningInputRepos->find($inputId);
        if ($planningInput === null) {
            $this->getLogger()->info('planningInput ' . $inputId . ' not found');
            return 0;
        }
        if ($filter !== null) {
            $this->processPlanning($planningInput, $filter);
            return 0;
        }
        $this->planningInputRepos->reset($planningInput);

        $this->processInput($queueService, $planningInput, null, null, QueueService::MAX_PRIORITY);
        $this->getLogger()->info('planningInput ' . $inputId . ' created');
        $this->getLogger()->info('memory usage: ' . memory_get_usage() . '(' . memory_get_usage(true) . ')');
        return 0;
    }

    protected function getPlanningFilter(InputInterface $input): PlanningFilter|null
    {
        $batchGamesRange = $this->getInputRange($input, 'batchGamesRange');
        if ($batchGamesRange === null) {
            return null;
        }
        $maxNrOfGamesInARow = 0;
        $maxNrOfGamesInARowOption = $input->getOption('maxNrOfGamesInARow');
        if (is_string($maxNrOfGamesInARowOption) && strlen($maxNrOfGamesInARowOption) > 0) {
            $maxNrOfGamesInARow = (int)$maxNrOfGamesInARowOption;
        }
        return new PlanningFilter($batchGamesRange, $maxNrOfGamesInARow);
    }


    protected function processPlanning(PlanningInput $input, PlanningFilter $filter): int
    {
        $planning = $this->planningRepos->findOneByExt(
            $input,
            $filter->getBatchGamesRange(),
            $filter->getMaxNrOfGamesInARow()
        );
        if ($planning === null) {
            $this->getLogger()->info('planning not found for inputid "' . (string)$input->getId() . '" and ' . $filter);
            return 0;
        }

        // new PlanningOutput($this->getLogger());

        $this->createSchedules($planning->getInput());
        $planningSeeker = new PlanningSeeker(
            $this->getLogger(),
            $this->planningInputRepos,
            $this->planningRepos,
            $this->scheduleRepos
        );
        if ($this->disableThrowOnTimeout === true) {
            $planningSeeker->disableThrowOnTimeout();
        }
        $schedules = $this->scheduleRepos->findByInput($planning->getInput());
        $planningSeeker->processPlanning($planning, $schedules);

        $this->getLogger()->info('planningstate: ' . $planning->getState());
        if ($planning->getState() === Planning::STATE_SUCCEEDED && $this->showSuccessful === true) {
            $planningOutput = new PlanningOutput($this->getLogger());
            $planningOutput->outputWithGames($planning, true);
            $planningOutput->outputWithTotals($planning, false);
        }
        return 0;
    }
}
