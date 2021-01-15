<?php

declare(strict_types=1);

namespace FCToernooi\Tournament;

use DateTimeImmutable;
use FCToernooi\Tournament;
use FCToernooi\User;
use FCToernooi\TournamentUser;
use Doctrine\ORM\Query\Expr;
use Sports\League;
use Sports\Competition;
use Sports\Competition\Repository as CompetitionRepository;
use Sports\League\Repository as LeagueRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;

class Repository extends \Sports\Repository
{
    public function __construct(EntityManagerInterface $em, ClassMetadata $class)
    {
        parent::__construct($em, $class);
    }

    public function find($id, $lockMode = null, $lockVersion = null): ?Tournament
    {
        return $this->_em->find($this->_entityName, $id, $lockMode, $lockVersion);
    }

    public function customPersist(Tournament $tournament, bool $flush)
    {
        $competitionRepos = new CompetitionRepository($this->_em, $this->_em->getClassMetadata(Competition::class));
        $competitionRepos->customPersist($tournament->getCompetition());
        $leagueRepos = new LeagueRepository($this->_em, $this->_em->getClassMetadata(League::class));
        $leagueRepos->save($tournament->getCompetition()->getLeague());
        $this->_em->persist($tournament);
        if ($flush) {
            $this->_em->flush();
        }
    }

    public function findByFilter(
        string $name = null,
        DateTimeImmutable $startDateTime = null,
        DateTimeImmutable $endDateTime = null,
        bool $public = null,
        DateTimeImmutable $startDateTimeCreated = null,
        DateTimeImmutable $endDateTimeCreated = null
    ) {
        $query = $this->createQueryBuilder('t')
            ->join("t.competition", "c")
            ->join("c.league", "l");

        if ($startDateTime !== null) {
            $query = $query->where('c.startDateTime >= :startDateTime');
            $query = $query->setParameter('startDateTime', $startDateTime);
        }

        if ($endDateTime !== null) {
            $query = $query->andWhere('c.startDateTime <= :endDateTime');
            $query = $query->setParameter('endDateTime', $endDateTime);
        }

        if ($startDateTimeCreated !== null) {
            $query = $query->where('t.createdDateTime >= :startDateTimeCreated');
            $query = $query->setParameter('startDateTimeCreated', $startDateTimeCreated);
        }

        if ($endDateTimeCreated !== null) {
            $query = $query->andWhere('t.createdDateTime <= :endDateTimeCreated');
            $query = $query->setParameter('endDateTimeCreated', $endDateTimeCreated);
        }

        if ($name !== null) {
            if ($startDateTime !== null || $endDateTime !== null) {
                $query = $query->andWhere("l.name like :name");
            } else {
                $query = $query->where('l.name like :name');
            }
            $query = $query->setParameter('name', '%' . $name . '%');
        }

        if ($public !== null) {
            if ($startDateTime !== null || $endDateTime !== null || $name !== null) {
                $query = $query->andWhere("t.public = :public");
            } else {
                $query = $query->where('t.public = :public');
            }
            $query = $query->setParameter('public', $public);
        }

        return $query->getQuery()->getResult();
    }

    public function findByRoles(User $user, int $roles)
    {
        $qb = $this->createQueryBuilder('t')
            ->join(TournamentUser::class, 'tu', Expr\Join::WITH, 't.id = tu.tournament');

        $qb = $qb->where('tu.user = :user')->andWhere('BIT_AND(tu.roles, :roles) > 0');
        $qb = $qb->setParameter('user', $user);
        $qb = $qb->setParameter('roles', $roles);

        return $qb->getQuery()->getResult();
    }

    public function remove($tournament)
    {
        $leagueRepos = new LeagueRepository($this->_em, $this->_em->getClassMetadata(League::class));
        $leagueRepos->remove($tournament->getCompetition()->getLeague());
    }
}
