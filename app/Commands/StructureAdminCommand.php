<?php

declare(strict_types=1);

namespace App\Commands;

use App\Command;
use App\Commands\Arguments\StructureActionArgument;
use App\Commands\Validator\NoUsersException;
use App\Mailer;
use DateTimeImmutable;
use Exception;
use FCToernooi\Tournament;
use FCToernooi\Tournament\Invitation\Repository as TournamentInvitationRepository;
use FCToernooi\Tournament\Repository as TournamentRepository;
use Psr\Container\ContainerInterface;
use Selective\Config\Configuration;
use Sports\Competition\Validator as CompetitionValidator;
use Sports\Competitor\StartLocationMap;
use Sports\Game\Against as AgainstGame;
use Sports\Game\Order as GameOrder;
use Sports\Output\Game\Against as AgainstGameOutput;
use Sports\Output\Game\Together as TogetherGameOutput;
use Sports\Output\StructureOutput;
use Sports\Planning\EditMode as PlanningEditMode;
use Sports\Round\Number as RoundNumber;
use Sports\Round\Number\GamesValidator;
use Sports\Structure;
use Sports\Structure\Repository as StructureRepository;
use Sports\Structure\Validator as StructureValidator;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class StructureAdminCommand extends Command
{
    protected TournamentRepository $tournamentRepos;
    protected TournamentInvitationRepository $invitationRepos;
    protected StructureRepository $structureRepos;
    protected StructureValidator $structureValidator;
    protected CompetitionValidator $competitionValidator;
    protected GamesValidator $gamesValidator;
    private DateTimeImmutable $deprecatedCreatedDateTime;
    private const DEFAULT_START_DAYS_IN_PAST = 7;
    private const DEFAULT_END_DAYS_IN_PAST = -1; // tomorrow
    public const TOURNAMENT_DEPRECATED_CREATED_DATETIME = '2020-06-01';
    private string $customName = 'structure-admin';

    public function __construct(ContainerInterface $container)
    {
        $x = @DateTimeImmutable::createFromFormat('Y-m-d', self::TOURNAMENT_DEPRECATED_CREATED_DATETIME);
        if ($x === false) {
            throw new Exception('invalid deprecated createdDateTime', E_ERROR);
        }
        $this->deprecatedCreatedDateTime = $x;

        /** @var TournamentRepository $tournamentRepos */
        $tournamentRepos = $container->get(TournamentRepository::class);
        $this->tournamentRepos = $tournamentRepos;

        /** @var TournamentInvitationRepository $invitationRepos */
        $invitationRepos = $container->get(TournamentInvitationRepository::class);
        $this->invitationRepos = $invitationRepos;

        /** @var StructureRepository $structureRepos */
        $structureRepos = $container->get(StructureRepository::class);
        $this->structureRepos = $structureRepos;

        $this->competitionValidator = new CompetitionValidator();
        $this->structureValidator = new StructureValidator();
        $this->gamesValidator = new GamesValidator();

        /** @var Mailer|null $mailer */
        $mailer = $container->get(Mailer::class);
        $this->mailer = $mailer;

        /** @var Configuration $config */
        $config = $container->get(Configuration::class);
        parent::__construct($config);
    }

    protected function configure(): void
    {
        $this
            // the name of the command (the part after "bin/console")
            ->setName('app:' . $this->customName)
            // the short description shown while running "php bin/console list"
            ->setDescription('validates the tournaments')
            // the full command description shown when running the command with
            // the "--help" option
            ->setHelp('validates the tournaments');
        parent::configure();

        $this->addOption('startDate', null, InputOption::VALUE_OPTIONAL, 'Y-m-d <= format, for validating');
        $this->addOption('endDate', null, InputOption::VALUE_OPTIONAL, 'Y-m-d <= format, for validating');
        $this->addOption('tournamentId', null, InputOption::VALUE_OPTIONAL, 'filter for action');

        // $actions = array_map(fn(StructureActionArgument $action) => $action->value, StructureActionArgument::cases());
        $this->addArgument('action', InputArgument::REQUIRED, join(',', ['show', 'validate']));
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $loggerName = 'command-' . $this->customName;
            $logger = $this->initLogger(
                $this->getLogLevel($input),
                $this->getMailLog($input),
                $this->getPathOrStdOut($input, $loggerName),
                $loggerName,
            );
            $tournaments = $this->getTournamentsFromInput($input);

            $logger->info('aan het valideren..');

            $action = $this->getAction($input);
            $tournamentCompetitorValidator = new \FCToernooi\Competitor\Validator();

            foreach ($tournaments as $tournament) {
                $description = 'validate id ' . (string)$tournament->getId() . ', created at ';
                $description .= $tournament->getCreatedDateTime()->format(DATE_ATOM);

                $logger->info($description);
                /** @var Structure|null $structure */
                $structure = null;
                try {
                    $structure = $this->structureRepos->getStructure($tournament->getCompetition());
                    if( $action === StructureActionArgument::Show ) {
                        $this->addStructureToLog($tournament, $structure);
                    } else {
                        $tournamentCompetitorValidator->checkValidity($tournament);
                        $this->checkValidity($tournament, $structure);

                        if ($tournament->getUsers()->count() === 0) {
                            $invitations = $this->invitationRepos->findBy(['tournament' => $tournament]);
                            if (count($invitations) === 0) {
                                throw new NoUsersException(
                                    'toernooi-id(' . ((string)$tournament->getId()) . ') => has no users or invitations',
                                    E_ERROR
                                );
                            }
                        }
                    }
                } catch (NoUsersException $exception) {
                    $logger->error($exception->getMessage());
                    if( $action === StructureActionArgument::Show && $structure !== null) {
                        $this->addStructureToLog($tournament, $structure);
                    }
                } catch (Exception $exception) {
                    $logger->error($exception->getMessage());
                }
            }
            $logger->info('alle toernooien gevalideerd');
        } catch (Exception $exception) {
            if ($this->logger !== null) {
                $this->logger->error($exception->getMessage());
            }
        }
        return 0;
    }

    protected function getAction(InputInterface $input): StructureActionArgument
    {
        /** @var string $action */
        $action = $input->getArgument('action');
        return StructureActionArgument::from($action);
    }

    protected function checkValidity(Tournament $tournament, Structure $structure): void
    {
        try {
            $competition = $tournament->getCompetition();
            if (count($competition->getFields()) === 0) {
                throw new Exception('het toernooi moet minimaal 1 veld bevatten', E_ERROR);
            }

            $this->competitionValidator->checkValidity($competition);
            $this->structureValidator->checkValidity($competition, $structure, $tournament->getPlaceRanges());
            $roundNumber = $structure->getFirstRoundNumber();
            $this->validateGames($tournament, $roundNumber, $competition->getReferees()->count());
        } catch (\Throwable $throwable) {
            // $this->showPlanning($tournament, $roundNumber, $competition->getReferees()->count());
            throw new Exception('toernooi-id(' . ((string)$tournament->getId()) . ') => ' . $throwable->getMessage(), E_ERROR);
        }
    }

    protected function validateGames(Tournament $tournament, RoundNumber $roundNumber, int $nrOfReferees): void
    {
        try {
            if ($roundNumber->getValidPlanningConfig()->getEditMode() === PlanningEditMode::Auto) {
                $this->gamesValidator->validate(
                    $roundNumber,
                    $nrOfReferees,
                    true,
                    $tournament->createRecessPeriods()
                );
            }
            $nextRoundNumber = $roundNumber->getNext();
            if ($nextRoundNumber !== null) {
                $this->validateGames($tournament, $nextRoundNumber, $nrOfReferees);
            }
        } catch (Exception $exception) {
            // $this->getLogger()->info('invalid roundnumber ' . ((string)$roundNumber->getId()));
            throw new Exception($exception->getMessage(), E_ERROR);
        }
    }

    protected function showPlanning(Tournament $tournament, RoundNumber $roundNumber, int $nrOfReferees): void
    {
        $map = new StartLocationMap(array_values($tournament->getCompetitors()->toArray()));
        $againstGameOutput = new AgainstGameOutput($map, $this->getLogger());
        $togetherGameOutput = new TogetherGameOutput($map, $this->getLogger());
        foreach ($roundNumber->getGames(GameOrder::ByBatch) as $game) {
            if ($game instanceof AgainstGame) {
                $againstGameOutput->output($game);
            } else {
                $togetherGameOutput->output($game);
            }
        }
        // return;

//        $planningOutput = new PlanningOutput($this->getLogger());
//
//        $inputService = new PlanningInputService();
//        $planningService = new PlanningService();
//        $planningInput = $this->planningInputRepos->getFromInput(
//            $inputService->get($roundNumber, $nrOfReferees)
//        );
//        if ($planningInput === null) {
//            $this->getLogger()->info('no planninginput');
//            return;
//        }
//
//        $bestPlanning = $planningService->getBestPlanning($planningInput);
//        if ($bestPlanning === null) {
//            $planningOutput->outputPlanningInput($planningInput, 'no best planning for');
//            return;
//        }
//        $planningOutput->outputWithGames($bestPlanning, true);
    }

    protected function addStructureToLog(Tournament $tournament, Structure $structure): void
    {
        try {
            (new StructureOutput($this->getLogger()))->output($structure);
        } catch (Exception $exception) {
            $this->getLogger()->error('could not find structure for tournamentId ' . ((string)$tournament->getId()));
        }
    }

    /**
     * @param InputInterface $input
     * @return list<Tournament>
     */
    protected function getTournamentsFromInput(InputInterface $input): array
    {
        /** @var string|null $tournamentId */
        $tournamentId = $input->getOption('tournamentId');
        if ( $tournamentId !== null && (int)$tournamentId > 0) {
            $tournament = $this->tournamentRepos->find($tournamentId);
            return $tournament !== null ? [$tournament] : [];
        }

        $start = $this->getStartFromInput($input);
        $end = $this->getEndFromInput($input, $start);
        $shellFilter = new Tournament\ShellFilter( $start, $end, null, null, null);
        return $this->tournamentRepos->findByFilter($shellFilter);
    }

    protected function getStartFromInput(InputInterface $input): DateTimeImmutable
    {
        $defaultStartDate = (new DateTimeImmutable('today'))->modify('-' . self::DEFAULT_START_DAYS_IN_PAST . ' days');
        $defaultEndDate = (new DateTimeImmutable('today'))->modify('-' . self::DEFAULT_END_DAYS_IN_PAST . ' days');

        $start = $input->getOption('startDate');
        if (!is_string($start) || strlen($start) === 0) {
            return $defaultStartDate;
        }
        $startDateFromInput = DateTimeImmutable::createFromFormat('Y-m-d', $start);
        if ($startDateFromInput === false) {
            return $defaultStartDate;
        }
        if ($startDateFromInput->getTimestamp() <= $this->deprecatedCreatedDateTime->getTimestamp()) {
            return $this->deprecatedCreatedDateTime->modify('+1 days');
        }
        if ($startDateFromInput->getTimestamp() >= $defaultEndDate->getTimestamp()) {
            throw new \Exception('it is not allowed to choose a start in the future', E_ERROR);
        }
        return $startDateFromInput;
    }

    protected function getEndFromInput(InputInterface $input, DateTimeImmutable $start): DateTimeImmutable
    {
        $defaultEndDate = (new DateTimeImmutable('today'))->modify('-' . self::DEFAULT_END_DAYS_IN_PAST . ' days');

        $end = $input->getOption('endDate');
        if (!is_string($end) || strlen($end) === 0) {
            return $defaultEndDate;
        }
        $endDateFromInput = DateTimeImmutable::createFromFormat('Y-m-d', $end);
        if ($endDateFromInput === false) {
            return $defaultEndDate;
        }
        if ($endDateFromInput->getTimestamp() < $start->getTimestamp()) {
            return $defaultEndDate;
        }
        return $endDateFromInput;
    }
}
