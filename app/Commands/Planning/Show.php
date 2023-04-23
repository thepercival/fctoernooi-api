<?php

declare(strict_types=1);

namespace App\Commands\Planning;

use App\Commands\Planning as PlanningCommand;
use App\Mailer;
use App\QueueService;
use App\QueueService\Planning as PlanningQueueService;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use FCToernooi\CacheService;
use FCToernooi\Tournament\Repository as TournamentRepository;
use Interop\Queue\Consumer;
use Interop\Queue\Message;
use Memcached;
use Psr\Container\ContainerInterface;
use Sports\Competition;
use Sports\Competition\Repository as CompetitionRepository;
use Sports\Round\Number as RoundNumber;
use Sports\Round\Number\PlanningCreator as RoundNumberPlanningCreator;
use Sports\Round\Number\Repository as RoundNumberRepository;
use Sports\Structure\Repository as StructureRepository;
use SportsHelpers\Dev\ByteFormatter;
use SportsPlanning\Input as PlanningInput;
use SportsPlanning\Planning\Filter as PlanningFilter;
use SportsPlanning\Planning\Output as PlanningOutput;
use SportsPlanning\Planning\State as PlanningState;
use SportsPlanning\Planning\Type;
use SportsPlanning\Planning\Type as PlanningType;
use SportsPlanning\Seeker as PlanningSeeker;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Show extends PlanningCommand
{
    private string $customName = 'show-planning';
    protected StructureRepository $structureRepos;
    protected RoundNumberRepository $roundNumberRepos;
    protected TournamentRepository $tournamentRepos;
    protected CompetitionRepository $competitionRepos;
    protected EntityManagerInterface $entityManager;
    private CacheService $cacheService;

    protected bool $showSuccessful = false;
    protected bool $disableThrowOnTimeout = false;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);

        /** @var Mailer|null $mailer */
        $mailer = $container->get(Mailer::class);
        $this->mailer = $mailer;

        /** @var Memcached $memcached */
        $memcached = $container->get(Memcached::class);
        $this->cacheService = new CacheService($memcached, $this->config->getString('namespace'));

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
            ->setDescription('Creates the plannings from the inputs')
            // the full command description shown when running the command with
            // the "--help" option
            ->setHelp('Creates the plannings from the inputs');
        parent::configure();

        $this->addArgument('inputId', InputArgument::OPTIONAL, 'input-id');
        $this->addOption('batchGamesRange', null, InputOption::VALUE_OPTIONAL, '1-2');
        $this->addOption('maxNrOfGamesInARow', null, InputOption::VALUE_OPTIONAL, '0');
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
            $this->getLogger()->info('starting command-' . $this->customName);

            $inputId = $input->getArgument('inputId');
            $planningInput = null;
            if (is_string($inputId) && strlen($inputId) > 0) {
                $planningInput = $this->planningInputRepos->find($inputId);
            }
            if ($planningInput === null) {
                $this->getLogger()->info('planningInput ' . $inputId . ' not found');
                return 0;
            }

            $planning = null;
            $planningFilter = $this->getPlanningFilter($input);
            if( $planningFilter === null ) {
                $planning = $planningInput->getBestPlanning(PlanningType::GamesInARow);
            } // else {

            // }
            if( $planning === null ) {
                throw new \Exception('implements filter in command first', E_ERROR);
            }
            $planningOutput = new PlanningOutput($this->getLogger());
            $planningOutput->outputWithGames($planning, true);
            $planningOutput->outputWithTotals($planning, false);

        } catch (\Exception $exception) {
            if ($this->logger !== null) {
                $this->logger->error($exception->getMessage());
            }
        }
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

}
