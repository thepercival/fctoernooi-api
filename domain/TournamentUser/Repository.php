<?php
declare(strict_types=1);

namespace FCToernooi\TournamentUser;

use SportsHelpers\Repository as BaseRepository;
use Doctrine\ORM\EntityRepository;
use FCToernooi\TournamentUser as TournamentUserBase;

/**
 * @template-extends EntityRepository<TournamentUserBase>
 */
class Repository extends EntityRepository
{
    use BaseRepository;
}
