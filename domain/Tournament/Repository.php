<?php
/**
 * Created by PhpStorm.
 * User: coen
 * Date: 10-10-17
 * Time: 12:27
 */

namespace FCToernooi\Tournament;

use FCToernooi\Tournament;
use FCToernooi\User;
use FCToernooi\Role;
use Doctrine\ORM\Query\Expr;

/**
 * Class Repository
 * @package Voetbal\Competition
 */
class Repository extends \Voetbal\Repository
{
    public function findByPeriod(
        \DateTimeImmutable $startDateTime = null,
        \DateTimeImmutable $endDateTime = null
    )
    {
        $query = $this->createQueryBuilder('t')
            ->join("t.competition","cs");

        if( $startDateTime !== null ) {
            $query = $query
                ->where('cs.startDateTime >= :date')
                ->setParameter('date', $startDateTime);
        }

            // ->andWhere('s.begindatum is null or lidm.einddatum >= :date');

        if( $endDateTime !== null ) {
            $query = $query
                ->where('cs.startDateTime <= :date')
                ->setParameter('date', $endDateTime);
        }

        return $query->getQuery()->getResult();
    }

    public function findByPermissions( User $user, int $roleValues )
    {
        $qb = $this->getEntityManager()->createQueryBuilder();
        $qb = $qb
            ->select('t')
            ->from( Tournament::class, 't')
            ->join( Role::class, 'r', Expr\Join::WITH, 't.id = r.tournament')
        ;

        $qb = $qb
            ->distinct()
            ->where('r.user = :user')
            ->andWhere('BIT_AND(r.value, :rolevalues) = r.value');

        $qb = $qb->setParameter('user', $user);
        $qb = $qb->setParameter('rolevalues', $roleValues);

        return $qb->getQuery()->getResult();
    }

}