<?php

declare(strict_types=1);

namespace FCToernooi\Sponsor;

use Doctrine\ORM\EntityRepository;
use Exception;
use FCToernooi\Sponsor as SponsorBase;
use FCToernooi\Tournament;
use SportsHelpers\Repository as BaseRepository;

/**
 * @template-extends EntityRepository<SponsorBase>
 */
class Repository extends EntityRepository
{
    /**
     * @use BaseRepository<SponsorBase>
     */
    use BaseRepository;

    public const MAXNROFSPONSORSPERSCREEN = 4;

    public function checkNrOfSponsors(Tournament $tournament, int $newScreenNr, SponsorBase $sponsor = null): void
    {
        $max = Repository::MAXNROFSPONSORSPERSCREEN;
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

        $nrOfSponsorsPresent = (int)$qb->getQuery()->getSingleScalarResult();
        if ($nrOfSponsorsPresent > $max) {
            throw new Exception(
                'er kan geen sponsor aan schermnummer ' . $newScreenNr . ' meer worden toegevoegd, het maximum van ' . Repository::MAXNROFSPONSORSPERSCREEN . ' is bereikt',
                E_ERROR
            );
        }
    }
}
