<?php

namespace App\Commands;

use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use App\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Selective\Config\Configuration;
use App\Mailer;
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
        $this->initMailer($this->logger);
        try {
            $url = $this->config->getString('www.wwwurl');
            $distPath = $this->config->getString('www.wwwurl-localpath');

            $content = $url . PHP_EOL;
            $content .= $url . "user/register/" . PHP_EOL;
            $content .= $url . "user/login/" . PHP_EOL;

            $tournaments = $this->tournamentRepos->findAll();
            foreach ($tournaments as $tournament) {
                $content .= $url . $tournament->getId() . PHP_EOL;
            }
            file_put_contents($distPath . "sitemap.txt", $content);
            // chmod ( $distPath . "sitemap.txt", 744 );
            chown($distPath . "sitemap.txt", "coen");
            chgrp($distPath . "sitemap.txt", "coen");
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            if ($this->config->getString('environment') === 'production') {
                $this->mailer->sendToAdmin("error creating sitemap", $e->getMessage());
            }
        }
        return 0;
    }
}
