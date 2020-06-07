<?php
/**
 * Created by PhpStorm.
 * User: coen
 * Date: 28-3-18
 * Time: 20:31
 */

namespace App\Middleware\Authorization\Tournament;

use Psr\Http\Message\ServerRequestInterface as Request;
use App\Middleware\Authorization\Tournament\AdminMiddleware as AuthorizationTournamentAdminMiddleware;
use FCToernooi\TournamentUser;

class UserMiddleware extends AuthorizationTournamentAdminMiddleware
{
    protected function isTournamentUserAuthorized(Request $request, TournamentUser $tournamentUser)
    {
    }
}
