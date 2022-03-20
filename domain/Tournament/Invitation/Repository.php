<?php

declare(strict_types=1);

namespace FCToernooi\Tournament\Invitation;

use Doctrine\ORM\EntityRepository;
use FCToernooi\Tournament;
use FCToernooi\Tournament\Invitation;
use SportsHelpers\Repository as BaseRepository;

/**
 * @template-extends EntityRepository<Invitation>
 */
class Repository extends EntityRepository
{
    /**
     * @use BaseRepository<Invitation>
     */
    use BaseRepository;

    public function findOneByCustom(Tournament $tournament, string $emailaddress, int $roles): Invitation|null
    {
        $qb = $this->createQueryBuilder('i');

        $qb = $qb
            ->where('i.tournament = :tournament')
            ->andWhere('i.emailaddress = :emailaddress')
            ->andWhere('BIT_AND(i.roles, :roles) > 0');
        $qb = $qb->setParameter('tournament', $tournament);
        $qb = $qb->setParameter('emailaddress', $emailaddress);
        $qb = $qb->setParameter('roles', $roles);
        /** @var Invitation|null $result */
        $result = $qb->getQuery()->getSingleResult();
        return $result;
    }
}
