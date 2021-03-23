<?php

declare(strict_types=1);

namespace App\Middleware\Authorization\Tournament;

use App\Response\ForbiddenResponse as ForbiddenResponse;
use FCToernooi\Tournament;
use FCToernooi\User;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Middleware\AuthorizationMiddleware;
use FCToernooi\Role;
use FCToernooi\TournamentUser;

abstract class AdminMiddleware extends AuthorizationMiddleware
{
    protected function isAuthorized(Request $request, User $user = null, Tournament $tournament = null): void
    {
        if ($user === null) {
            throw new \Exception("je moet ingelogd zijn voor dit toernooi", E_ERROR);
        };
        if ($tournament === null) {
            throw new \Exception("het toernooi is onbekend", E_ERROR);
        }
        $tournamentUser = $tournament->getUser($user);
        if ($tournamentUser === null) {
            throw new \Exception("je hebt geen rol voor dit toernooi", E_ERROR);
        }

        $this->isTournamentUserAuthorized($request, $tournamentUser);
    }

    abstract protected function isTournamentUserAuthorized(Request $request, TournamentUser $tournamentUser): void;
}
