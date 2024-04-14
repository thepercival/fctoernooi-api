<?php

declare(strict_types=1);

namespace App\Commands\Pdf;

use App\Command;
use App\Commands\StructureAdminCommand;
use App\Export\Pdf\DocumentFactory as PdfDocumentFactory;
use App\Export\PdfService;
use App\Export\PdfSubject;
use App\QueueService\Pdf as PdfQueueService;
use App\TmpService;
use DateTimeImmutable;
use Exception;
use FCToernooi\Tournament;
use FCToernooi\Tournament\Repository as TournamentRepository;
use Memcached;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Selective\Config\Configuration;
use Sports\Output\StructureOutput;
use Sports\Round\Number\GamesValidator;
use Sports\Structure;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;



class PdfValidateCommand extends Command
{
    private string $customName = 'validate-pdf';
    protected TournamentRepository $tournamentRepos;
    protected GamesValidator $gamesValidator;
    protected PdfService $pdfService;
    protected PdfQueueService $queueService;
    protected int $borderTimestamp;

    public function __construct(ContainerInterface $container)
    {
        $dateString = StructureAdminCommand::TOURNAMENT_DEPRECATED_CREATED_DATETIME;
        $this->borderTimestamp = (new DateTimeImmutable($dateString))->getTimestamp();

        /** @var TournamentRepository $tournamentRepos */
        $tournamentRepos = $container->get(TournamentRepository::class);
        $this->tournamentRepos = $tournamentRepos;

        /** @var Memcached $memcached */
        $memcached = $container->get(Memcached::class);
        /** @var Configuration $config */
        $config = $container->get(Configuration::class);
        /** @var LoggerInterface $logger */
        $logger = $container->get(LoggerInterface::class);
        $this->pdfService = new PdfService(
            $config,
            new TmpService(),
            new PdfDocumentFactory($config),
            $memcached,
            $logger
        );

        $this->queueService = new PdfQueueService($config->getArray('queue'));

        $this->gamesValidator = new GamesValidator();

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
            ->setDescription('validates the tournament-pdfs')
            // the full command description shown when running the command with
            // the "--help" option
            ->setHelp('validates the tournaments');
        parent::configure();

        $this->addArgument('tournamentId', InputArgument::OPTIONAL);
        $this->addOption('subjects', null, InputOption::VALUE_OPTIONAL, 'show certain subjects');
        $this->addOption('amount', null, InputOption::VALUE_OPTIONAL, '10');
        $this->addOption('startId', null, InputOption::VALUE_OPTIONAL, '1');
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

            $logger->info('pdf-en aan het genereren..');
            $filter = [];
            $tournamentId = $input->getArgument('tournamentId');
            if (is_string($tournamentId) && (int)$tournamentId > 0) {
                $filter = ['id' => $tournamentId];
            }
            $amount = $this->getAmountFromInput($input);
            $startId = $this->getStartIdFromInput($input);
            $tournaments = $this->tournamentRepos->findBy($filter);
            $subjects = $this->getSubjects($input);
            /** @var Tournament $tournament */
            foreach ($tournaments as $tournament) {
                if ($this->tournamentTooOld($tournament) || $tournament->getId() < $startId) {
                    continue;
                }
                if ($amount-- === 0) {
                    $logger->info('max amount reached');
                    break;
                }

                    $logger->info('creating pdf for ' . (string)$tournament->getId());
                    $structure = null;
                    try {
                        $this->createPdf($tournament, $subjects);
                        // $this->addStructureToLog($tournament, $structure);
                    } catch (Exception $exception) {
                        $logger->error($exception->getMessage());
                        if ($structure !== null && count($filter) > 0) {
                            $this->addStructureToLog($tournament, $structure);
                        }
                    }

            }
            $logger->info('alle pdf-en zijn gegenereerd');
        } catch (Exception $exception) {
            if ($this->logger !== null) {
                $this->logger->error($exception->getMessage());
            }
        }
        return 0;
    }


    protected function getAmountFromInput(InputInterface $input): int
    {
        return $this->getIntFromInput($input, 'amount', 10);
    }

    protected function getStartIdFromInput(InputInterface $input): int
    {
        return $this->getIntFromInput($input, 'startId', 1);
    }

    protected function getIntFromInput(InputInterface $input, string $key, int $defaultValue): int
    {
        /** @var string|null $value */
        $value = $input->getOption($key);
        if ($value === null) {
            return $defaultValue;
        }
        return (int)$value;
    }

    protected function tournamentTooOld(Tournament $tournament): bool
    {
        return $tournament->getCreatedDateTime()->getTimestamp() <= $this->borderTimestamp;
    }

    /**
     * @param Tournament $tournament
     * @param non-empty-list<PdfSubject> $subjects
     * @throws Exception
     */
    protected function createPdf(Tournament $tournament, array $subjects): void
    {
        try {
            $this->pdfService->createASyncOnDisk($tournament, $subjects, $this->queueService);
        } catch (Exception $exception) {
            // $this->showPlanning($tournament, $roundNumber, $competition->getReferees()->count());
            throw new Exception(
                'toernooi-id(' . ((string)$tournament->getId()) . ') => ' . $exception->getMessage(),
                E_ERROR
            );
        }
    }

    /**
     * @param InputInterface $input
     * @return non-empty-list<PdfSubject>
     * @throws Exception
     */
    protected function getSubjects(InputInterface $input): array
    {
        $inputSubjects = $input->getOption('subjects');
        $summedUpInputSubjects = (int)$inputSubjects;

        $filteredSubjects = PdfSubject::toFilteredArray($summedUpInputSubjects);
        if (count($filteredSubjects) === 0) {
            return PdfSubject::cases();
        }
        return $filteredSubjects;
    }

    /**
     * @param non-empty-list<PdfSubject> $subjects
     * @return non-empty-list<non-empty-list<PdfSubject>>
     */
    protected function toSubjectsLists(array $subjects): array
    {
        return array_map(function (PdfSubject $subject): array {
            return [$subject];
        }, $subjects);
    }

    protected function addStructureToLog(Tournament $tournament, Structure $structure): void
    {
        try {
            (new StructureOutput($this->getLogger()))->output($structure);
        } catch (Exception $exception) {
            $this->getLogger()->error('could not find structure for tournamentId ' . ((string)$tournament->getId()));
        }
    }
}
