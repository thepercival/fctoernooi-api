<?php
declare(strict_types=1);

namespace App\Commands;

use App\Mailer;
use Exception;
use Sports\Game\Against as AgainstGame;
use Sports\Competitor\Map as CompetitorMap;
use FCToernooi\Tournament;
use Psr\Container\ContainerInterface;
use App\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Selective\Config\Configuration;
use FCToernooi\Tournament\Repository as TournamentRepository;
use Sports\Round\Number as RoundNumber;
use Sports\Structure\Repository as StructureRepository;
use SportsPlanning\Input\Repository as PlanningInputRepository;
use Sports\Structure\Validator as StructureValidator;
use Sports\Round\Number\GamesValidator;
use Sports\Output\Game\Against as AgainstGameOutput;
use Sports\Output\Game\Together as TogetherGameOutput;
use Sports\Game;

class Validator extends Command
{
    protected TournamentRepository $tournamentRepos;
    protected StructureRepository $structureRepos;
    protected StructureValidator $structureValidator;
    protected PlanningInputRepository $planningInputRepos;
    protected GamesValidator $gamesValidator;

    public function __construct(ContainerInterface $container)
    {
        $this->tournamentRepos = $container->get(TournamentRepository::class);
        $this->structureRepos = $container->get(StructureRepository::class);
        $this->planningInputRepos = $container->get(PlanningInputRepository::class);
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

        $this->addArgument('tournamentId', InputArgument::OPTIONAL);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->initLogger($input, 'cron-tournament-validator');
        try {
            $this->getLogger()->info('aan het valideren..');
            $filter = ['updated' => true];
            $tournamentId = $input->getArgument('tournamentId');
            if (is_string($tournamentId) && (int)$tournamentId > 0) {
                $filter = ['id' => $tournamentId];
            }
            $tournaments = $this->tournamentRepos->findBy($filter);
            /** @var Tournament $tournament */
            foreach ($tournaments as $tournament) {
                try {
                    $this->checkValidity($tournament);
                } catch (Exception $exception) {
                    $this->getLogger()->error($exception->getMessage());
                }
            }
            $this->getLogger()->info('alle toernooien gevalideerd');
        } catch (Exception $exception) {
            if( $this->logger !== null ) {
                $this->logger->error($exception->getMessage());
            }
        }
        return 0;
    }

    protected function checkValidity(Tournament $tournament): void
    {
        try {
            $competition = $tournament->getCompetition();
            if (count($competition->getFields()) === 0) {
                throw new Exception('het toernooi moet minimaal 1 veld bevatten', E_ERROR);
            }
            $structure = $this->structureRepos->getStructure($competition);
            $this->structureValidator->checkValidity($competition, $structure, $tournament->getPlaceRanges());
            $this->validateGames($tournament, $structure->getFirstRoundNumber(), $competition->getReferees()->count());
        } catch (Exception $exception) {
            throw new Exception('toernooi-id(' . ((string)$tournament->getId()) . ') => ' . $exception->getMessage(), E_ERROR);
        }
    }

    protected function validateGames(Tournament $tournament, RoundNumber $roundNumber, int $nrOfReferees): void
    {
        try {
            $this->gamesValidator->validate($roundNumber, $nrOfReferees, $tournament->getBreak());
            $nextRoundNumber = $roundNumber->getNext();
            if ($nextRoundNumber !== null) {
                $this->validateGames($tournament, $nextRoundNumber, $nrOfReferees);
            }
        } catch (Exception $exception) {
            $this->getLogger()->info('invalid roundnumber ' . ((string)$roundNumber->getId()));
            // $this->showPlanning($tournament, $roundNumber, $nrOfReferees);
            throw new Exception($exception->getMessage(), E_ERROR);
        }
    }

    protected function showPlanning(Tournament $tournament, RoundNumber $roundNumber, int $nrOfReferees): void
    {
        $map = new CompetitorMap(array_values($tournament->getCompetitors()->toArray()));
        $againstGameOutput = new AgainstGameOutput($map, $this->getLogger());
        $togetherGameOutput = new TogetherGameOutput($map, $this->getLogger());
        foreach ($roundNumber->getGames(Game::ORDER_BY_BATCH) as $game) {
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
}
