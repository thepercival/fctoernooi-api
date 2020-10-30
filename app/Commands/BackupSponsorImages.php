<?php

declare(strict_types=1);

namespace App\Commands;

use Psr\Container\ContainerInterface;
use App\Command;
use Selective\Config\Configuration;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use FCToernooi\Sponsor\Repository as SponsorRepository;

class BackupSponsorImages extends Command
{
    /**
     * @var SponsorRepository
     */
    protected $sponsorRepos;

    public function __construct(ContainerInterface $container)
    {
        $this->sponsorRepos = $container->get(SponsorRepository::class);
        parent::__construct($container->get(Configuration::class));
    }

    protected function configure()
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
                if (strpos($logoUrl, $apiUrl) === false) {
                    continue;
                }
                // $logoUrl = $settings["www"]["apiurl-localpath"];
                $logoLocalPath = str_replace($apiUrl, $this->config->getString('www.apiurl-localpath'), $logoUrl);
                if (!is_readable($logoLocalPath)) {
                    throw new \Exception("sponsorimage " . $logoLocalPath . " could not be found", E_ERROR);
                }

                $newPath = str_replace($path, $backupPath, $logoLocalPath);
                if (!copy($logoLocalPath, $newPath)) {
                    $this->logger->error("failed to copy  " . $logoLocalPath);
                }
            }
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
        }
        return 0;
    }
}
