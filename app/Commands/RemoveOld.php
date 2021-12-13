<?php

declare(strict_types=1);

namespace App\Commands;

use App\Command;
use App\Mailer;
use DateTime;
use Monolog\Logger;
use Psr\Container\ContainerInterface;
use Selective\Config\Configuration;
use Sports\Competition\Repository as CompetitionRepository;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RemoveOld extends Command
{
    protected int $nrOfMonthsBeforeRemoval;
    protected CompetitionRepository $competitionRepos;

    public function __construct(ContainerInterface $container)
    {
        $this->competitionRepos = $container->get(CompetitionRepository::class);
        /** @var Configuration $config */
        $config = $container->get(Configuration::class);
        $this->mailer = $container->get(Mailer::class);
        $this->nrOfMonthsBeforeRemoval = $config->getInt('tournament.nrOfMonthsBeforeRemoval');
        parent::__construct($config);
    }

    protected function configure(): void
    {
        $this
            // the name of the command (the part after "bin/console")
            ->setName('app:remove-old-tournaments')
            // the short description shown while running "php bin/console list"
            ->setDescription('removes tournaments with a start before x months in the past')
            // the full command description shown when running the command with
            // the "--help" option
            ->setHelp('removes old tournaments');
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $mailHandler = $this->getMailHandler((string)$this->getName(), Logger::INFO);
        $this->initLogger($input, 'cron-remove-old-tournaments', $mailHandler);
        try {
            $oldCompetitions = $this->competitionRepos->findByStartDate($this->getRemovalDeadline());
            // $nrOfCompetitions = count($oldCompetitions);
            while ($oldCompetition = array_shift($oldCompetitions)) {
                $msg = 'removed competition with id "' . (string)$oldCompetition->getId() . '" ';
                $start = $oldCompetition->getStartDateTime()->format(DateTime::ISO8601);
                $msg .= 'and startDateTime "' . $start . '"';
                $this->getLogger()->info($msg);
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
