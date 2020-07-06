<?php

namespace App\Commands\Planning;

use App\Mailer;
use App\QueueService;
use Doctrine\ORM\EntityManager;
use Interop\Queue\Consumer;
use Interop\Queue\Message;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Voetbal\Competition;
use Voetbal\Output\Planning as PlanningOutput;
use Voetbal\Output\Planning\Batch as BatchOutput;
use Voetbal\Planning as PlanningBase;
use Voetbal\Round\Number\PlanningCreator as RoundNumberPlanningCreator;
use Voetbal\Structure\Repository as StructureRepository;

use Voetbal\Planning\Input as PlanningInput;
use Voetbal\Planning\Input\Service as PlanningInputService;
use Voetbal\Planning\Seeker as PlanningSeeker;
use Voetbal\Planning\Service as PlanningService;
use FCToernooi\Tournament\Repository as TournamentRepository;
use Voetbal\Round\Number\PlanningCreator;
use Voetbal\Competition\Repository as CompetitionRepository;
use Voetbal\Round\Number as RoundNumber;
use Voetbal\Structure\Validator as StructureValidator;
use App\Commands\Planning as PlanningCommand;

class Create extends PlanningCommand
{
    /**
     * @var StructureRepository
     */
    protected $structureRepos;
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

                $this->processPlanning($queueService, $planningInput, $competition, $roundNumberAsValue);
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
        if ($planningInput->getState() === PlanningInput::STATE_TRYING_PLANNINGS) {
            $planningOutput->outputPlanningInput($planningInput, null, 'still processing ...');
            return;
        }
        if ($planningInput->getState() === PlanningInput::STATE_UPDATING_BESTPLANNING_SELFREFEE) {
            $planningOutput->outputPlanningInput($planningInput, null, 'still processing selfreferee ...');
            return;
        }

        $planningSeeker = new PlanningSeeker($this->logger, $this->planningInputRepos, $this->planningRepos);
        $planningSeeker->process($planningInput);
        if ($planningInput->getSelfReferee()) {
            $this->updateSelfReferee($planningInput);
        }
        // sleep(10);

        $planningService = new PlanningBase\Service();
        $bestPlanning = $planningService->getBestPlanning($planningInput);
        if ($bestPlanning === null) {
            throw new \Exception(
                "best planning not found for roundnumber " . $roundNumberAsValue . " and competitionid " . $competition->getId(
                ), E_ERROR
            );
        }
        $planningOutput = new PlanningOutput($this->logger);
        // $planningOutput->outputWithGames($bestPlanning, false);
        $planningOutput->outputWithTotals($bestPlanning, false);
        if ($competition === null or $roundNumberAsValue === null) {
            return;
        }

        // $this->entityManager->refresh($roundNumber);
        $roundNumber = $this->getRoundNumber($competition, $roundNumberAsValue);
        $tournament = $this->tournamentRepos->findOneBy(["competition" => $roundNumber->getCompetition()]);
        $roundNumberPlanningCreator = new RoundNumberPlanningCreator($this->planningInputRepos, $this->planningRepos);
        $roundNumberPlanningCreator->addFrom($queueService, $roundNumber, $tournament->getBreak());
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
}
