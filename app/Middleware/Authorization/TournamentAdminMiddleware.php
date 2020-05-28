<?php
/**
 * Created by PhpStorm.
 * User: coen
 * Date: 28-3-18
 * Time: 20:31
 */

namespace App\Middleware\Authorization;

use App\Middleware\AuthorizationMiddleware;
use FCToernooi\Role;

class TournamentAdminMiddleware extends AuthorizationMiddleware
{
    public function __construct()
    {
        parent::__construct(Role::ADMIN);
    }
}
