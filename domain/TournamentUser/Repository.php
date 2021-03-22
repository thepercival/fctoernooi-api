<?php

declare(strict_types=1);

namespace FCToernooi\TournamentUser;

use Doctrine\ORM\EntityRepository;
use FCToernooi\TournamentUser as TournamentUserBase;

/**
 * @template-extends EntityRepository<TournamentUserBase>
 */
class Repository extends EntityRepository
{
    use \Sports\Repository;
}
