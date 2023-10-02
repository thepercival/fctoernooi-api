<?php

declare(strict_types=1);

namespace FCToernooi\Tournament;

use DateTimeImmutable;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query\Expr;
use FCToernooi\Tournament as TournamentBase;
use FCToernooi\TournamentUser;
use FCToernooi\User;
use Sports\Competition;
use Sports\Competition\Repository as CompetitionRepository;
use SportsHelpers\Repository as BaseRepository;

/**
 * @template-extends EntityRepository<TournamentBase>
 */
class Repository extends EntityRepository
{
    /**
     * @use BaseRepository<TournamentBase>
     */
    use BaseRepository;

    public function customPersist(TournamentBase $tournament, bool $flush): void
    {
        /** @psalm-suppress MixedArgumentTypeCoercion */
        $competitionRepos = new CompetitionRepository($this->_em, $this->_em->getClassMetadata(Competition::class));
        $competitionRepos->customPersist($tournament->getCompetition());
        $this->_em->persist($tournament->getCompetition()->getLeague());
        $this->_em->flush();
        $this->_em->persist($tournament);
        if ($flush) {
            $this->_em->flush();
        }
    }

    /**
     * @param ShellFilter $shellFilter
     * @param DateTimeImmutable|null $startDateTimeCreated
     * @param DateTimeImmutable|null $endDateTimeCreated
     * @return list<TournamentBase>
     */
    public function findByFilter(
        ShellFilter $shellFilter,
        DateTimeImmutable $startDateTimeCreated = null,
        DateTimeImmutable $endDateTimeCreated = null,
        int $max = null
    ): array {
        $query = $this->createQueryBuilder('t')
            ->join("t.competition", "c")
            ->join("c.league", "l");

        if ($shellFilter->startDateTime !== null) {
            $query = $query->andWhere('c.startDateTime >= :startDateTime');
            $query = $query->setParameter('startDateTime', $shellFilter->startDateTime);
        }

        if ($shellFilter->endDateTime !== null) {
            $query = $query->andWhere('c.startDateTime <= :endDateTime');
            $query = $query->setParameter('endDateTime', $shellFilter->endDateTime);
        }

        if ($startDateTimeCreated !== null) {
            $query = $query->andWhere('t.createdDateTime >= :startDateTimeCreated');
            $query = $query->setParameter('startDateTimeCreated', $startDateTimeCreated);
        }

        if ($endDateTimeCreated !== null) {
            $query = $query->andWhere('t.createdDateTime <= :endDateTimeCreated');
            $query = $query->setParameter('endDateTimeCreated', $endDateTimeCreated);
        }

        if ($shellFilter->name !== null) {
            $query = $query->andWhere("l.name like :name");
            $query = $query->setParameter('name', '%' . $shellFilter->name . '%');
        }

        if ($shellFilter->public !== null) {
            $query = $query->andWhere("t.public = :public");
            $query = $query->setParameter('public', $shellFilter->public);
        }

        if ($shellFilter->example !== null) {
            $query = $query->andWhere("t.example = :example");
            $query = $query->setParameter('example', $shellFilter->example);
        }

        if ($max !== null) {
            $query = $query->setMaxResults($max);
        }

        /** @var list<TournamentBase> $results */
        $results = $query->getQuery()->getResult();
        return $results;
    }

    /**
     * @param User $user
     * @param int $roles
     * @return list<TournamentBase>
     */
    public function findByRoles(User $user, int $roles): array
    {
        $qb = $this->createQueryBuilder('t')
            ->join(TournamentUser::class, 'tu', Expr\Join::WITH, 't.id = tu.tournament');

        $qb = $qb->where('tu.user = :user')->andWhere('BIT_AND(tu.roles, :roles) > 0');
        $qb = $qb->setParameter('user', $user);
        $qb = $qb->setParameter('roles', $roles);

        $qb = $qb->andWhere('t.example = :example');
        $qb = $qb->setParameter('example', false);

        /** @var list<TournamentBase> $results */
        $results = $qb->getQuery()->getResult();
        return $results;
    }

    public function removeByLeague(TournamentBase $tournament): void
    {
        $this->_em->remove($tournament->getCompetition()->getLeague());
        $this->_em->flush();
    }
}
