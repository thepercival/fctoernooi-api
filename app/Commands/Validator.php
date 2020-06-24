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
use Voetbal\Structure;
use Voetbal\Structure\Repository;
use Voetbal\Structure\Repository as StructureRepository;
use Voetbal\Structure\Validator as StructureValidator;
use Voetbal\Round\Number\GamesValidator;

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
     * @var GamesValidator
     */
    protected $gamesValidator;

    public function __construct(ContainerInterface $container)
    {
        $this->tournamentRepos = $container->get(TournamentRepository::class);
        $this->structureRepos = $container->get(StructureRepository::class);
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

            // needs to be turned on eventually
            // $this->gamesValidator->validateStructure($structure, $competition->getReferees()->count());
        } catch (\Exception $e) {
            throw new \Exception("toernooi-id(" . $tournament->getId() . ") => " . $e->getMessage(), E_ERROR);
        }
    }
}
