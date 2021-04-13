<?php
declare(strict_types=1);

namespace FCToernooi\TournamentUser;

use SportsHelpers\Repository\SaveRemove as SaveRemoveRepository;
use SportsHelpers\Repository as BaseRepository;
use Doctrine\ORM\EntityRepository;
use FCToernooi\TournamentUser as TournamentUserBase;

/**
 * @template-extends EntityRepository<TournamentUserBase>
 * @template-implements SaveRemoveRepository<TournamentUserBase>
 */
class Repository extends EntityRepository implements SaveRemoveRepository
{
    use BaseRepository;
}
