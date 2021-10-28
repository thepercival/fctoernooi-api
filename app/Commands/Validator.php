<?php
declare(strict_types=1);

namespace App\Commands;

use App\Mailer;
use DateTime;
use DateTimeImmutable;
use Exception;
use Sports\Competition;
use Sports\Structure;
use Sports\Game\Against as AgainstGame;
use Sports\Competitor\Map as CompetitorMap;
use FCToernooi\Tournament;
use Psr\Container\ContainerInterface;
use App\Command;
use Sports\Output\StructureOutput;
use Sports\Planning\EditMode as PlanningEditMode;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Selective\Config\Configuration;
use FCToernooi\Tournament\Repository as TournamentRepository;
use Sports\Round\Number as RoundNumber;
use Sports\Structure\Repository as StructureRepository;
use SportsPlanning\Input\Repository as PlanningInputRepository;
use Sports\Structure\Validator as StructureValidator;
use Sports\Competition\Validator as CompetitionValidator;
use Sports\Round\Number\GamesValidator;
use Sports\Output\Game\Against as AgainstGameOutput;
use Sports\Output\Game\Together as TogetherGameOutput;
use Sports\Game\Order as GameOrder;

class Validator extends Command
{
    protected TournamentRepository $tournamentRepos;
    protected StructureRepository $structureRepos;
    protected StructureValidator $structureValidator;
    protected CompetitionValidator $competitionValidator;
    protected PlanningInputRepository $planningInputRepos;
    protected GamesValidator $gamesValidator;
    private const DEFAULT_START_DAYS_IN_PAST = 7;
    private const DEFAULT_END_DAYS_IN_PAST = -1; // tomorrow
    private const TOURNAMENT_STARTID_VALIDATE_PRIO = 4000;

    public function __construct(ContainerInterface $container)
    {
        $this->tournamentRepos = $container->get(TournamentRepository::class);
        $this->structureRepos = $container->get(StructureRepository::class);
        $this->planningInputRepos = $container->get(PlanningInputRepository::class);
        $this->competitionValidator = new CompetitionValidator();
        $this->structureValidator = new StructureValidator();
        $this->gamesValidator = new GamesValidator();

        parent::__construct($container->get(Configuration::class));
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
                $structure = null;
                try {
                    if ($tournament->getUsers()->count() === 0) {
                        throw new \Exception('no users for tournament ' . (string)$tournament->getId(), E_ERROR);
                    }
                    $structure = $this->structureRepos->getStructure($tournament->getCompetition());
                    $this->checkValidity($tournament, $structure);
                    $this->addStructureToLog($tournament, $structure);
                } catch (Exception $exception) {
                    $this->getLogger()->error($exception->getMessage());
                    if ($structure !== null) {
                        $this->addStructureToLog($tournament, $structure);
                    }
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
        } catch (Exception $exception) {
            // $this->showPlanning($tournament, $roundNumber, $competition->getReferees()->count());
            throw new Exception('toernooi-id(' . ((string)$tournament->getId()) . ') => ' . $exception->getMessage(), E_ERROR);
        }
    }

    protected function validateGames(Tournament $tournament, RoundNumber $roundNumber, int $nrOfReferees): void
    {
        try {
            if ($roundNumber->getValidPlanningConfig()->getEditMode() === PlanningEditMode::Auto) {
                $this->gamesValidator->validate(
                    $roundNumber,
                    $nrOfReferees,
                    $tournament->getId() > self::TOURNAMENT_STARTID_VALIDATE_PRIO,
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
            // (new StructureOutput($this->getLogger()))->output($structure);
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
        $tournamentId = $input->getArgument('tournamentId');
        if (is_string($tournamentId) && (int)$tournamentId > 0) {
            $tournament = $this->tournamentRepos->find($tournamentId);
            return $tournament !== null ? [$tournament] : [];
        }

        $start = $this->getStartFromInput($input);
        $end = $this->getEndFromInput($input);
        return $this->tournamentRepos->findByFilter(null, $start, $end);
    }

    protected function getStartFromInput(InputInterface $input): DateTimeImmutable
    {
        $startDate = (new DateTimeImmutable('today'))->modify('-' . self::DEFAULT_START_DAYS_IN_PAST . ' days');
        $start = $input->getOption('startdate');
        if (!is_string($start) || strlen($start) === 0) {
            return $startDate;
        }
        $startDateFromInput = DateTimeImmutable::createFromFormat('Y-m-d', $start);
        if ($startDateFromInput === false) {
            return $startDate;
        }
        return $startDateFromInput;
    }

    protected function getEndFromInput(InputInterface $input): DateTimeImmutable
    {
        $endDate = (new DateTimeImmutable('today'))->modify('-' . self::DEFAULT_END_DAYS_IN_PAST . ' days');
        $end = $input->getOption('enddate');
        if (!is_string($end) || strlen($end) === 0) {
            return $endDate;
        }
        $endDateFromInput = DateTimeImmutable::createFromFormat('Y-m-d', $end);
        if ($endDateFromInput === false) {
            return $endDate;
        }
        return $endDateFromInput;
    }
}
