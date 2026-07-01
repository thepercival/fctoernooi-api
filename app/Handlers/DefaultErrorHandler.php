<?php

declare(strict_types=1);

namespace App\Handlers;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Log\LoggerInterface;
use Slim\Handlers\ErrorHandler;
use Slim\Interfaces\CallableResolverInterface;

/** @psalm-suppress PropertyNotSetInConstructor */
final class DefaultErrorHandler extends ErrorHandler
{
    /**
     * @param CallableResolverInterface $callableResolver
     * @param ResponseFactoryInterface $responseFactory
     * @param LoggerInterface|null $logger
     */
    public function __construct(
        CallableResolverInterface $callableResolver,
        ResponseFactoryInterface $responseFactory,
        ?LoggerInterface $logger = null
    ) {
        parent::__construct($callableResolver, $responseFactory, $logger);
    }

    #[\Override]
    protected function logError(string $error): void
    {
        header('Access-Control-Allow-Origin: *');
        parent::logError($error);
    }
}
