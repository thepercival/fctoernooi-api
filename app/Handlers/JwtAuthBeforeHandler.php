<?php

namespace App\Handlers;

use App\Services\AuthToken;
use JimTools\JwtAuth\Handlers\BeforeHandlerInterface;
use Psr\Http\Message\ServerRequestInterface;

final class JwtAuthBeforeHandler implements BeforeHandlerInterface
{
    #[\Override]
    public function __invoke(ServerRequestInterface $request, array $arguments): ServerRequestInterface
    {
        if (count($arguments['decoded']) > 0) {
            /** @var array<string, string|int> $decoded */
            $decoded = $arguments['decoded'];
            $token = new AuthToken($decoded);
            return $request->withAttribute('token', $token);
        }
        return $request;
    }
}