<?php

declare(strict_types=1);

namespace App\Repositories;

use Doctrine\ORM\EntityRepository;
use FCToernooi\Tournament;
use FCToernooi\Tournament\Registration;

/**
 * @template-extends EntityRepository<Registration>
 */
final class TournamentRegistrationRepository extends EntityRepository
{

    /**
     * @param Tournament $tournament
     * @param int $categoryNr
     * @return list<Registration>
     */
    public function findByCategoryNr(Tournament $tournament, int $categoryNr): array {
        $registrations = $this->findBy(['tournament' => $tournament, 'categoryNr' => $categoryNr]);
        return $registrations;
    }

    /**
     * @param Tournament $tournament
     * @param array<int, int|null> $fromToCategoryMap
     * @return void
     */
    public function syncRegistrations(Tournament $tournament, array $fromToCategoryMap): void
    {
        foreach( $fromToCategoryMap as $oldNr => $newNr ) {
            $registrations = $this->findByCategoryNr($tournament, $oldNr);
            if( $newNr === null ) {
                if( count($registrations) === 0 ) {
                    continue;
                }
                throw new \Exception('je kan geen categorien verwijderen waar al inschrijvingen op zijn gedaan', E_ERROR);
            }
            foreach($registrations as $registration) {
                $registration->setCategoryNr($newNr);
            }
        }
        $this->getEntityManager()->flush();
    }
}
