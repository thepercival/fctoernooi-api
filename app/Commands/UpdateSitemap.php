<?php

declare(strict_types=1);

namespace App\Commands;

use FCToernooi\Tournament;
use Psr\Container\ContainerInterface;
use App\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Selective\Config\Configuration;
use FCToernooi\Tournament\Repository as TournamentRepository;

class UpdateSitemap extends Command
{
    /**
     * @var TournamentRepository
     */
    protected $tournamentRepos;

    public function __construct(ContainerInterface $container)
    {
        $this->tournamentRepos = $container->get(TournamentRepository::class);
        parent::__construct($container->get(Configuration::class));
    }

    protected function configure()
    {
        $this
            // the name of the command (the part after "bin/console")
            ->setName('app:update-sitemap')
            // the short description shown while running "php bin/console list"
            ->setDescription('Updates the sitemap')
            // the full command description shown when running the command with
            // the "--help" option
            ->setHelp('Updates the sitemap');
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->initLogger($input, 'cron-update-sitemap');
        try {
            $url = $this->config->getString('www.wwwurl');
            $distPath = $this->config->getString('www.wwwurl-localpath');

            $content = $url . PHP_EOL;
            $content .= $url . "user/register" . PHP_EOL;
            $content .= $url . "user/login" . PHP_EOL;

            $tournaments = $this->tournamentRepos->findAll();
            /** @var Tournament $tournament */
            foreach ($tournaments as $tournament) {
                if ($tournament->getPublic() === false) {
                    continue;
                }
                $content .= $url . $tournament->getId() . PHP_EOL;
            }
            file_put_contents($distPath . "sitemap.txt", $content);

            $robotsContent = "Sitemap: " . $url . "sitemap.txt";
            file_put_contents($distPath . "robots.txt", $robotsContent);

            // chmod ( $distPath . "sitemap.txt", 744 );
//            chown($distPath . "sitemap.txt", "coen");
//            chgrp($distPath . "sitemap.txt", "coen");
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
        }
        return 0;
    }
}
