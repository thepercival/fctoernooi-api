<?php

namespace App\Commands;

use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use App\Settings\Www as WwwSettings;
use App\Mailer;
use FCToernooi\Tournament\Repository as TournamentRepository;

class UpdateSitemap extends Command
{
    /**
     * @var LoggerInterface
     */
    protected $logger;
    /**
     * @var Mailer
     */
    protected $mailer;
    /**
     * @var TournamentRepository
     */
    protected $tournamentRepos;
    /**
     * @var WwwSettings
     */
    protected $wwwSettings;
    /**
     * @var string
     */
    protected $env;

    public function __construct(ContainerInterface $container)
    {
        $this->tournamentRepos = $container->get(TournamentRepository::class);
        $this->logger = $container->get(LoggerInterface::class);
        $this->mailer = $container->get(Mailer::class);
        $this->wwwSettings = $container->get(WwwSettings::class);
        $this->env = $container->get('settings')['environment'];
        parent::__construct();
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
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $url = $this->wwwSettings->getWwwUrl();
            $distPath = $this->wwwSettings->getWwwUrlLocalpath();

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
            if ($this->env === 'production') {
                $this->mailer->sendToAdmin("error creating sitemap", $e->getMessage());
                $this->logger->error("GENERAL ERROR: " . $e->getMessage());
            } else {
                echo $e->getMessage() . PHP_EOL;
            }
        }
        return 0;
    }
}
