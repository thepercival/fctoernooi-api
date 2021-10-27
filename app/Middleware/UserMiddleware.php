<?php
declare(strict_types=1);

namespace App\Middleware;

use FCToernooi\User\Repository as UserRepository;
use FCToernooi\Auth\Token as AuthToken;
use FCToernooi\User;
use App\Response\ForbiddenResponse as ForbiddenResponse;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

class UserMiddleware implements MiddlewareInterface
{
    public function __construct(protected UserRepository $userRepos)
    {
    }

    public function process(Request $request, RequestHandler $handler): Response
    {
        if ($request->getMethod() === "OPTIONS") {
            return $handler->handle($request);
        }

        /** @var AuthToken|null $token */
        $token = $request->getAttribute('token');
        if ($token === null) {
            return $handler->handle($request);
        }
        $user = $this->getUser($token);
        if ($user === null) {
            return $handler->handle($request);
        }
        return $handler->handle($request->withAttribute("user", $user));
    }

    protected function getUser(AuthToken $token): ?User
    {
        return $this->userRepos->find($token->getUserId());
    }
}
