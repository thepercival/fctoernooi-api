<?php

declare(strict_types=1);

namespace App\Commands\Planning;

use App\Mailer;
use App\QueueService;
use Doctrine\ORM\EntityManager;
use Exception;
use Interop\Queue\Consumer;
use Interop\Queue\Message;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Sports\Competition;
use SportsPlanning\Planning\Output as PlanningOutput;
use SportsPlanning\Batch\Output as BatchOutput;
use Sports\Planning as PlanningBase;
use Sports\Round\Number\PlanningCreator as RoundNumberPlanningCreator;
use Sports\Structure\Repository as StructureRepository;

use SportsPlanning\Input as PlanningInput;
use SportsPlanning\Input\Service as PlanningInputService;
use SportsPlanning\Planning\Seeker as PlanningSeeker;
use SportsPlanning\Planning\Service as PlanningService;
use FCToernooi\Tournament\Repository as TournamentRepository;
use Sports\Round\Number\Repository as RoundNumberRepository;
use Sports\Competition\Repository as CompetitionRepository;
use Sports\Round\Number as RoundNumber;
use Sports\Structure\Validator as StructureValidator;
use App\Commands\Planning as PlanningCommand;

class Create extends PlanningCommand
{
    protected StructureRepository $structureRepos;
    protected RoundNumberRepository $roundNumberRepos;
    protected TournamentRepository $tournamentRepos;
    protected CompetitionRepository $competitionRepos;
    protected EntityManager $entityManager;

    protected bool $showSuccessful = false;

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
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $this->initLogger($input, 'command-planning-create');
            $this->getLogger()->info('starting command app:planning-create');
            $showSuccessful = $input->getOption('showSuccessful');
            $this->showSuccessful = is_bool($showSuccessful) ? $showSuccessful : false;
            $queueService = new QueueService($this->config->getArray('queue'));
            $inputId = $input->getArgument('inputId');
            if (is_string($inputId) && strlen($inputId) > 0) {
                $planningInput = $this->planningInputRepos->find((int)$inputId);
                if ($planningInput === null) {
                    $this->getLogger()->info('planningInput ' . $inputId . ' not found');
                    return 0;
                }
                $this->planningInputRepos->reset($planningInput);
                $disableThrowOnTimeout = $input->getOption('disableThrowOnTimeout');
                $disableThrowOnTimeout = is_bool($disableThrowOnTimeout) ? $disableThrowOnTimeout : false;
                $this->processPlanning($queueService, $planningInput, null, null, $disableThrowOnTimeout);
                $this->getLogger()->info('planningInput ' . $inputId . ' created');
                return 0;
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
        return function (Message $message, Consumer $consumer) use ($queueService) : void {
            // process message
            try {
                $this->getLogger()->info('------ EXECUTING ------');
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
                    $this->processPlanning($queueService, $planningInput, $competition, $roundNumberAsValue);
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

    protected function processPlanning(
        QueueService $queueService,
        PlanningInput $planningInput,
        Competition|null $competition,
        int|null $roundNumberAsValue,
        bool|null $disableThrowOnTimeout = null
    ): void {
        $planningOutput = new PlanningOutput($this->getLogger());

        $planningSeeker = new PlanningSeeker($this->getLogger(), $this->planningInputRepos, $this->planningRepos);
        if ($disableThrowOnTimeout === true) {
            $planningSeeker->disableThrowOnTimeout();
        }
        $planningSeeker->process($planningInput);
        $bestPlanning = $planningInput->getBestPlanning();
        if ($this->showSuccessful === true) {
            $planningOutput = new PlanningOutput($this->getLogger());
            $planningOutput->outputWithGames($bestPlanning, true);
            $planningOutput->outputWithTotals($bestPlanning, false);
        }
        if ($competition !== null and $roundNumberAsValue !== null) {
            $this->updateRoundNumberWithPlanning($queueService, $competition, $roundNumberAsValue);
        }
    }

    protected function updateRoundNumberWithPlanning(
        QueueService $queueService,
        Competition $competition,
        int $roundNumberAsValue
    ): void {
        $this->getLogger()->info('update roundnumber ' . $roundNumberAsValue . " and competitionid " . ((string)$competition->getId()) . ' with new planning');

        $this->entityManager->refresh($competition);
        // all roundnumbers should be refreshed, because planningConfig can be gotten from roundnumber above
        for ($roundNumberValueIt = 1 ;$roundNumberValueIt <= $roundNumberAsValue ; $roundNumberValueIt++) {
            $roundNumberIt = $this->getRoundNumber($competition, $roundNumberValueIt);
            $this->refreshDb($competition, $roundNumberIt);
        }
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
            $roundNumberPlanningCreator->addFrom($queueService, $roundNumber, $tournament->getBreak());
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

    protected function refreshDb(Competition $competition, RoundNumber $roundNumber): void
    {
        // $this->entityManager->refresh($competition);

//        $refreshDbRoundNumber = function (RoundNumber $roundNumberParam) use (&$refreshDbRoundNumber): void {
        $this->entityManager->refresh($roundNumber/*$roundNumberParam*/);
        $planningConfig =$roundNumber->getPlanningConfig();
        if ($planningConfig !== null) {
            $this->entityManager->refresh($planningConfig);
        }
        foreach ($roundNumber->getValidGameAmountConfigs() as $gameAmountConfig) {
            $this->entityManager->refresh($gameAmountConfig);
        }

//            if ($roundNumberParam->hasNext()) {
//                $refreshDbRoundNumber($roundNumberParam->getNext());
//            }
//        };
//        $refreshDbRoundNumber($roundNumber);
    }
}
