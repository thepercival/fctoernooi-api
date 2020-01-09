<?php

namespace App\Commands;

use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use App\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use App\Settings\Www as WwwSettings;
use App\Settings\Image as ImageSettings;
use App\Mailer;
use FCToernooi\Sponsor\Repository as SponsorRepository;

class BackupSponsorImages extends Command
{
    /**
     * @var SponsorRepository
     */
    protected $sponsorRepos;
    /**
     * @var WwwSettings
     */
    protected $wwwSettings;
    /**
     * @var ImageSettings
     */
    protected $imageSettings;

    public function __construct(ContainerInterface $container)
    {
        $this->sponsorRepos = $container->get(SponsorRepository::class);
        $this->wwwSettings = $container->get(WwwSettings::class);
        $this->imageSettings = $container->get(ImageSettings::class);

        parent::__construct($container, 'cron-backup-sponsorimages');
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
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $path = $this->wwwSettings->getApiUrlLocalpath() . $this->imageSettings->getSponsorsPathPostfix();
        $backupPath = $this->imageSettings->getSponsorsBackupPath() . $this->imageSettings->getSponsorsPathPostfix();
        try {
            if (!is_writable($backupPath)) {
                throw new \Exception("backuppath " . $backupPath . " is not writable", E_ERROR);
            }

            $apiUrl = $this->wwwSettings->getApiUrl();
            $sponsors = $this->sponsorRepos->findAll();
            foreach ($sponsors as $sponsor) {
                $logoUrl = $sponsor->getLogoUrl();
                if (strpos($logoUrl, $apiUrl) === false) {
                    continue;
                }
                // $logoUrl = $settings["www"]["apiurl-localpath"];
                $logoLocalPath = str_replace($apiUrl, $this->wwwSettings->getApiUrlLocalpath(), $logoUrl);
                if (!is_readable($logoLocalPath)) {
                    throw new \Exception("sponsorimage " . $logoLocalPath . " could not be found", E_ERROR);
                }

                $newPath = str_replace($path, $backupPath, $logoLocalPath);
                if (!copy($logoLocalPath, $newPath)) {
                    $this->logger->error("failed to copy  " . $logoLocalPath);
                }
            }
        } catch (\Exception $e) {
            if ($this->env === 'production') {
                $this->mailer->sendToAdmin("error creating sitemap", $e->getMessage());
                $this->logger->error($e->getMessage());
            } else {
                echo $e->getMessage() . PHP_EOL;
            }
        }
        return 0;
    }
}
