<?php
declare(strict_types=1);

namespace App\Commands;

use App\Command;
use Psr\Container\ContainerInterface;
use Selective\Config\Configuration;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Listing extends Command
{
    /**
     * Listing constructor.
     * @param ContainerInterface $container
     * @param list<string> $commandKeys
     */
    public function __construct(protected ContainerInterface $container, private array $commandKeys)
    {
        /** @var Configuration $config */
        $config = $container->get(Configuration::class);
        parent::__construct($config);
    }

    protected function configure(): void
    {
        $this
            // the name of the command (the part after "bin/console")
            ->setName('app:list')
            // the short description shown while running "php bin/console list"
            ->setDescription('list the commands')
            // the full command description shown when running the command with
            // the "--help" option
            ->setHelp('list the commands');

        parent::configure();

        $this->addArgument('commandName', InputArgument::OPTIONAL, 'command-name');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $commandFilter = null;
        $commandName = $input->getArgument('commandName');
        if (is_string($commandName) && strlen($commandName) > 0) {
            $commandFilter = $commandName;
        }

        foreach ($this->commandKeys as $commandKey) {
            if ($commandFilter !== null && $commandKey !== $commandFilter) {
                continue;
            }
            /** @var Command $command */
            $command = $this->container->get($commandKey);
            echo $commandKey . " (" . $command->getDescription() . ")" . PHP_EOL;
            foreach ($command->getDefinition()->getArguments() as $argument) {
                echo "  " . $argument->getName() . " (" . $argument->getDescription() . ")" . PHP_EOL;
            }
            foreach ($command->getDefinition()->getOptions() as $option) {
                echo " --" . $option->getName() . " (" . $option->getDescription() . ")" . PHP_EOL;
            }
            echo PHP_EOL;
        }
        return 0;
    }
}
