<?php

declare(strict_types=1);

namespace App\Middleware;

use Slim\Routing\RouteContext;
use FCToernooi\Tournament\Repository as TournamentRepository;
use App\Response\ForbiddenResponse as ForbiddenResponse;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

class TournamentMiddleware implements MiddlewareInterface
{
    /**
     * @var TournamentRepository
     */
    protected $tournamentRepos;

    public function __construct(
        TournamentRepository $tournamentRepos
    ) {
        $this->tournamentRepos = $tournamentRepos;
    }

    public function process(Request $request, RequestHandler $handler): Response
    {
        if ($request->getMethod() === "OPTIONS") {
            return $handler->handle($request);
        }

        $routeContext = RouteContext::fromRequest($request);
        $routingResults = $routeContext->getRoutingResults();

        $args = $routingResults->getRouteArguments();

        if (array_key_exists("tournamentId", $args) === false) {
            return $handler->handle($request);
        }

        $tournament = $this->tournamentRepos->find((int)$args["tournamentId"]);
        if ($tournament === null) {
            return new ForbiddenResponse("er kon geen toernooi worden gevonden voor: " . $args["tournamentId"]);
        }

        return $handler->handle($request->withAttribute("tournament", $tournament));
    }
}
