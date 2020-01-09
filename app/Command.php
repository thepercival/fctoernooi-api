<?php

namespace App;

use Psr\Container\ContainerInterface;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Monolog\Processor\UidProcessor;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command as SymCommand;

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
     * @var string
     */
    protected $env;

    public function __construct(ContainerInterface $container, string $name)
    {
        $this->env = $container->get('settings')['environment'];
        $this->initLogger($container->get('settings')['logger'], $name);
        $this->initMailer($this->logger, $container->get('settings')['email']);
        parent::__construct();
    }

    protected function initLogger(array $loggerSettings, string $name)
    {
        $this->logger = new Logger($name);
        $processor = new UidProcessor();
        $this->logger->pushProcessor($processor);
        $path = $this->env === "development" ? 'php://stdout' : ($loggerSettings['path'] . $name . '.log');
        $handler = new StreamHandler($path, $loggerSettings['level']);
        $this->logger->pushHandler($handler);
    }

    protected function initMailer(LoggerInterface $logger, array $emailSettings)
    {
        $this->mailer = new Mailer(
            $logger,
            $emailSettings['from'],
            $emailSettings['fromname'],
            $emailSettings['admin']
        );
    }
}
