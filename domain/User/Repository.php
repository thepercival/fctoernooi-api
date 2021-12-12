<?php

declare(strict_types=1);

namespace FCToernooi\User;

use Doctrine\ORM\EntityRepository;
use FCToernooi\User as UserBase;
use SportsHelpers\Repository as BaseRepository;

/**
 * @template-extends EntityRepository<UserBase>
 */
class Repository extends EntityRepository
{
    /**
     * @use BaseRepository<UserBase>
     */
    use BaseRepository;
}
