<?php
/**
 * Created by PhpStorm.
 * User: coen
 * Date: 28-3-18
 * Time: 20:31
 */

namespace App\Middleware\Authorization;

use FCToernooi\Tournament;
use FCToernooi\User;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Middleware\AuthorizationMiddleware;

class UserMiddleware extends AuthorizationMiddleware
{
    protected function isAuthorized(Request $request, User $user = null, Tournament $tournament = null)
    {
        if ($user === null) {
            throw new \Exception("je moet ingelogd zijn voor dit toernooi");
        };
    }
}
