<?php

declare(strict_types=1);

namespace App\Repositories;

use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\ConnectionException;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Exception\ORMException;
use FCToernooi\Competitor;
use FCToernooi\LockerRoom;
use FCToernooi\LockerRoom as LockerRoomBase;

/**
 * @api
 * @template-extends EntityRepository<LockerRoomBase>
 */
final class LockerRoomRepository extends EntityRepository
{
    /**
     * @param LockerRoom $lockerRoom
     * @param Collection<int|string, Competitor> $newCompetitors
     * @throws ConnectionException
     * @throws ORMException
     */
    public function updateCompetitors(LockerRoom $lockerRoom, Collection $newCompetitors): void
    {
        $conn = $this->getEntityManager()->getConnection();
        $conn->beginTransaction();
        try {
            // remove

            while ($competitor = $lockerRoom->getCompetitors()->first()) {
                $lockerRoom->getCompetitors()->removeElement($competitor);
            }
            // $lockerRoom->getCompetitors()->clear();
            $this->getEntityManager()->flush();

            $competitors = $lockerRoom->getTournament()->getCompetitors();

            foreach ($newCompetitors as $newCompetitor) {
                $foundCompetitors = array_filter(
                    $competitors->toArray(),
                    function (Competitor $competitorIt) use ($newCompetitor): bool {
                        return $newCompetitor->getName() === $competitorIt->getName();
                    }
                );
                $competitor = reset($foundCompetitors);
                if ($competitor === false) {
                    continue;
                }
                $lockerRoom->getCompetitors()->add($competitor);
            }
            $this->getEntityManager()->persist($lockerRoom);

            $this->getEntityManager()->flush();
            $conn->commit();
        } catch (\Exception $exception) {
            $conn->rollBack();
            throw $exception;
        }
    }
}
