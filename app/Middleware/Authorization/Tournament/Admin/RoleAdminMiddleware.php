<?php
/**
 * Created by PhpStorm.
 * User: coen
 * Date: 28-3-18
 * Time: 20:31
 */

namespace App\Middleware\Authorization\Tournament\Admin;

use FCToernooi\Role;
use FCToernooi\TournamentUser;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Middleware\Authorization\Tournament\AdminMiddleware as AuthorizationTournamentAdminMiddleware;

class RoleAdminMiddleware extends AuthorizationTournamentAdminMiddleware
{
    protected function isTournamentUserAuthorized(Request $request, TournamentUser $tournamentUser)
    {
        if ($tournamentUser->hasRoles(Role::ROLEADMIN) === false) {
            throw new \Exception("je bent geen " . Role::getName(Role::ROLEADMIN) . " voorr dit toernooi", E_ERROR);
        };
    }
}
