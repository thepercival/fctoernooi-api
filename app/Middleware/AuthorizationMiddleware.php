<?php

declare(strict_types=1);

namespace App\Middleware;

use Exception;
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
    public function process(Request $request, RequestHandler $handler): Response
    {
        if ($request->getMethod() === "OPTIONS") {
            return $handler->handle($request);
        }
        try {
            $this->isAuthorized($request, $request->getAttribute('user'), $request->getAttribute('tournament'));
        } catch (Exception $e) {
            return new ForbiddenResponse($e->getMessage());
        }
        return $handler->handle($request);
    }

    /**
     * @param Request $request
     * @param User|null $user
     * @param Tournament|null $tournament
     * @throws Exception
     */
    abstract protected function isAuthorized(Request $request, User $user = null, Tournament $tournament = null);
}
