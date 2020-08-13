<?php
/**
 * Created by PhpStorm.
 * User: coen
 * Date: 22-5-18
 * Time: 12:14
 */

namespace FCToernooi\Competitor;

use FCToernooi\Competitor as CompetitorBase;
use FCToernooi\Tournament;
use Sports\Place;
use Sports\Round;

class Repository extends \Sports\Repository
{
    public function find($id, $lockMode = null, $lockVersion = null): ?CompetitorBase
    {
        return $this->_em->find($this->_entityName, $id, $lockMode, $lockVersion);
    }

    public function syncCompetitors(Tournament $tournament, Round $rootRound) {
        /**
         * @param Round $rootRound
         * @return array|Place[]
         */
        $getUnassignedPlaces = function(Round $rootRound): array {
            $unassignedPlaces = [];
            foreach( $rootRound->getPlaces() as $place ) {
                $unassignedPlaces[$place->getPoule()->getNumber() . "." . $place->getNumber() ] = true;
            }
            return $unassignedPlaces;
        };
        /**
         * @param Tournament $tournament
         * @param array|Place[] $unassignedPlaces
         * @return array|CompetitorBase[]
         */
        $getUnassignedCompetitors = function(Tournament $tournament, array $unassignedPlaces ): array {
            $unassignedCompetitors = [];
            foreach( $tournament->getCompetitors() as $competitor ) {
                $placeLocationId = $competitor->getPouleNr() . "." . $competitor->getPlaceNr();
                if( array_key_exists( $placeLocationId, $unassignedPlaces ) ) {
                    unset($unassignedPlaces[$placeLocationId]);
                } else {
                    $unassignedCompetitors[$placeLocationId] = $competitor;
                }
            }
            return $unassignedCompetitors;
        };

        $unassignedPlaces = $getUnassignedPlaces( $rootRound );
        $unassignedCompetitors = $getUnassignedCompetitors($tournament, $unassignedPlaces);

        foreach( $unassignedCompetitors as $unassignedCompetitor ) {

            if( count($unassignedPlaces) === 0 ) {
                $tournament->getCompetitors()->removeElement( $unassignedCompetitor );
                $this->remove($unassignedCompetitor);
            } else {
                $unassignedPlace = array_shift($unassignedPlaces);
                $unassignedCompetitor->setPouleNr( $unassignedPlace->getPoule()->getNumber() );
                $unassignedCompetitor->setPlaceNr( $unassignedPlace->getNumber());
                $this->save($unassignedCompetitor);
            }
        }
    }
}
