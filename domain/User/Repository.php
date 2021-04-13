<?php
declare(strict_types=1);

namespace FCToernooi\User;

use SportsHelpers\Repository\SaveRemove as SaveRemoveRepository;
use SportsHelpers\Repository as BaseRepository;
use Doctrine\ORM\EntityRepository;
use FCToernooi\User as UserBase;

/**
 * @template-extends EntityRepository<UserBase>
 * @template-implements SaveRemoveRepository<UserBase>
 */
class Repository extends EntityRepository implements SaveRemoveRepository
{
    use BaseRepository;
}
