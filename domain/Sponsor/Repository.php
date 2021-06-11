<?php
declare(strict_types=1);

namespace FCToernooi\Sponsor;

use Exception;
use SportsHelpers\Repository\SaveRemove as SaveRemoveRepository;
use SportsHelpers\Repository as BaseRepository;
use Doctrine\ORM\EntityRepository;
use FCToernooi\Sponsor as SponsorBase;
use FCToernooi\Tournament;

/**
 * @template-extends EntityRepository<SponsorBase>
 * @template-implements SaveRemoveRepository<SponsorBase>
 */
class Repository extends EntityRepository implements SaveRemoveRepository
{
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
