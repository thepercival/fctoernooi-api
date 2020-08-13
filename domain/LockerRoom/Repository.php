<?php

namespace FCToernooi\LockerRoom;

use FCToernooi\LockerRoom;
use FCToernooi\LockerRoom as LockerRoomBase;
use \Doctrine\Common\Collections\ArrayCollection;
use FCToernooi\Competitor;

class Repository extends \Sports\Repository
{
    public function find($id, $lockMode = null, $lockVersion = null): ?LockerRoomBase
    {
        return $this->_em->find($this->_entityName, $id, $lockMode, $lockVersion);
    }

    public function updateCompetitors(LockerRoom $lockerRoom, ArrayCollection $newCompetitors)
    {
        $conn = $this->_em->getConnection();
        $conn->beginTransaction();
        try {
            // remove
            while ($lockerRoom->getCompetitors()->count() > 0) {
                $lockerRoom->getCompetitors()->removeElement($lockerRoom->getCompetitors()->first());
            }
            // $lockerRoom->getCompetitors()->clear();
            $this->_em->flush();

            $competitors = $lockerRoom->getTournament()->getCompetitors();

            foreach ($newCompetitors as $newCompetitor) {
                $foundCompetitors = array_filter(
                    $competitors->toArray(),
                    function (Competitor $competitorIt) use ($newCompetitor): bool {
                        return $newCompetitor->getName() === $competitorIt->getName();
                    }
                );
                $competitor = reset($foundCompetitors);
                if (!$competitor) {
                    continue;
                }
                $lockerRoom->getCompetitors()->add($competitor);
            }
            $this->_em->persist($lockerRoom);

            $this->_em->flush();
            $conn->commit();
        } catch (\Exception $e) {
            $conn->rollBack();
            throw $e;
        }
    }
}
