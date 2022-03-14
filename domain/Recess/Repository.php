<?php

declare(strict_types=1);

namespace FCToernooi\Recess;

use Doctrine\ORM\EntityRepository;
use FCToernooi\Recess;
use SportsHelpers\Repository as BaseRepository;

/**
 * @template-extends EntityRepository<Recess>
 */
class Repository extends EntityRepository
{
    /**
     * @use BaseRepository<Recess>
     */
    use BaseRepository;

}
