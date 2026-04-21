<?php

declare(strict_types=1);

namespace FCToernooi\Role;

/**
 * @api
 */
enum State: int
{
    case Invitation = 1;
    case Role = 2;
}