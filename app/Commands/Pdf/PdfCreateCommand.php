<?php

declare(strict_types=1);

namespace App\Commands\Pdf;

use App\Command;
use App\Export\Pdf\DocumentFactory;
use App\Export\Pdf\DocumentFactory as PdfDocumentFactory;
use App\Export\PdfService;
use App\Export\PdfSubject;
use App\Mailer;
use App\QueueService\Pdf as PdfQueueService;
use App\QueueService\Pdf\CreateMessage as PdfCreateMessage;
use App\TmpService;
use Doctrine\ORM\EntityManagerInterface;
use FCToernooi\Tournament\Repository as TournamentRepository;
use Interop\Queue\Consumer;
use Interop\Queue\Message;
use Memcached;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Selective\Config\Configuration;
use Sports\Competition;
use Sports\Round\Number as RoundNumber;
use Sports\Round\Number\Repository as RoundNumberRepository;
use Sports\Structure\Repository as StructureRepository;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class PdfCreateCommand extends Command
{
    private string $customName = 'create-pdf';
    protected StructureRepository $structureRepos;
    protected RoundNumberRepository $roundNumberRepos;
    protected TournamentRepository $tournamentRepos;
    protected PdfService $pdfService;
    protected PdfQueueService $queueService;
    protected DocumentFactory $documentFactory;
    protected EntityManagerInterface $entityManager;
    protected bool $showSuccessful = false;
    protected bool $disableThrowOnTimeout = false;

    public function __construct(ContainerInterface $container)
    {
        /** @var Configuration $config */
        $config = $container->get(Configuration::class);

        parent::__construct($config);

        /** @var Mailer|null $mailer */
        $mailer = $container->get(Mailer::class);
        $this->mailer = $mailer;

        /** @var StructureRepository $structureRepos */
        $structureRepos = $container->get(StructureRepository::class);
        $this->structureRepos = $structureRepos;

        /** @var RoundNumberRepository $roundNumberRepos */
        $roundNumberRepos = $container->get(RoundNumberRepository::class);
        $this->roundNumberRepos = $roundNumberRepos;

        /** @var TournamentRepository $tournamentRepos */
        $tournamentRepos = $container->get(TournamentRepository::class);
        $this->tournamentRepos = $tournamentRepos;

        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(EntityManagerInterface::class);
        $this->entityManager = $entityManager;

        /** @var Memcached $memcached */
        $memcached = $container->get(Memcached::class);
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

        $this->documentFactory = new DocumentFactory($config);
    }

    protected function configure(): void
    {
        $this
            // the name of the command (the part after "bin/console")
            ->setName('app:' . $this->customName)
            // the short description shown while running "php bin/console list"
            ->setDescription('Creates the pdf from the inputs')
            // the full command description shown when running the command with
            // the "--help" option
            ->setHelp('Creates the pdf from the inputs');
        parent::configure();

        $this->addOption('singleRun', null, InputOption::VALUE_NONE, 'not waiting..');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $loggerName = 'command-' . $this->customName;
            $this->initLogger(
                $this->getLogLevelFromInput($input),
                $this->getMailLogFromInput($input),
                $this->getPathOrStdOutFromInput($input, $loggerName),
                $loggerName,
            );
            $this->getLogger()->info('starting command app:pdf-create');

            $this->removeOldFiles();

            $queueService = new PdfQueueService($this->config->getArray('queue'));

            $singleRun = $input->getOption('singleRun');
            $singleRun = is_bool($singleRun) ? $singleRun : false;
            $timeoutInSeconds = $singleRun ? 1 : 240;
            $queueService->receive($this->getReceiver($queueService), $timeoutInSeconds);
        } catch (\Exception $exception) {
            if ($this->logger !== null) {
                $this->logger->error($exception->getMessage());
            }
        }
        return 0;
    }

    protected function getReceiver(PdfQueueService $queueService): callable
    {
        return function (Message $message, Consumer $consumer) use ($queueService): void {
            // process message
            try {
                $createMessage = $this->getMessage($message->getBody());
                $tournamentId = (string)$createMessage->getTournament()->getId();
                $logMessage = 'creating pdf for tournamentId "' . $tournamentId . '"';
                $logMessage .= ' with subject "' . $createMessage->getSubject()->name . '"';
                $this->getLogger()->info($logMessage);
                $this->createPdf($createMessage);

                $consumer->acknowledge($message);
            } catch (\Exception $exception) {
                if ($this->logger !== null) {
                    $this->logger->error($exception->getMessage());
                }
                $consumer->reject($message);
            }
        };
    }

    protected function getMessage(string $messageContent): PdfCreateMessage
    {
        /** @var object $content */
        $content = json_decode($messageContent);
        $tournament = null;
        if (property_exists($content, 'tournamentId')) {
            $tournament = $this->tournamentRepos->find((int)$content->tournamentId);
        }

        if (!property_exists($content, 'fileName')) {
            throw new \Exception('incorrect input params for queue-pdf-command', E_ERROR);
        }

        if ($tournament === null ||
            !property_exists($content, 'totalNrOfSubjects') || !property_exists($content, 'subject')
        ) {
            throw new \Exception('incorrect input params for queue-pdf-command', E_ERROR);
        }

        $subject = PdfSubject::from((int)$content->subject);
        $totalNrOfSubjects = (int)$content->totalNrOfSubjects;

        return new PdfCreateMessage($tournament, $content->fileName, $subject, $totalNrOfSubjects);
    }

    protected function createPdf(PdfCreateMessage $message): void
    {
        $time_start = microtime(true);
        $tournament = $message->getTournament();
        $this->refreshCompetition($tournament->getCompetition());
        $tournamentId = (string)$tournament->getId();
        $structure = $this->structureRepos->getStructure($tournament->getCompetition());
        $progressPerSubject = $this->pdfService->getProgressPerSubject($message->getTotalNrOfSubjects());
        $subject = $message->getSubject();
        $progress = $this->pdfService->getProgress($tournamentId);
        $pdf = $this->documentFactory->createSubject(
            $tournament,
            $structure,
            $subject,
            $progress,
            $progressPerSubject
        );
        $path = $this->pdfService->getTmpSubjectPath($tournamentId, $subject);
        $pdf->save($path);
        $duration = round(microtime(true) - $time_start, 1);
        $this->getLogger()->info('     executed in ' . $duration . ' seconds');

        if ($this->pdfService->creationCompleted($progress->getProgress())) {
            $this->getLogger()->info('merging pdf for tournamentId "' . $tournamentId . '"');
            $time_start = microtime(true);
            $this->pdfService->mergePdfs($tournament, $message->getFileName());
            $duration = round(microtime(true) - $time_start, 1);
            $this->getLogger()->info('     executed in ' . $duration . ' seconds');
        }
    }

    private function removeOldFiles(): void
    {
        if (random_int(1, 25) !== 5) {
            return;
        }
        $this->getLogger()->info('removing pdfs older than an hour');
        $tmpService = new TmpService();
        $expireDateTime = (new \DateTimeImmutable())->modify('-1 hours');
        $tmpService->removeOldFiles($this->pdfService->getTmpDir(), $expireDateTime);
        $tmpService->removeOldFiles($this->pdfService->getPublicDir(), $expireDateTime);
    }

    protected function getRoundNumber(Competition $competition): RoundNumber
    {
        $roundNumberAsValue = 1;
        $structure = $this->structureRepos->getStructure($competition);
        $roundNumber = $structure->getRoundNumber($roundNumberAsValue);
        if ($roundNumber === null) {
            throw new \Exception(
                "roundnumber " . $roundNumberAsValue . " not found for competitionid " . ((string)$competition->getId()),
                E_ERROR
            );
        }
        return $roundNumber;
    }

    protected function refreshCompetition(Competition $competition): void
    {
        $this->entityManager->refresh($competition);
        foreach ($competition->getSports() as $sport) {
            $this->entityManager->refresh($sport);
        }
        $roundNumber = $this->getRoundNumber($competition);
        $this->refreshRoundNumber($roundNumber);
    }

    protected function refreshRoundNumber(RoundNumber $roundNumber): void
    {
        $this->entityManager->refresh($roundNumber);

        $this->entityManager->refresh($roundNumber);
        foreach ($roundNumber->getRounds() as $round) {
            $this->entityManager->refresh($round);
            foreach ($round->getPoules() as $poule) {
                $this->entityManager->refresh($poule);
//                foreach ($poule->getAgainstGames() as $game) {
//                    $this->entityManager->refresh($game);
//                }
            }
        }
        $planningConfig =$roundNumber->getPlanningConfig();
        if ($planningConfig !== null) {
            $this->entityManager->refresh($planningConfig);
        }
        foreach ($roundNumber->getValidGameAmountConfigs() as $gameAmountConfig) {
            $this->entityManager->refresh($gameAmountConfig);
        }

        $nextRoundNumber = $roundNumber->getNext();
        if ($nextRoundNumber !== null) {
            $this->refreshRoundNumber($nextRoundNumber);
        }
    }
}
