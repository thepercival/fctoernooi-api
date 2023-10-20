<?php

declare(strict_types=1);

namespace FCToernooi\Tournament\Rule;

use Doctrine\ORM\EntityRepository;
use SportsHelpers\Repository as BaseRepository;
use FCToernooi\Tournament\Rule as TournamentRule;

/**
 * @template-extends EntityRepository<TournamentRule>
 */
class Repository extends EntityRepository
{
    /**
     * @use BaseRepository<TournamentRule>
     */
    use BaseRepository;
}
