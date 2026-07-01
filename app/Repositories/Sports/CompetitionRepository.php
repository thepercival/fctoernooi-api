<?php

declare(strict_types=1);

namespace App\Repositories\Sports;

use Doctrine\ORM\EntityRepository;
use Sports\Competition;

/**
 * @template-extends EntityRepository<Competition>
 */
final class CompetitionRepository extends EntityRepository
{
    public function customPersist(Competition $competition): void
    {
        $em = $this->getEntityManager();
        foreach ($competition->getReferees() as $referee) {
            $em->persist($referee);
        }

        foreach ($competition->getSports() as $competitionSport) {
            $em->persist($competitionSport);
            foreach ($competitionSport->getFields() as $field) {
                $em->persist($field);
            }
        }

        $em->persist($competition);
    }
}
