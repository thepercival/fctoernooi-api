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

class TournamentPublicMiddleware implements MiddlewareInterface
{
    public function __construct()
    {
    }

    public function process(Request $request, RequestHandler $handler): Response
    {
        if ($request->getMethod() === "OPTIONS") {
            return $handler->handle($request);
        }

        /** @var User $user */
        $user = $request->getAttribute('user');

        /** @var Tournament $tournament */
        $tournament = $request->getAttribute("tournament");

        $tournamentUser = $tournament->getUser($user);
        if ($tournamentUser === null && !$tournament->getPublic()) {
            return new ForbiddenResponse("je hebt geen rechten voor deze aanvraag");
        }
        return $handler->handle($request);
    }
}
