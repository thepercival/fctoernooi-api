<?php
/**
 * Created by PhpStorm.
 * User: coen
 * Date: 28-3-18
 * Time: 20:31
 */

namespace App\Middleware;

use FCToernooi\Tournament;
use FCToernooi\User;
use App\Response\ForbiddenResponse as ForbiddenResponse;
use FCToernooi\TournamentUser;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

abstract class AuthorizationMiddleware implements MiddlewareInterface
{
    /**
     * @var int
     */
    private $roles;

    public function __construct(int $roles)
    {
        $this->roles = $roles;
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
        if ($tournamentUser === null || !$this->isAuthorized($tournamentUser, $request)) {
            return new ForbiddenResponse("je hebt geen rechten voor deze aanvraag");
        }
        return $handler->handle($request);
    }

    protected function isAuthorized(TournamentUser $tournamentUser, Request $request): bool
    {
        return $tournamentUser->hasRoles($this->roles);
    }
}
