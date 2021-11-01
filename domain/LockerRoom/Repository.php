<?php
declare(strict_types=1);

namespace FCToernooi\LockerRoom;

use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\ConnectionException;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use FCToernooi\Competitor;
use FCToernooi\LockerRoom;
use FCToernooi\LockerRoom as LockerRoomBase;
use SportsHelpers\Repository as BaseRepository;

/**
 * @template-extends EntityRepository<LockerRoomBase>
 */
class Repository extends EntityRepository
{
    /**
     * @use BaseRepository<LockerRoomBase>
     */
    use BaseRepository;

    /**
     * @param LockerRoomBase $lockerRoom
     * @param Collection<int|string, Competitor> $newCompetitors
     * @throws ConnectionException
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function updateCompetitors(LockerRoom $lockerRoom, Collection $newCompetitors): void
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
