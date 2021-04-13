<?php
declare(strict_types=1);

namespace FCToernooi\Tournament;

use SportsHelpers\Repository\SaveRemove as SaveRemoveRepository;
use SportsHelpers\Repository as BaseRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityRepository;
use FCToernooi\Tournament as TournamentBase;
use FCToernooi\User;
use FCToernooi\TournamentUser;
use Doctrine\ORM\Query\Expr;
use Sports\League;
use Sports\Competition;
use Sports\Competition\Repository as CompetitionRepository;
use Sports\League\Repository as LeagueRepository;

/**
 * @template-extends EntityRepository<TournamentBase>
 * @template-implements SaveRemoveRepository<TournamentBase>
 */
class Repository extends EntityRepository implements SaveRemoveRepository
{
    use BaseRepository;

    public function customPersist(TournamentBase $tournament, bool $flush): void
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

    /**
     * @param string|null $name
     * @param DateTimeImmutable|null $startDateTime
     * @param DateTimeImmutable|null $endDateTime
     * @param bool|null $public
     * @param DateTimeImmutable|null $startDateTimeCreated
     * @param DateTimeImmutable|null $endDateTimeCreated
     * @return list<TournamentBase>
     */
    public function findByFilter(
        string $name = null,
        DateTimeImmutable $startDateTime = null,
        DateTimeImmutable $endDateTime = null,
        bool $public = null,
        DateTimeImmutable $startDateTimeCreated = null,
        DateTimeImmutable $endDateTimeCreated = null
    ): array {
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
        /** @var list<TournamentBase> $results */
        $results = $qb->getQuery()->getResult();
        return $results;
    }

    public function removeByLeague(TournamentBase $tournament): void
    {
        $this->remove($tournament->getCompetition()->getLeague());
    }
}
