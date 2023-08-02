<?php

declare(strict_types=1);

namespace FCToernooi\Tournament\Registration;

use Doctrine\ORM\EntityRepository;
use FCToernooi\Tournament;
use FCToernooi\Tournament\Registration;
use Sports\Place;
use Sports\Round;
use SportsHelpers\Repository as BaseRepository;

/**
 * @template-extends EntityRepository<Registration>
 */
class Repository extends EntityRepository
{
    /**
     * @use BaseRepository<Registration>
     */
    use BaseRepository;

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
     * @param Round $rootRound
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
        $this->flush();
    }
}
