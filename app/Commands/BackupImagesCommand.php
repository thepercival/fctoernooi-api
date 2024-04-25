<?php

declare(strict_types=1);

namespace App\Commands;

use App\Command;
use App\ImageService;
use App\Mailer;
use Doctrine\ORM\EntityManagerInterface;
use FCToernooi\Competitor;
use FCToernooi\Sponsor;
use FCToernooi\Sponsor\Repository as SponsorRepository;
use FCToernooi\Competitor\Repository as CompetitorRepository;
use Psr\Container\ContainerInterface;
use Selective\Config\Configuration;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class BackupImagesCommand extends Command
{
    private string $customName = 'backup-images';
    protected SponsorRepository $sponsorRepos;
    protected CompetitorRepository $competitorRepos;
    protected EntityManagerInterface|null $entityManager;

    public function __construct(ContainerInterface $container)
    {
        /** @var CompetitorRepository $competitorRepos */
        $competitorRepos = $container->get(CompetitorRepository::class);
        $this->competitorRepos = $competitorRepos;

        /** @var SponsorRepository $sponsorRepos */
        $sponsorRepos = $container->get(SponsorRepository::class);
        $this->sponsorRepos = $sponsorRepos;

        /** @var Mailer|null $mailer */
        $mailer = $container->get(Mailer::class);
        $this->mailer = $mailer;

        /** @var EntityManagerInterface|null $entityManager */
        $entityManager = $container->get(EntityManagerInterface::class);
        $this->entityManager = $entityManager;

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
            ->setDescription('Backups the sponsorimages')
            // the full command description shown when running the command with
            // the "--help" option
            ->setHelp('Backups the sponsorimages');

        $this->addOption('sync-db-with-disk', null, InputOption::VALUE_NONE, 'sync-db-with-disk');

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $syncDbWithDisk = $input->getOption('sync-db-with-disk');
        $entityManager = $syncDbWithDisk === true ? $this->entityManager : null;

        $loggerName = 'command-' . $this->customName;
        $logger = $this->initLogger(
            $this->getLogLevelFromInput($input),
            $this->getMailLogFromInput($input),
            $this->getPathOrStdOutFromInput($input, $loggerName),
            $loggerName,
        );
        $imageService = new ImageService($this->config, $logger);
        try {

            $imgPath = $this->config->getString('www.apiurl-localpath') . 'images/';
            if( file_exists($imgPath) === false ) {
                throw new \Exception("imgpath " . $imgPath . " not writable", E_ERROR);
            }

            $backupPath = $this->config->getString('images.backuppath');
            if( file_exists($backupPath) === false ) {
                if( !mkdir($backupPath) ) {
                    throw new \Exception("backuppath " . $backupPath . " could not be created", E_ERROR);
                }
            }
            if (!is_writable($backupPath)) {
                throw new \Exception("backuppath " . $backupPath . " is not writable", E_ERROR);
            }
            $this->backupSponsors($imageService, $backupPath . '/' . Sponsor::IMG_FOLDER . '/', $entityManager);
            $this->backupCompetitors($imageService, $backupPath . '/' . Competitor::IMG_FOLDER . '/', $entityManager);
        } catch (\Exception $exception) {
            $logger->error($exception->getMessage());
        }
        return 0;
    }

    protected function backupSponsors(ImageService $imageService, string $backupPath, EntityManagerInterface|null $syncDbWithDisk): void
    {
        try {
            if( file_exists($backupPath) === false ) {
                if( !mkdir($backupPath) ) {
                    throw new \Exception("backuppath " . $backupPath . " could not be created", E_ERROR);
                }
            }
            if (!is_writable($backupPath)) {
                throw new \Exception("backuppath " . $backupPath . " is not writable", E_ERROR);
            }

            $sponsors = $this->sponsorRepos->findAll();
            foreach ($sponsors as $sponsor) {
                $logoExtension = $sponsor->getLogoExtension();
                if ($logoExtension === null ) {
                    continue;
                }

                $imageService->backupImages($sponsor, $syncDbWithDisk);
            }
        } catch (\Exception $exception) {
            if ($this->logger !== null) {
                $this->logger->error($exception->getMessage());
            }
        }
    }

    protected function backupCompetitors(ImageService $imageService, string $backupPath, EntityManagerInterface|null $syncDbWithDisk): void
    {
        try {
            if( file_exists($backupPath) === false ) {
                if( !mkdir($backupPath) ) {
                    throw new \Exception("backuppath " . $backupPath . " could not be created", E_ERROR);
                }
            }
            if (!is_writable($backupPath)) {
                throw new \Exception("backuppath " . $backupPath . " is not writable", E_ERROR);
            }

            $competitors = $this->competitorRepos->findAll();
            foreach ($competitors as $competitor) {
                $logoExtension = $competitor->getLogoExtension();
                if ($logoExtension === null ) {
                    continue;
                }

                $imageService->backupImages($competitor, $syncDbWithDisk);
            }
        } catch (\Exception $exception) {
            if ($this->logger !== null) {
                $this->logger->error($exception->getMessage());
            }
        }
    }
}
