<?php

declare(strict_types=1);

namespace App\Middleware\Authorization\Tournament;

use Psr\Http\Message\ServerRequestInterface as Request;
use App\Middleware\Authorization\Tournament\AdminMiddleware as AuthorizationTournamentAdminMiddleware;
use FCToernooi\TournamentUser;

class UserMiddleware extends AuthorizationTournamentAdminMiddleware
{
    protected function isTournamentUserAuthorized(Request $request, TournamentUser $tournamentUser): void
    {
    }
}
