<?php

declare(strict_types=1);

namespace App\Repositories;

use Doctrine\ORM\EntityRepository;
use FCToernooi\Competitor as CompetitorBase;
use FCToernooi\Tournament;
use Sports\Place;
use Sports\Round;

/**
 * @api
 * @template-extends EntityRepository<CompetitorBase>
 */
final class CompetitorRepository extends EntityRepository
{
    public function syncCompetitors(Tournament $tournament, Round $rootRound): void
    {
        /**
         * @param Round $rootRound
         * @return array<string, Place>
         */
        $getUnassignedPlaces = function (Round $rootRound): array {
            $unassignedPlaces = [];
            foreach ($rootRound->getPlaces() as $place) {
                $unassignedPlaces[$place->getUniqueIndex()] = $place;
            }
            return $unassignedPlaces;
        };
        $unassignedPlaces = $getUnassignedPlaces($rootRound);

        /**
         * @param Tournament $tournament
         * @return list<CompetitorBase>
         */
        $getUnassignedCompetitors = function (Tournament $tournament) use (&$unassignedPlaces): array {
            $unassignedCompetitors = [];
            foreach ($tournament->getCompetitors() as $competitor) {
                $placeLocationId = $competitor->getPouleNr() . "." . $competitor->getPlaceNr();
                if (array_key_exists($placeLocationId, $unassignedPlaces)) {
                    unset($unassignedPlaces[$placeLocationId]);
                } else {
                    $unassignedCompetitors[$placeLocationId] = $competitor;
                }
            }
            return $unassignedCompetitors;
        };

        $unassignedCompetitors = $getUnassignedCompetitors($tournament);

        while (count($unassignedPlaces) > 0 && count($unassignedCompetitors) > 0) {
            $unassignedPlace = array_shift($unassignedPlaces);
            $unassignedCompetitor = array_shift($unassignedCompetitors);
            $unassignedCompetitor->setPouleNr($unassignedPlace->getPouleNr());
            $unassignedCompetitor->setPlaceNr($unassignedPlace->getPlaceNr());
            $this->getEntityManager()->persist($unassignedCompetitor);
            $this->getEntityManager()->flush();
        }
        while (count($unassignedCompetitors) > 0) {
            $unassignedCompetitor = array_shift($unassignedCompetitors);
            $tournament->getCompetitors()->removeElement($unassignedCompetitor);
            $this->getEntityManager()->remove($unassignedCompetitor);
            $this->getEntityManager()->flush();
            $this->getEntityManager()->persist($unassignedCompetitor);
            $this->getEntityManager()->flush();
        }
    }
}
