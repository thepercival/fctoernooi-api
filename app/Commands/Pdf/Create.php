<?php

declare(strict_types=1);

namespace App\Commands\Pdf;

use App\Commands\Planning as PlanningCommand;
use App\Export\Pdf\DocumentFactory;
use App\Export\Pdf\DocumentFactory as PdfDocumentFactory;
use App\Export\PdfService;
use App\Export\PdfSubject;
use App\Mailer;
use App\QueueService;
use App\QueueService\Pdf as PdfQueueService;
use App\TmpService;
use FCToernooi\CacheService;
use FCToernooi\Tournament;
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
use Symfony\Component\Console\Output\OutputInterface;

class Create extends PlanningCommand
{
    protected StructureRepository $structureRepos;
    protected RoundNumberRepository $roundNumberRepos;
    protected TournamentRepository $tournamentRepos;
    protected PdfService $pdfService;
    protected PdfQueueService $queueService;
    protected DocumentFactory $documentFactory;
    protected bool $showSuccessful = false;
    protected bool $disableThrowOnTimeout = false;
    private CacheService $cacheService;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);

        /** @var Mailer|null $mailer */
        $mailer = $container->get(Mailer::class);
        $this->mailer = $mailer;

        /** @var Memcached $memcached */
        $memcached = $container->get(Memcached::class);
        $this->cacheService = new CacheService($memcached);

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
            ->setName('app:create-pdf')
            // the short description shown while running "php bin/console list"
            ->setDescription('Creates the pdf from the inputs')
            // the full command description shown when running the command with
            // the "--help" option
            ->setHelp('Creates the pdf from the inputs');
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $this->initLogger(
                $this->getLogLevel($input),
                $this->getStreamDef($input),
                'command-pdf-create.log'
            );
            $this->getLogger()->info('starting command app:pdf-create');

            $queueService = new PdfQueueService($this->config->getArray('queue'));

            $timeoutInSeconds = 240;
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
                $eventPriority = $message->getHeader('priority') ?? QueueService::MAX_PRIORITY;
                $this->getLogger()->info('------ EXECUTING WITH PRIORITY ' . $eventPriority . ' ------');

                /** @var object $content */
                $content = json_decode($message->getBody());
                $tournament = null;
                if (property_exists($content, 'tournamentId')) {
                    $tournament = $this->tournamentRepos->find((int)$content->tournamentId);
                }
                $subject = null;
                if (property_exists($content, 'subject')) {
                    $subject = PdfSubject::from((int)$content->subject);
                }
                $hash = null;
                if (property_exists($content, 'hash')) {
                    $hash = (string)$hash;
                }
                if ($tournament === null || $subject === null || $hash === null) {
                    throw new \Exception('incorrect input params for queue-pdf-command', E_ERROR);
                }

                $this->createPdf($tournament, $subject, $hash);

                $consumer->acknowledge($message);
            } catch (\Exception $exception) {
                if ($this->logger !== null) {
                    $this->logger->error($exception->getMessage());
                }
                $consumer->reject($message);
            }
        };
    }

    protected function createPdf(Tournament $tournament, PdfSubject $subject, string $hash): void
    {
        $structure = $this->structureRepos->getStructure($tournament->getCompetition());
        $pdf = $this->documentFactory->createDocument($tournament, $structure, $subject);
        // werk progress level bij
    }
}
