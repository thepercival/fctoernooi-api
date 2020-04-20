<?php

namespace App\Commands;

use _HumbugBox09702017065e\Nette\Neon\Exception;
use FCToernooi\Tournament;
use Psr\Container\ContainerInterface;
use App\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Selective\Config\Configuration;
use FCToernooi\Tournament\Repository as TournamentRepository;
use Voetbal\Structure\Repository as StructureRepository;
use Voetbal\Structure\Validator as StructureValidator;

class Validator extends Command
{
    /**
     * @var TournamentRepository
     */
    protected $tournamentRepos;
    /**
     * @var StructureValidator
     */
    protected $structureValidator;

    public function __construct(ContainerInterface $container)
    {
        $this->tournamentRepos = $container->get(TournamentRepository::class);
        $this->structureValidator = new StructureValidator($container->get(StructureRepository::class));
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
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->initLogger($input, 'cron-tournament-validator');
        try {
            $tournaments = $this->tournamentRepos->findBy(["updated" => true]);
            /** @var Tournament $tournament */
            foreach ($tournaments as $tournament) {
                try {
                    $this->checkValidity($tournament);
                } catch (\Exception $e) {
                    $this->logger->error($e->getMessage());
                }
            }
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
        }
        return 0;
    }

    protected function checkValidity(Tournament $tournament)
    {
        try {
            $competition = $tournament->getCompetition();
            if ($competition->getFields()->count() === 0) {
                throw new \Exception("het toernooi moet minimaal 1 veld bevatten", E_ERROR);
            }
            $this->structureValidator->checkValidity($competition);
        } catch (\Exception $e) {
            throw new \Exception("toernooi-id(" . $tournament->getId() . ") => " . $e->getMessage(), E_ERROR);
        }
    }
}
