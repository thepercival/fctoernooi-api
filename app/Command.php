<?php

declare(strict_types=1);

namespace App;

use Exception;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Monolog\Processor\UidProcessor;
use Psr\Log\LoggerInterface;
use Selective\Config\Configuration;
use Symfony\Component\Console\Command\Command as SymCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

class Command extends SymCommand
{
    protected LoggerInterface|null $logger = null;
    protected Mailer|null $mailer = null;

    public function __construct(protected Configuration $config)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('logtofile', null, InputOption::VALUE_NONE, 'logtofile?');
        $this->addOption('loglevel', null, InputOption::VALUE_OPTIONAL, '' . Logger::INFO);
    }

    protected function getLogger(): LoggerInterface
    {
        if ($this->logger === null) {
            throw new Exception('define logger first', E_ERROR);
        }
        return $this->logger;
    }

    protected function initLogger(
        int $logLevel,
        string $streamDef,
        string $name,
        MailHandler|null $mailHandler = null
    ): Logger {
        $this->logger = new Logger($name);
        $processor = new UidProcessor();
        $this->logger->pushProcessor($processor);

        $handler = new StreamHandler($streamDef, $logLevel);
        $this->logger->pushHandler($handler);

        if ($mailHandler === null) {
            $mailHandler = $this->getMailHandler();
        }
        if ($this->mailer !== null) {
            $mailHandler->setMailer($this->mailer);
        }
        $this->logger->pushHandler($mailHandler);
        return $this->logger;
    }

    protected function getMailHandler(
        string|null $subject = null,
        int|null $mailLogLevel = null
    ): MailHandler {
        if ($subject === null) {
            $subject = ((string)$this->getName()) . ' : error';
        }
        if ($mailLogLevel === null) {
            $mailLogLevel = Logger::ERROR;
        }
        $toEmailAddress = $this->config->getString('email.admin');
        $fromEmailAddress = $this->config->getString('email.from');
        return new MailHandler($toEmailAddress, $subject, $fromEmailAddress, $mailLogLevel);
    }

    protected function getLogLevel(InputInterface $input, int|null $defaultLogLevel = null): int
    {
        if ($defaultLogLevel === null) {
            $loggerSettings = $this->config->getArray('logger');
            $defaultLogLevel = $loggerSettings['level'];
        }

        $logLevelParam = $input->getOption('loglevel');
        if (is_string($logLevelParam) && strlen($logLevelParam) > 0) {
            $logLevelTmp = filter_var($logLevelParam, FILTER_VALIDATE_INT);
            if ($logLevelTmp !== false) {
                return $logLevelTmp;
            }
        }
        return $defaultLogLevel;
    }

    protected function getStreamDef(InputInterface $input, string|null $fileName = null): string
    {
        $logToFile = $input->getOption('logtofile');
        $logToFile = is_bool($logToFile) ? $logToFile : false;
        if ($logToFile === false) {
            return 'php://stdout';
        }
        $loggerSettings = $this->config->getArray('logger');
        return ($loggerSettings['path'] . $fileName . '.log');
    }
}
