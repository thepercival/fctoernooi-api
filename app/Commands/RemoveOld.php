<?php

declare(strict_types=1);

namespace App\Commands;

use App\Command;
use App\Mailer;
use DateTime;
use FCToernooi\Tournament\Repository as TournamentRepository;
use Monolog\Logger;
use Psr\Container\ContainerInterface;
use Selective\Config\Configuration;
use Sports\Competition\Repository as CompetitionRepository;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RemoveOld extends Command
{
    private string $customName = 'remove-old-tournaments';
    protected int $nrOfMonthsBeforeRemoval;
    protected CompetitionRepository $competitionRepos;
    protected TournamentRepository $tournamentRepos;

    public function __construct(ContainerInterface $container)
    {
        /** @var CompetitionRepository $competitionRepos */
        $competitionRepos = $container->get(CompetitionRepository::class);
        $this->competitionRepos = $competitionRepos;

        /** @var TournamentRepository $tournamentRepos */
        $tournamentRepos = $container->get(TournamentRepository::class);
        $this->tournamentRepos = $tournamentRepos;

        /** @var Configuration $config */
        $config = $container->get(Configuration::class);

        /** @var Mailer|null $mailer */
        $mailer = $container->get(Mailer::class);
        $this->mailer = $mailer;

        $this->nrOfMonthsBeforeRemoval = $config->getInt('tournament.nrOfMonthsBeforeRemoval');
        parent::__construct($config);
    }

    protected function configure(): void
    {
        $this
            // the name of the command (the part after "bin/console")
            ->setName('app:' . $this->customName)
            // the short description shown while running "php bin/console list"
            ->setDescription('removes tournaments with a start before x months in the past')
            // the full command description shown when running the command with
            // the "--help" option
            ->setHelp('removes old tournaments');
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $loggerName = 'command-' . $this->customName;
            $mailHandler = $this->getMailHandler((string)$this->getName(), Logger::INFO);
            $logger = $this->initLogger(
                $this->getLogLevel($input),
                $this->getStreamDef($input, $loggerName),
                $loggerName,
                $mailHandler
            );

            if ($this->nrOfMonthsBeforeRemoval <= 11) {
                throw new \Exception('nrOfMonthsBeforeRemoval must be greater than 11', E_ERROR);
            }
            $oldTournaments = $this->tournamentRepos->findByFilter(
                null,
                null,
                null,
                null,
                null,
                $this->getRemovalDeadline()
            );
            // $nrOfCompetitions = count($oldCompetitions);
            while ($oldTournament = array_shift($oldTournaments)) {
                $msg = 'removed competition with id "' . (string)$oldTournament->getCompetition()->getId() . '" ';
                $createdDateTime = $oldTournament->getCreatedDateTime()->format(DateTime::ISO8601);
                $msg .= 'and tournament.createdDateTime = "' . $createdDateTime . '"';
                $logger->info($msg);
                $this->competitionRepos->remove($oldTournament->getCompetition(), true);
            }
        } catch (\Exception $exception) {
            if ($this->logger !== null) {
                $this->logger->error($exception->getMessage());
            }
        }
        return 0;
    }

    protected function getRemovalDeadline(): \DateTimeImmutable
    {
        return (new \DateTimeImmutable())->modify('-' . $this->nrOfMonthsBeforeRemoval . ' months');
    }
}
