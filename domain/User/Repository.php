<?php
declare(strict_types=1);

namespace FCToernooi\User;

use SportsHelpers\Repository as BaseRepository;
use Doctrine\ORM\EntityRepository;
use FCToernooi\User as UserBase;

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
