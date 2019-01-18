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
use Voetbal\Referee;
use Voetbal\Competition;

/**
 * Class Repository
 * @package Voetbal\Competition
 */
class Repository extends \Voetbal\Repository
{
    public function customPersist( Tournament $tournament, bool $flush )
    {
        $competitionRepos = $this->_em->getRepository(Competition::class);
        $competitionRepos->customPersist($tournament->getCompetition());
        $this->_em->persist($tournament);
        if( $flush ) {
            $this->_em->flush();
        }
    }

    public function findByFilter(string $name = null, \DateTimeImmutable $startDateTime = null, \DateTimeImmutable $endDateTime = null )
    {
        $query = $this->createQueryBuilder('t')
            ->join("t.competition","c")
            ->join("c.league","l");

        if( $startDateTime !== null ) {
            $query = $query->where('c.startDateTime >= :startDateTime');
            $query = $query->setParameter('startDateTime', $startDateTime);
        }

        if( $endDateTime !== null ) {
            if( $startDateTime !== null ) {
                $query = $query->andWhere('c.startDateTime <= :endDateTime');
            } else {
                $query = $query->where('c.startDateTime <= :endDateTime');
            }
            $query = $query->setParameter('endDateTime', $endDateTime);
        }

        if( $name !== null ) {
            if( $startDateTime !== null || $endDateTime !== null ) {
                $query = $query->andWhere("l.name like :name");
            } else {
                $query = $query->where('l.name like :name');
            }

            $query = $query->setParameter('name', '%'.$name.'%');
        }
        return $query->getQuery()->getResult();
    }

    public function findByPermissions( User $user, int $roleValues )
    {
        $qb = $this->getEntityManager()->createQueryBuilder();
        $qb = $qb
            ->distinct()
            ->select('t')
            ->from( Tournament::class, 't')
            ->join( Role::class, 'r', Expr\Join::WITH, 't.id = r.tournament')
        ;

        $qb = $qb->where('r.user = :user')->andWhere('BIT_AND(r.value, :rolevalues) = r.value');
        $qb = $qb->setParameter('user', $user);
        $qb = $qb->setParameter('rolevalues', $roleValues);

        return $qb->getQuery()->getResult();
    }

    public function findByEmailaddress( $emailladdress )
    {
        if( strlen( $emailladdress ) === 0 ) {
            return [];
        }
        $qb = $this->getEntityManager()->createQueryBuilder();
        $qb = $qb
            ->select('t')
            ->from( Tournament::class, 't')
            ->join( Competition::class, 'c', Expr\Join::WITH, 'c.id = t.competition')
            ->join( Referee::class, 'ref', Expr\Join::WITH, 'c.id = ref.competition')
        ;

        $qb = $qb
            ->distinct()
            ->where('ref.emailaddress = :emailaddress');

        $qb = $qb->setParameter('emailaddress', $emailladdress);

        return $qb->getQuery()->getResult();
    }

}