<?php

declare(strict_types=1);

namespace App\Repositories\Sports;

use Doctrine\ORM\EntityRepository;
use Sports\Sport;

/**
 * @template-extends EntityRepository<Sport>
 */
final class SportRepository extends EntityRepository
{
    /**
     * @param bool|null $withCustomId
     * @return list<Sport>
     */
    public function findByExt(bool|null $withCustomId = null): array
    {
        $qb = $this->createQueryBuilder('s');
        if ($withCustomId !== null) {
            $operator = $withCustomId ? '>' : '=';
            $qb = $qb->andWhere('s.customId ' . $operator . ' 0');
        }
        /** @var list<Sport> $results */
        $results = $qb->getQuery()->getResult();
        return $results;
    }
}
