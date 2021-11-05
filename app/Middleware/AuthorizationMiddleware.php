<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Response\ForbiddenResponse as ForbiddenResponse;
use Exception;
use FCToernooi\Tournament;
use FCToernooi\User;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

abstract class AuthorizationMiddleware implements MiddlewareInterface
{
    public function process(Request $request, RequestHandler $handler): Response
    {
        if ($request->getMethod() === "OPTIONS") {
            return $handler->handle($request);
        }
        try {
            /** @var User $user */
            $user = $request->getAttribute('user');
            /** @var Tournament $tournament */
            $tournament = $request->getAttribute('tournament');
            $this->isAuthorized($request, $user, $tournament);
        } catch (Exception $exception) {
            return new ForbiddenResponse($exception->getMessage());
        }
        return $handler->handle($request);
    }

    abstract protected function isAuthorized(
        Request $request,
        User|null $user = null,
        Tournament|null $tournament = null
    ): void;
}
