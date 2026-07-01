<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Services\AuthToken;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use FCToernooi\User;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

/**
 * @api
 */
final class UserMiddleware implements MiddlewareInterface
{
    /** @var EntityRepository<User> */
    protected EntityRepository $userRepos;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $metaData = $entityManager->getClassMetadata(User::class);
        $this->userRepos = new EntityRepository($entityManager, $metaData);
    }

    #[\Override]
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
