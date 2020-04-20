<?php
/**
 * Created by PhpStorm.
 * User: coen
 * Date: 22-5-18
 * Time: 12:14
 */

namespace FCToernooi\LockerRoom;

use FCToernooi\LockerRoom;
use FCToernooi\LockerRoom as LockerRoomBase;
use \Doctrine\Common\Collections\ArrayCollection;
use FCToernooi\Tournament;
use Voetbal\Competitor;
use Voetbal\Structure;
use Voetbal\Structure\Repository as StructureRepository;

/**
 * Class Repository
 * @package FCToernooi\Sponsor
 */
class Repository extends \Voetbal\Repository
{
    public function find($id, $lockMode = null, $lockVersion = null): ?LockerRoomBase
    {
        return $this->_em->find($this->_entityName, $id, $lockMode, $lockVersion);
    }

    public function update(Tournament $tournament, ArrayCollection $newLockerRooms): array
    {
        $conn = $this->_em->getConnection();
        $conn->beginTransaction();
        try {
            // remove
            {
                $lockerRooms = $tournament->getLockerRooms();
                foreach ($lockerRooms as $lockerRoom) {
                    $this->_em->remove($lockerRoom);
                }
                $lockerRooms->clear();
            }
            $this->_em->flush();

            // add
            $structureRepos = new StructureRepository($this->_em);
            $structure = $structureRepos->getStructure($tournament->getCompetition());
            $competitors = $structure ? $structure->getFirstRoundNumber()->getCompetitors() : [];
            foreach ($newLockerRooms as $newLockerRoom) {
                $realNewLockerRoom = new LockerRoom($tournament, $newLockerRoom->getName());
                foreach ($newLockerRoom->getCompetitorIds() as $competitorId) {
                    $foundCompetitors = array_filter(
                        $competitors,
                        function (Competitor $competitorIt) use ($competitorId) {
                            return $competitorId === $competitorIt->getId();
                        }
                    );
                    $competitor = reset($foundCompetitors);
                    if (!$competitor) {
                        continue;
                    }
                    $realNewLockerRoom->getCompetitors()->add($competitor);
                }
                // $refereeRole->setValue(Role::REFEREE);
                $this->_em->persist($realNewLockerRoom);
            }
            $lockerRoomsRet = array_values($tournament->getLockerRooms()->toArray());

            $this->_em->flush();
            $conn->commit();
            return $lockerRoomsRet;
        } catch (\Exception $e) {
            $conn->rollBack();
            throw $e;
        }
    }
}
