<?php
/**
 * Created by PhpStorm.
 * User: coen
 * Date: 28-3-18
 * Time: 20:31
 */

namespace App\Middleware\Authorization\Tournament;

use FCToernooi\Tournament;
use FCToernooi\User;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Middleware\AuthorizationMiddleware;

class PublicMiddleware extends AuthorizationMiddleware
{
    protected function isAuthorized(Request $request, User $user = null, Tournament $tournament = null)
    {
        if ($tournament === null) {
            throw new \Exception("het toernooi is onbekend", E_ERROR);
        }
        if ($tournament->getPublic() === false) {
            throw new \Exception("het toernooi is niet publiek", E_ERROR);
        }
    }
}
