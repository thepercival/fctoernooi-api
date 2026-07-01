<?php

declare(strict_types=1);

namespace App;

use Exception;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;
use Monolog\Level;
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

    #[\Override]
    protected function configure(): void
    {
        $this->addOption('logtofile', null, InputOption::VALUE_NONE, 'logtofile?');
        $this->addOption('loglevel', null, InputOption::VALUE_OPTIONAL, Level::Info->name);
        $this->addOption('maillog', null, InputOption::VALUE_NONE, 'maillog');
    }

    protected function getLogger(): LoggerInterface
    {
        if ($this->logger === null) {
            throw new Exception('define logger first', E_ERROR);
        }
        return $this->logger;
    }

    protected function initLogger(
        Level $logLevel,
        bool $mailLog,
        string $pathOrStdOut,
        string $name,
        MailHandler|null $mailHandler = null
    ): Logger {
        $this->logger = new Logger($name);
        $processor = new UidProcessor();
        $this->logger->pushProcessor($processor);

        // Format Line Start //////////////////////////

        // the default date format is "Y-m-d\TH:i:sP"
//        $dateFormat = "Y n j, g:i a";

        // the default output format is "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n"
        // we now change the default output format according to our needs.
        $messageFormat = $logLevel === Level::Debug ? 'info': 'default';
        if( $messageFormat === 'info' ) {
            $output = "%message% %context% %extra%\n";
        } else {
            $output = "%datetime% > %level_name% > %message% %context% %extra%\n";
        }
        // finally, create a formatter
        $formatter = new LineFormatter($output, null/*$dateFormat*/);
        // Create a handler
        $streamHandler = new StreamHandler($pathOrStdOut, $logLevel);
        $streamHandler->setFormatter($formatter);
        $this->logger->pushHandler($streamHandler);
        // Format Line End //////////////////////////

        if ($mailHandler === null) {
            $mailHandler = $this->getMailHandler();
        }
        if ($this->mailer !== null && $mailLog) {
            $mailHandler->setMailer($this->mailer);
        }
        $this->logger->pushHandler($mailHandler);
        return $this->logger;
    }

    protected function getMailHandler(
        string|null $subject = null,
        Level|null $mailLogLevel = null
    ): MailHandler {
        if ($subject === null) {
            $subject = ((string)$this->getName()) . ' : error';
        }
        if ($mailLogLevel === null) {
            $mailLogLevel = Level::Error;
        }
        $toEmailAddress = $this->config->getString('email.admin');
        $fromEmailAddress = $this->config->getString('email.from');
        return new MailHandler($toEmailAddress, $subject, $fromEmailAddress, $mailLogLevel);
    }

    protected function getLogLevelFromInput(InputInterface $input, Level|null $defaultLogLevel = null): Level
    {
        $logLevelNameParam = $input->getOption('loglevel');
        if (is_string($logLevelNameParam) && strlen($logLevelNameParam) > 0) {
            try {
                /** @psalm-suppress ArgumentTypeCoercion */
                return Level::fromName($logLevelNameParam);
            } catch (\UnhandledMatchError $e) {
                $this->getLogger()->warning("invalid parameter loglevel: {$logLevelNameParam} : {$e->getMessage()}");
            }
        }
        if ($defaultLogLevel !== null) {
            return $defaultLogLevel;
        }
        $loggerSettings = $this->config->getArray('logger');
        return $loggerSettings['level'];
    }

    protected function getPathOrStdOutFromInput(InputInterface $input, string|null $fileName = null): string
    {
        $logToFile = $input->getOption('logtofile');
        $logToFile = is_bool($logToFile) ? $logToFile : false;
        if ($logToFile === false) {
            return 'php://stdout';
        }
        $loggerSettings = $this->config->getArray('logger');
        return ($loggerSettings['path'] . $fileName . '.log');
    }

    protected function getMailLogFromInput(InputInterface $input): bool
    {
        return $this->getBooleanFromInput($input, 'maillog');
    }

    protected function getBooleanFromInput(InputInterface $input, string $key): bool
    {
        $logToFile = $input->getOption($key);
        return is_bool($logToFile) ? $logToFile : false;
    }
}
