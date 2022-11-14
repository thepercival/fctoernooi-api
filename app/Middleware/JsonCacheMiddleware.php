<?php

declare(strict_types=1);

namespace App\Middleware;

use FCToernooi\CacheService;
use FCToernooi\Tournament\Repository as TournamentRepository;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Selective\Config\Configuration;
use Slim\Routing\RouteContext;

class JsonCacheMiddleware implements MiddlewareInterface
{
    private CacheService $cacheService;

    public function __construct(
        \Memcached $memcached,
        protected TournamentRepository $tournamentRepos,
        protected Configuration $config
    ) {
        $this->cacheService = new CacheService($memcached, $config->getString('namespace'));
    }

    public function process(Request $request, RequestHandler $handler): Response
    {
        if ($request->getMethod() !== 'PUT' && $request->getMethod() !== 'POST' && $request->getMethod() !== 'DELETE') {
            return $handler->handle($request);
        }

        $tournamentId = $this->getTournamentId($request);
        if ($tournamentId === null) {
            return $handler->handle($request);
        }

        $ignoreCacheReset = $request->getHeaderLine('X-Ignore-Cache-Reset');
        if ($ignoreCacheReset === '') {
            $this->cacheService->resetTournament($tournamentId);
            $this->cacheService->resetStructure($tournamentId);
        }
        else if ($ignoreCacheReset === 'tournament') {
            $this->cacheService->resetStructure($tournamentId);
        }
        else if ($ignoreCacheReset !== 'structure') {
            $this->cacheService->resetTournament($tournamentId);
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
