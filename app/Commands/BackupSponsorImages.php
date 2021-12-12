<?php

declare(strict_types=1);

namespace App\Commands;

use App\Command;
use FCToernooi\Sponsor\Repository as SponsorRepository;
use Psr\Container\ContainerInterface;
use Selective\Config\Configuration;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class BackupSponsorImages extends Command
{
    /**
     * @var SponsorRepository
     */
    protected $sponsorRepos;

    public function __construct(ContainerInterface $container)
    {
        $this->sponsorRepos = $container->get(SponsorRepository::class);
        /** @var Configuration $config */
        $config = $container->get(Configuration::class);
        parent::__construct($config);
    }

    protected function configure(): void
    {
        $this
            // the name of the command (the part after "bin/console")
            ->setName('app:backup-sponsorimages')
            // the short description shown while running "php bin/console list"
            ->setDescription('Backups the sponsorimages')
            // the full command description shown when running the command with
            // the "--help" option
            ->setHelp('Backups the sponsorimages');
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->initLogger($input, 'cron-backup-sponsorimages');
        $path = $this->config->getString('www.apiurl-localpath') . $this->config->getString(
            'images.sponsors.pathpostfix'
        );
        $backupPath = $this->config->getString('images.sponsors.backuppath') . $this->config->getString(
            'images.sponsors.pathpostfix'
        );
        try {
            if (!is_writable($backupPath)) {
                throw new \Exception("backuppath " . $backupPath . " is not writable", E_ERROR);
            }

            $apiUrl = $this->config->getString('www.apiurl');
            $sponsors = $this->sponsorRepos->findAll();
            foreach ($sponsors as $sponsor) {
                $logoUrl = $sponsor->getLogoUrl();
                if ($logoUrl === null || strpos($logoUrl, $apiUrl) === false) {
                    continue;
                }
                // $logoUrl = $settings["www"]["apiurl-localpath"];
                $logoLocalPath = str_replace($apiUrl, $this->config->getString('www.apiurl-localpath'), $logoUrl);
                if (!is_readable($logoLocalPath)) {
                    throw new \Exception("sponsorimage " . $logoLocalPath . " could not be found", E_ERROR);
                }

                $newPath = str_replace($path, $backupPath, $logoLocalPath);
                if (!copy($logoLocalPath, $newPath)) {
                    $this->getLogger()->error("failed to copy  " . $logoLocalPath);
                }
            }
        } catch (\Exception $exception) {
            if ($this->logger !== null) {
                $this->logger->error($exception->getMessage());
            }
        }
        return 0;
    }
}
