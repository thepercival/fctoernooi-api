<?php

declare(strict_types=1);

namespace App\Middleware;

use FCToernooi\Tournament\Repository as TournamentRepository;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Routing\RouteContext;

class JsonCacheMiddleware implements MiddlewareInterface
{
    public const TournamentCacheIdPrefix = 'json-tournament-';
    public const StructureCacheIdPrefix = 'json-structure-';

    public function __construct(
        protected \Memcached $memcached,
        protected TournamentRepository $tournamentRepos
    ) {
    }

    public function process(Request $request, RequestHandler $handler): Response
    {
        if ($request->getMethod() !== 'PUT' && $request->getMethod() !== 'POST') {
            return $handler->handle($request);
        }

        $tournamentId = $this->getTournamentId($request);
        if ($tournamentId === null) {
            return $handler->handle($request);
        }

        $ignoreCacheReset = $request->getHeaderLine('X-Ignore-Cache-Reset');
        if ($ignoreCacheReset !== 'tournament') {
            $this->memcached->delete(self::TournamentCacheIdPrefix . $tournamentId);
        }
        if ($ignoreCacheReset !== 'structure') {
            $this->memcached->delete(self::StructureCacheIdPrefix . $tournamentId);
        }

        return $handler->handle($request);
    }

    private function getTournamentId(Request $request): string|null
    {
        $routeContext = RouteContext::fromRequest($request);
        $routingResults = $routeContext->getRoutingResults();

        $args = $routingResults->getRouteArguments();

        if (array_key_exists('tournamentId', $args) === false) {
            return null;
        }
        return $args['tournamentId'];
    }
}
