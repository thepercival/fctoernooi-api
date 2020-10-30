<?php

declare(strict_types=1);

namespace App\Middleware\Authorization\Tournament\Admin;

use App\Response\ForbiddenResponse as ForbiddenResponse;
use FCToernooi\Tournament;
use FCToernooi\User;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Middleware\Authorization\Tournament\AdminMiddleware as AuthorizationTournamentAdminMiddleware;
use FCToernooi\Role;
use FCToernooi\TournamentUser;

class AdminMiddleware extends AuthorizationTournamentAdminMiddleware
{
    protected function isTournamentUserAuthorized(Request $request, TournamentUser $tournamentUser)
    {
        if ($tournamentUser->hasRoles(Role::ADMIN) === false) {
            throw new \Exception("je bent geen " . Role::getName(Role::ADMIN) . " voor dit toernooi", E_ERROR);
        };
    }
}
