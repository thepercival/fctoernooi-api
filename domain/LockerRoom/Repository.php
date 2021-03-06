<?php
declare(strict_types=1);

namespace FCToernooi\LockerRoom;

use SportsHelpers\Repository\SaveRemove as SaveRemoveRepository;
use SportsHelpers\Repository as BaseRepository;
use Doctrine\DBAL\ConnectionException;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use FCToernooi\LockerRoom;
use FCToernooi\LockerRoom as LockerRoomBase;
use \Doctrine\Common\Collections\ArrayCollection;
use FCToernooi\Competitor;

/**
 * @template-extends EntityRepository<LockerRoomBase>
 * @template-implements SaveRemoveRepository<LockerRoomBase>
 */
class Repository extends EntityRepository implements SaveRemoveRepository
{
    use BaseRepository;

    /**
     * @param LockerRoomBase $lockerRoom
     * @param ArrayCollection<int|string, Competitor> $newCompetitors
     * @throws ConnectionException
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function updateCompetitors(LockerRoom $lockerRoom, ArrayCollection $newCompetitors): void
    {
        $conn = $this->_em->getConnection();
        $conn->beginTransaction();
        try {
            // remove

            while ($competitor = $lockerRoom->getCompetitors()->first()) {
                $lockerRoom->getCompetitors()->removeElement($competitor);
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
        } catch (\Exception $exception) {
            $conn->rollBack();
            throw $exception;
        }
    }
}
