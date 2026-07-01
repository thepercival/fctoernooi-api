<?php

declare(strict_types=1);

namespace App\Repositories;

use Doctrine\ORM\EntityRepository;
use Exception;
use FCToernooi\Sponsor as SponsorBase;
use FCToernooi\Tournament;

/**
 * @template-extends EntityRepository<SponsorBase>
 */
final class SponsorRepository extends EntityRepository
{
    public const int MAXNROFSPONSORSPERSCREEN = 4;

    public function checkNrOfSponsors(Tournament $tournament, int $newScreenNr, SponsorBase|null $sponsor = null): void
    {
        $max = SponsorRepository::MAXNROFSPONSORSPERSCREEN;
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
        if (!is_int($nrOfSponsorsPresent) || $nrOfSponsorsPresent > $max) {
            throw new Exception(
                'er kan geen sponsor aan schermnummer ' . $newScreenNr . ' meer worden toegevoegd, het maximum van ' . SponsorRepository::MAXNROFSPONSORSPERSCREEN . ' is bereikt',
                E_ERROR
            );
        }
    }
}
