<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Response\ErrorResponse;
use Selective\Config\Configuration;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface as Middleware;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

/**
 * @api
 */
final class VersionMiddleware implements Middleware
{
    public function __construct(protected Configuration $config) {}

    #[\Override]
    public function process(Request $request, RequestHandler $handler): Response
    {
        if ($request->getMethod() === 'OPTIONS') {
            return $handler->handle($request);
        }
        $apiVersion = $request->getHeaderLine('X-Api-Version');
        $expectedVersion = $this->config->getString('apiVersion');
        if ($apiVersion !== $expectedVersion) {
            return new ErrorResponse('de app/website moet vernieuwd worden naar(v' . $expectedVersion . ') en is nu (v' . $apiVersion . '), ververs de pagina', 418);
        }
        return $handler->handle($request);
    }
}
