<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Routing\RouteContext;

/**
 * CORS middleware.
 *
 * Allows CORS preflight from any domain.
 */
final class CorsMiddleware implements MiddlewareInterface
{
    /**
     * @var string
     */
    private $origin;

    public function __construct(string $origin)
    {
        $this->origin = $origin;
    }

    #[\Override]
    public function process(Request $request, RequestHandler $handler): Response
    {
        if ($request->getMethod() === 'OPTIONS') {
            $response = new \Slim\Psr7\Response();
        } else {
            $response = $handler->handle($request);
        }

        $origin = $this->origin;
        if (str_ends_with($origin, '/')) {
            $origin = substr($origin, 0, -1);
        }

        try {
            $routeContext = RouteContext::fromRequest($request);
            $routingResults = $routeContext->getRoutingResults();
            $methods = $routingResults->getAllowedMethods();
        } catch (\RuntimeException) {
            $methods = [];
        }

        if (count($methods) === 0) {
            $methods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'];
        }

        return $response
            ->withHeader('Access-Control-Allow-Credentials', 'true')
            ->withHeader('Access-Control-Allow-Origin', $origin)
            ->withHeader(
                'Access-Control-Allow-Headers',
                'X-Requested-With, Content-Type, Accept, Origin, Authorization, X-Api-Version, x-api-version'
            )
            ->withHeader('Access-Control-Allow-Methods', implode(',', $methods))
            ->withHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->withAddedHeader('Cache-Control', 'post-check=0, pre-check=0')
            ->withHeader('Pragma', 'no-cache');
    }
}
