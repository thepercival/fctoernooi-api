<?php

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
    /**
     * @var StructureRepository
     */
    protected $structureRepos;
    /**
     * @var RoundNumberRepository
     */
    protected $roundNumberRepos;
    /**
     * @var TournamentRepository
     */
    protected $tournamentRepos;
    /**
     * @var CompetitionRepository
     */
    protected $competitionRepos;
    /**
     * @var EntityManager
     */
    protected $entityManager;
    /**
     * @var Mailer
     */
    protected $mailer;

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

    protected function configure()
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
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->initLogger($input, 'command-planning-create');
        $this->logger->info('starting command app:planning-create');

        try {
            $queueService = new QueueService($this->config->getArray('queue'));
            if (strlen($input->getArgument('inputId')) > 0) {
                $planningInput = $this->planningInputRepos->find((int)$input->getArgument('inputId'));
                if ($planningInput === null) {
                    $this->logger->info('planningInput ' . $input->getArgument('inputId') . ' not found');
                    return 0;
                }
                $this->planningInputRepos->reset($planningInput);
                $this->processPlanning($queueService, $planningInput);
                $this->logger->info('planningInput ' . $input->getArgument('inputId') . ' created');
                return 0;
            }

            $timeoutInSeconds = 295;
            $queueService->receive($this->getReceiver($queueService), $timeoutInSeconds);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
        }
        return 0;
    }

    protected function getReceiver(QueueService $queueService): callable
    {
        return function (Message $message, Consumer $consumer) use ($queueService) : void {
            // process message
            $this->logger->info('------ EXECUTING ------');
            try {
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
                    $this->logger->info('planningInput ' . $content->inputId . ' not found');
                }
                $consumer->acknowledge($message);
            } catch (\Exception $e) {
                $this->logger->error($e->getMessage());
                $consumer->reject($message);
            }
        };
    }

    protected function processPlanning(
        QueueService $queueService,
        PlanningInput $planningInput,
        Competition $competition = null,
        int $roundNumberAsValue = null
    ) {
        $planningOutput = new PlanningOutput($this->logger);

        $planningSeeker = new PlanningSeeker($this->logger, $this->planningInputRepos, $this->planningRepos);
        $planningSeeker->process($planningInput);
        $bestPlanning = $planningInput->getBestPlanning();
        if ($bestPlanning === null) {
            $message = "best planning not found";
            if ($competition !== null && $roundNumberAsValue !== null) {
                $message .= " for roundnumber " . $roundNumberAsValue . " and competitionid " . $competition->getId();
            }
            throw new \Exception($message, E_ERROR);
        }

//        $planningOutput = new PlanningOutput($this->logger);
//        $planningOutput->outputWithGames($bestPlanning, false);
//        $planningOutput->outputWithTotals($bestPlanning, false);

        if ($competition !== null and $roundNumberAsValue !== null) {
            $this->updateRoundNumberWithPlanning($queueService, $competition, $roundNumberAsValue);
        }
    }

    protected function updateRoundNumberWithPlanning(
        QueueService $queueService,
        Competition $competition,
        int $roundNumberAsValue
    ) {
        $this->logger->info('update roundnumber ' . $roundNumberAsValue . " and competitionid " . $competition->getId() . ' with new planning');

        $this->entityManager->refresh($competition);
        $roundNumber = $this->getRoundNumber($competition, $roundNumberAsValue);
        $this->refreshDb($competition, $roundNumber);

        $tournament = $this->tournamentRepos->findOneBy(["competition" => $roundNumber->getCompetition()]);
        $roundNumberPlanningCreator = new RoundNumberPlanningCreator(
            $this->planningInputRepos,
            $this->planningRepos,
            $this->roundNumberRepos,
            $this->logger
        );
        try {
            $this->entityManager->getConnection()->beginTransaction();
            $roundNumberPlanningCreator->addFrom($queueService, $roundNumber, $tournament->getBreak());
            $this->entityManager->getConnection()->commit();
        } catch (Exception $e) {
            $this->entityManager->getConnection()->rollBack();
            throw $e;
        }
    }

    protected function getRoundNumber(Competition $competition, int $roundNumberAsValue): RoundNumber
    {
        $structure = $this->structureRepos->getStructure($competition);
        if ($structure === null) {
            throw new \Exception("structure not found for competitionid " . $competition->getId(), E_ERROR);
        }
        $roundNumber = $structure->getRoundNumber($roundNumberAsValue);
        if ($roundNumber === null) {
            throw new \Exception(
                "roundnumber " . $roundNumberAsValue . " not found for competitionid " . $competition->getId(), E_ERROR
            );
        }
        return $roundNumber;
    }

    protected function refreshDb(Competition $competition, RoundNumber $roundNumber)
    {
        $this->entityManager->refresh($competition);

        $refreshDbRoundNumber = function (RoundNumber $roundNumberParam) use (&$refreshDbRoundNumber): void {
            $this->entityManager->refresh($roundNumberParam);
            if ($roundNumberParam->hasNext()) {
                $refreshDbRoundNumber($roundNumberParam->getNext());
            }
        };
        $refreshDbRoundNumber($roundNumber);
    }
}
