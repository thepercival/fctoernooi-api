<?php

namespace App\Commands;

use App\Mailer;
use FCToernooi\Tournament;
use Psr\Container\ContainerInterface;
use App\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Selective\Config\Configuration;
use FCToernooi\Tournament\Repository as TournamentRepository;
use Voetbal\Planning\Input\Service as PlanningInputService;
use Voetbal\Planning\Service as PlanningService;
use Voetbal\Structure;
use Voetbal\Round\Number as RoundNumber;
use Voetbal\Structure\Repository;
use Voetbal\Structure\Repository as StructureRepository;
use Voetbal\Planning\Input\Repository as PlanningInputRepository;
use Voetbal\Structure\Validator as StructureValidator;
use Voetbal\Round\Number\GamesValidator;
use Voetbal\Output\Planning\Batch as BatchOutput;
use Voetbal\Output\Planning as PlanningOutput;
use Voetbal\Output\Game as GameOutput;
use Voetbal\Game;

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
                } catch (\Exception $e) {
                    $this->logger->error($e->getMessage());
                }
            }
            $this->logger->info('alle toernooien gevalideerd');
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
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
            $this->validateGames($structure->getFirstRoundNumber(), $competition->getReferees()->count());
        } catch (\Exception $e) {
            throw new \Exception("toernooi-id(" . $tournament->getId() . ") => " . $e->getMessage(), E_ERROR);
        }
    }

    protected function validateGames(RoundNumber $roundNumber, int $nrOfReferees)
    {
        try {
            $this->gamesValidator->validate($roundNumber, $nrOfReferees);
            if ($roundNumber->hasNext()) {
                $this->validateGames($roundNumber->getNext(), $nrOfReferees);
            }
        } catch (\Exception $e) {
            $this->logger->info("invalid roundnumber " . $roundNumber->getId());
            // $this->showPlanning($roundNumber, $nrOfReferees);
            throw new \Exception($e->getMessage(), E_ERROR);
        }
    }

    protected function showPlanning(RoundNumber $roundNumber, int $nrOfReferees)
    {
        $gameOutput = new GameOutput($this->logger);
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
