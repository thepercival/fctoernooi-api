<?php

declare(strict_types=1);

namespace App\Commands\Pdf;

use App\Commands\Planning as PlanningCommand;
use App\Export\Pdf\DocumentFactory;
use App\Export\Pdf\DocumentFactory as PdfDocumentFactory;
use App\Export\PdfService;
use App\Export\PdfSubject;
use App\Mailer;
use App\QueueService\Pdf as PdfQueueService;
use App\QueueService\Pdf\CreateMessage as PdfCreateMessage;
use App\TmpService;
use FCToernooi\Tournament\Repository as TournamentRepository;
use Interop\Queue\Consumer;
use Interop\Queue\Message;
use Memcached;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Selective\Config\Configuration;
use Sports\Round\Number\Repository as RoundNumberRepository;
use Sports\Structure\Repository as StructureRepository;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Create extends PlanningCommand
{
    private string $customName = 'create-pdf';
    protected StructureRepository $structureRepos;
    protected RoundNumberRepository $roundNumberRepos;
    protected TournamentRepository $tournamentRepos;
    protected PdfService $pdfService;
    protected PdfQueueService $queueService;
    protected DocumentFactory $documentFactory;
    protected bool $showSuccessful = false;
    protected bool $disableThrowOnTimeout = false;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);

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
                $this->getLogLevel($input),
                $this->getStreamDef($input, $loggerName),
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
                $logMessage = 'creating pdf for tournamentid "' . $tournamentId . '"';
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
            $this->getLogger()->info('merging pdf for tournamentid "' . $tournamentId . '"');
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
}
