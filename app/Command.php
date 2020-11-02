<?php

declare(strict_types=1);

namespace App;

use Monolog\Handler\NativeMailerHandler;
use Psr\Container\ContainerInterface;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Monolog\Processor\UidProcessor;
use Psr\Log\LoggerInterface;
use Selective\Config\Configuration;
use Symfony\Component\Console\Command\Command as SymCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

class Command extends SymCommand
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
     * @var Configuration
     */
    protected $config;

    public function __construct(Configuration $config)
    {
        parent::__construct();
        $this->config = $config;
    }

    protected function configure()
    {
        $this->addOption('logtofile', null, InputOption::VALUE_NONE, 'logtofile?');
        $this->addOption('loglevel', null, InputOption::VALUE_OPTIONAL, '100');
    }

    protected function initLogger(InputInterface $input, string $name)
    {
        $loggerSettings = $this->config->getArray('logger');
        $logLevel = $loggerSettings['level'];
        if ($input->getOption('loglevel') !== null && strlen($input->getOption('loglevel')) > 0) {
            $logLevel = filter_var($input->getOption('loglevel'), FILTER_VALIDATE_INT);
        }

        $this->logger = new Logger($name);
        $processor = new UidProcessor();
        $this->logger->pushProcessor($processor);

        $path = $input->getOption('logtofile') ? ($loggerSettings['path'] . $name . '.log') : 'php://stdout';
        $handler = new StreamHandler($path, $logLevel);
        $this->logger->pushHandler($handler);

        if ($this->config->getString('environment') === 'production') {
            $emailSettings = $this->config->getArray('email');
            $this->logger->pushHandler(
                new NativeMailerHandler(
                    $emailSettings['admin'],
                    $this->getName() . " : error",
                    $emailSettings['from']
                )
            );
        }
    }
}
