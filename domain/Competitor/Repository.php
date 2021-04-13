<?php

declare(strict_types=1);

namespace FCToernooi\Competitor;

use SportsHelpers\Repository\SaveRemove as SaveRemoveRepository;
use SportsHelpers\Repository as BaseRepository;
use Doctrine\ORM\EntityRepository;
use FCToernooi\Competitor as CompetitorBase;
use FCToernooi\Tournament;
use Sports\Place;
use Sports\Round;

/**
 * @template-extends EntityRepository<CompetitorBase>
 * @template-implements SaveRemoveRepository<CompetitorBase>
 */
class Repository extends EntityRepository implements SaveRemoveRepository
{
    use BaseRepository;

    public function syncCompetitors(Tournament $tournament, Round $rootRound): void
    {
        /**
         * @param Round $rootRound
         * @return list<Place>
         */
        $getUnassignedPlaces = function (Round $rootRound): array {
            $unassignedPlaces = [];
            foreach ($rootRound->getPlaces() as $place) {
                $unassignedPlaces[$place->getPoule()->getNumber() . '.' . $place->getNumber() ] = $place;
            }
            return $unassignedPlaces;
        };
        $unassignedPlaces = $getUnassignedPlaces($rootRound);

        /**
         * @param Tournament $tournament
         * @param list<Place> $unassignedPlaces
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
            $unassignedCompetitor->setPouleNr($unassignedPlace->getPoule()->getNumber());
            $unassignedCompetitor->setPlaceNr($unassignedPlace->getNumber());
            $this->save($unassignedCompetitor);
        }
        while (count($unassignedCompetitors) > 0) {
            $unassignedCompetitor = array_shift($unassignedCompetitors);
            $tournament->getCompetitors()->removeElement($unassignedCompetitor);
            $this->remove($unassignedCompetitor);
            $this->save($unassignedCompetitor);
        }
    }
}
