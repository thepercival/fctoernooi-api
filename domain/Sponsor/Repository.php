<?php
declare(strict_types=1);

namespace FCToernooi\Sponsor;

use Doctrine\ORM\EntityRepository;
use FCToernooi\Sponsor as SponsorBase;
use FCToernooi\Tournament;

/**
 * @template-extends EntityRepository<SponsorBase>
 */
class Repository extends EntityRepository
{
    use \Sports\Repository;

    const MAXNROFSPONSORSPERSCREEN = 4;

    public function find($id, $lockMode = null, $lockVersion = null): ?SponsorBase
    {
        return $this->_em->find($this->_entityName, $id, $lockMode, $lockVersion);
    }

    public function checkNrOfSponsors(Tournament $tournament, int $newScreenNr, SponsorBase $sponsor = null)
    {
        $max = static::MAXNROFSPONSORSPERSCREEN;
        if ($sponsor === null || $sponsor->getScreenNr() !== $newScreenNr) {
            $max--;
        }
        $qb = $this->getEntityManager()->createQueryBuilder();
        $qb = $qb
            ->select('count(s.id)')
            ->from(SponsorBase::class, 's');

        $qb = $qb->where('s.tournament = :tournament')->andWhere('s.screenNr = :screenNr');
        $qb = $qb->setParameter('tournament', $tournament);
        $qb = $qb->setParameter('screenNr', $newScreenNr);

        $nrOfSponsorsPresent = $qb->getQuery()->getSingleScalarResult();
        if ($nrOfSponsorsPresent > $max) {
            throw new \Exception(
                "er kan geen sponsor aan schermnummer " . $newScreenNr . " meer worden toegevoegd, het maximum van " . static::MAXNROFSPONSORSPERSCREEN . " is bereikt",
                E_ERROR
            );
        }
    }
}
