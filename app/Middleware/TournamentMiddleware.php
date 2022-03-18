<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Response\ForbiddenResponse as ForbiddenResponse;
use FCToernooi\Tournament\Repository as TournamentRepository;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Routing\RouteContext;

class TournamentMiddleware implements MiddlewareInterface
{
    public function __construct(protected TournamentRepository $tournamentRepos)
    {
    }

    public function process(Request $request, RequestHandler $handler): Response
    {
        if ($request->getMethod() === 'OPTIONS') {
            return $handler->handle($request);
        }

        $tournamnetId = $this->getTournamentId($request);
        if ($tournamnetId === null) {
            return $handler->handle($request);
        }

        $tournament = $this->tournamentRepos->find($tournamnetId);
        if ($tournament === null) {
            return new ForbiddenResponse('er kon geen toernooi worden gevonden voor: ' . $tournamnetId);
        }

        return $handler->handle($request->withAttribute('tournament', $tournament));
    }

    private function getTournamentId(Request $request): int|null
    {
        $routeContext = RouteContext::fromRequest($request);
        $routingResults = $routeContext->getRoutingResults();

        $args = $routingResults->getRouteArguments();

        if (array_key_exists('tournamentId', $args) === false) {
            return null;
        }
        return (int)$args['tournamentId'];
    }
}
