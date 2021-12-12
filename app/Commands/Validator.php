<?php

declare(strict_types=1);

namespace App\Commands;

use App\Command;
use App\Commands\Validator\NoUsersException;
use DateTimeImmutable;
use Exception;
use FCToernooi\Tournament;
use FCToernooi\Tournament\Repository as TournamentRepository;
use Psr\Container\ContainerInterface;
use Selective\Config\Configuration;
use Sports\Competition\Validator as CompetitionValidator;
use Sports\Competitor\Map as CompetitorMap;
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
use SportsPlanning\Input\Repository as PlanningInputRepository;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Validator extends Command
{
    protected TournamentRepository $tournamentRepos;
    protected StructureRepository $structureRepos;
    protected StructureValidator $structureValidator;
    protected CompetitionValidator $competitionValidator;
    protected PlanningInputRepository $planningInputRepos;
    protected GamesValidator $gamesValidator;
    private DateTimeImmutable $deprecatedCreatedDateTime;
    private const DEFAULT_START_DAYS_IN_PAST = 7;
    private const DEFAULT_END_DAYS_IN_PAST = -1; // tomorrow
    private const TOURNAMENT_DEPRECATED_CREATED_DATETIME = '2020-06-01';

    public function __construct(ContainerInterface $container)
    {
        $x = @DateTimeImmutable::createFromFormat('Y-m-d', self::TOURNAMENT_DEPRECATED_CREATED_DATETIME);
        if ($x === false) {
            throw new Exception('12', E_ERROR);
        }
        $this->deprecatedCreatedDateTime = $x;

        /** @var TournamentRepository $tournamentRepos */
        $tournamentRepos = $container->get(TournamentRepository::class);
        $this->tournamentRepos = $tournamentRepos;

        /** @var StructureRepository $structureRepos */
        $structureRepos = $container->get(StructureRepository::class);
        $this->structureRepos = $structureRepos;

        /** @var PlanningInputRepository $planningInputRepos */
        $planningInputRepos = $container->get(PlanningInputRepository::class);
        $this->planningInputRepos = $planningInputRepos;

        $this->competitionValidator = new CompetitionValidator();
        $this->structureValidator = new StructureValidator();
        $this->gamesValidator = new GamesValidator();

        /** @var Configuration $config */
        $config = $container->get(Configuration::class);
        parent::__construct($config);
    }

    protected function configure(): void
    {
        $this
            // the name of the command (the part after "bin/console")
            ->setName('app:validate')
            // the short description shown while running "php bin/console list"
            ->setDescription('validates the tournaments')
            // the full command description shown when running the command with
            // the "--help" option
            ->setHelp('validates the tournaments');
        parent::configure();

        $this->addOption('startdate', null, InputArgument::OPTIONAL, 'Y-m-d');
        $this->addOption('enddate', null, InputArgument::OPTIONAL, 'Y-m-d');

        $this->addArgument('tournamentId', InputArgument::OPTIONAL);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->initLogger($input, 'command-validate');

        try {
            $tournaments = $this->getTournamentsFromInput($input);

            $this->getLogger()->info('aan het valideren..');

            foreach ($tournaments as $tournament) {
                $description = 'validate id ' . (string)$tournament->getId() . ', created at ';
                $description .= $tournament->getCreatedDateTime()->format(DATE_ISO8601);

                $this->getLogger()->info($description);
                /** @var Structure|null $structure */
                $structure = null;
                try {
                    $structure = $this->checkValidity($tournament);
                    if ($tournament->getUsers()->count() === 0) {
                        throw new NoUsersException('toernooi-id(' . ((string)$tournament->getId()) . ') => no users for tournament', E_ERROR);
                    }
                } catch (NoUsersException $exception) {
                    $this->getLogger()->error($exception->getMessage());
//                    if ($structure !== null) {
//                        $this->addStructureToLog($tournament, $structure);
//                    }
                } catch (Exception $exception) {
                    $this->getLogger()->error($exception->getMessage());
                }
            }
            $this->getLogger()->info('alle toernooien gevalideerd');
        } catch (Exception $exception) {
            if ($this->logger !== null) {
                $this->logger->error($exception->getMessage());
            }
        }
        return 0;
    }

    protected function checkValidity(Tournament $tournament): Structure
    {
        try {
            $structure = $this->structureRepos->getStructure($tournament->getCompetition());
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
        return $structure;
    }

    protected function validateGames(Tournament $tournament, RoundNumber $roundNumber, int $nrOfReferees): void
    {
        try {
            if ($roundNumber->getValidPlanningConfig()->getEditMode() === PlanningEditMode::Auto) {
                $this->gamesValidator->validate(
                    $roundNumber,
                    $nrOfReferees,
                    true,
                    $tournament->getBreak()
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
        $map = new CompetitorMap(array_values($tournament->getCompetitors()->toArray()));
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
        $tournamentId = (string)$input->getArgument('tournamentId');
        if ((int)$tournamentId > 0) {
            $tournament = $this->tournamentRepos->find($tournamentId);
            return $tournament !== null ? [$tournament] : [];
        }

        $start = $this->getStartFromInput($input);
        $end = $this->getEndFromInput($input, $start);
        return $this->tournamentRepos->findByFilter(null, $start, $end);
    }

    protected function getStartFromInput(InputInterface $input): DateTimeImmutable
    {
        $defaultStartDate = (new DateTimeImmutable('today'))->modify('-' . self::DEFAULT_START_DAYS_IN_PAST . ' days');
        $defaultEndDate = (new DateTimeImmutable('today'))->modify('-' . self::DEFAULT_END_DAYS_IN_PAST . ' days');

        $start = $input->getOption('startdate');
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

        $end = $input->getOption('enddate');
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
