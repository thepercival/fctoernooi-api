<?php

declare(strict_types=1);

namespace App\Commands;

use App\Mailer;
use Sports\Place\Location\Map as PlaceLocationMap;
use FCToernooi\Tournament;
use Psr\Container\ContainerInterface;
use App\Command;
use SportsHelpers\SportConfig;
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
use SportsHelpers\GameMode;

class Validator extends Command
{
    /**
     * @var TournamentRepository
     */
    protected $tournamentRepos;
    /**
     * @var StructureRepository
     */
    protected $structureRepos;
    /**
     * @var StructureValidator
     */
    protected $structureValidator;
    /**
     * @var PlanningInputRepository
     */
    protected $planningInputRepos;
    /**
     * @var GamesValidator
     */
    protected $gamesValidator;

    public function __construct(ContainerInterface $container)
    {
        $this->tournamentRepos = $container->get(TournamentRepository::class);
        $this->structureRepos = $container->get(StructureRepository::class);
        $this->planningInputRepos = $container->get(PlanningInputRepository::class);
        $this->structureValidator = new StructureValidator();
        $this->gamesValidator = new GamesValidator();

        parent::__construct($container->get(Configuration::class));
    }

    protected function configure()
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

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->initLogger($input, 'cron-tournament-validator');
        try {
            $this->logger->info('aan het valideren..');
            $filter = ["updated" => true];
            if (((int)$input->getArgument("tournamentId")) > 0) {
                $filter = ["id" => (int)$input->getArgument("tournamentId")];
            }
            $tournaments = $this->tournamentRepos->findBy($filter);
            /** @var Tournament $tournament */
            foreach ($tournaments as $tournament) {
                try {
                    $this->checkValidity($tournament);
                } catch (\Exception $exception) {
                    $this->logger->error($exception->getMessage());
                }
            }
            $this->logger->info('alle toernooien gevalideerd');
        } catch (\Exception $exception) {
            $this->logger->error($exception->getMessage());
        }
        return 0;
    }

    protected function checkValidity(Tournament $tournament)
    {
        try {
            $competition = $tournament->getCompetition();
            if (count($competition->getFields()) === 0) {
                throw new \Exception("het toernooi moet minimaal 1 veld bevatten", E_ERROR);
            }
            $structure = $this->structureRepos->getStructure($competition);
            $this->structureValidator->checkValidity($competition, $structure);
            $this->gamesValidator->setBlockedPeriod($tournament->getBreak());
            $this->validateGames($tournament, $structure->getFirstRoundNumber(), $competition->getReferees()->count());
        } catch (\Exception $exception) {
            throw new \Exception("toernooi-id(" . $tournament->getId() . ") => " . $exception->getMessage(), E_ERROR);
        }
    }

    protected function validateGames(Tournament $tournament, RoundNumber $roundNumber, int $nrOfReferees)
    {
        try {
            $this->gamesValidator->validate($roundNumber, $nrOfReferees);
            if ($roundNumber->hasNext()) {
                $this->validateGames($tournament, $roundNumber->getNext(), $nrOfReferees);
            }
        } catch (\Exception $exception) {
            $this->logger->info("invalid roundnumber " . $roundNumber->getId());
            // $this->showPlanning($tournament, $roundNumber, $nrOfReferees);
            throw new \Exception($exception->getMessage(), E_ERROR);
        }
    }

    protected function showPlanning(Tournament $tournament, RoundNumber $roundNumber, int $nrOfReferees)
    {
        $map = new PlaceLocationMap($tournament->getCompetitors()->toArray());
        $gameOutput = null;
        if( $roundNumber->getValidPlanningConfig()->getGameMode() === GameMode::AGAINST ) {
            $gameOutput = new AgainstGameOutput($map, $this->logger);
        } else {
            $gameOutput = new TogetherGameOutput($map, $this->logger);
        }
        foreach ($roundNumber->getGames(Game::ORDER_BY_BATCH) as $game) {
            $gameOutput->output($game);
        }
        return;


//        $planningOutput = new PlanningOutput($this->logger);
//
//        $inputService = new PlanningInputService();
//        $planningService = new PlanningService();
//        $planningInput = $this->planningInputRepos->getFromInput(
//            $inputService->get($roundNumber, $nrOfReferees)
//        );
//        if ($planningInput === null) {
//            $this->logger->info('no planninginput');
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
