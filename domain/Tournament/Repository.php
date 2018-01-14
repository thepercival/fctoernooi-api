<?php
/**
 * Created by PhpStorm.
 * User: coen
 * Date: 10-10-17
 * Time: 12:27
 */

namespace FCToernooi\Tournament;

use FCToernooi\Tournament;

/**
 * Class Repository
 * @package Voetbal\Competitionseason
 */
class Repository extends \Voetbal\Repository
{
    public function merge( Tournament $tournament )
    {
        return $this->_em->merge( $tournament );
    }

    public function findByPeriod(
        \DateTimeImmutable $startDateTime = null,
        \DateTimeImmutable $endDateTime = null
    )
    {
        $query = $this->createQueryBuilder('t')
            ->join("t.competitionseason","cs");

        if( $startDateTime !== null ) {
            $query = $query
                ->where('cs.startDateTime >= :date')
                ->setParameter('date', $startDateTime);
        }

            // ->andWhere('s.begindatum is null or lidm.einddatum >= :date');

        if( $endDateTime !== null ) {
            $query = $query
                ->where('cs.startDateTime <= :date')
                ->setParameter('date', $endDateTime);
        }

        return $query->getQuery()->getResult();
    }

}