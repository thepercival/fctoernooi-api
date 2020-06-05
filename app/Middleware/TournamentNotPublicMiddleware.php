<?php
/**
 * Created by PhpStorm.
 * User: coen
 * Date: 28-3-18
 * Time: 20:31
 */

namespace App\Middleware;

use App\Middleware\AuthorizationMiddleware;
use App\Response\ForbiddenResponse as ForbiddenResponse;
use FCToernooi\Role;
use FCToernooi\Tournament;
use FCToernooi\User;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

class TournamentNotPublicMiddleware implements MiddlewareInterface
{
    public function __construct()
    {
    }

    public function process(Request $request, RequestHandler $handler): Response
    {
        if ($request->getMethod() === "OPTIONS") {
            return $handler->handle($request);
        }

        /** @var Tournament $tournament */
        $tournament = $request->getAttribute("tournament");

        if ($tournament->getPublic()) {
            return $handler->handle($request);
        }

        /** @var User $user */
        $user = $request->getAttribute('user');
        if ($user === null) {
            return new ForbiddenResponse("je hebt geen rechten voor deze aanvraag");
        }

        $tournamentUser = $tournament->getUser($user);
        if ($tournamentUser !== null && $tournamentUser->getRoles() > 0) {
            return $handler->handle($request);
        }
        return new ForbiddenResponse("je hebt geen rollen voor deze aanvraag");
    }
}
