<?php

declare(strict_types=1);

namespace App\Commands;

use App\Command;
use FCToernooi\Tournament;
use FCToernooi\Tournament\Repository as TournamentRepository;
use Psr\Container\ContainerInterface;
use Selective\Config\Configuration;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class UpdateSitemapCommand extends Command
{
    private string $customName = 'update-sitemap';
    protected TournamentRepository $tournamentRepos;

    public function __construct(ContainerInterface $container)
    {
        /** @var TournamentRepository $tournamentRepos */
        $tournamentRepos = $container->get(TournamentRepository::class);
        $this->tournamentRepos = $tournamentRepos;
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
            ->setDescription('Updates the sitemap')
            // the full command description shown when running the command with
            // the "--help" option
            ->setHelp('Updates the sitemap');
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $loggerName = 'command-' . $this->customName;
            $this->initLogger(
                $this->getLogLevel($input),
                $this->getMailLog($input),
                $this->getPathOrStdOut($input, $loggerName),
                $loggerName,
            );

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
                $content .= $url . ((string)$tournament->getId()) . PHP_EOL;
            }
            file_put_contents($distPath . "sitemap.txt", $content);

            $robotsContent = "Sitemap: " . $url . "sitemap.txt";
            file_put_contents($distPath . "robots.txt", $robotsContent);

            // chmod ( $distPath . "sitemap.txt", 744 );
//            chown($distPath . "sitemap.txt", "coen");
//            chgrp($distPath . "sitemap.txt", "coen");
        } catch (\Exception $exception) {
            if ($this->logger !== null) {
                $this->logger->error($exception->getMessage());
            }
        }
        return 0;
    }
}
