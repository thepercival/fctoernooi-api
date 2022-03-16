<?php

declare(strict_types=1);

namespace FCToernooi\CreditAction;

use DateTimeImmutable;
use Doctrine\ORM\EntityRepository;
use FCToernooi\CreditAction;
use FCToernooi\CreditAction\Name as CreditActionName;
use FCToernooi\User;
use FCToernooi\User\Repository as UserRepository;
use SportsHelpers\Repository as BaseRepository;

/**
 * @template-extends EntityRepository<CreditAction>
 */
class Repository extends EntityRepository
{
    /**
     * @use BaseRepository<CreditAction>
     */
    use BaseRepository;

    public function doAction(
        User $user,
        CreditActionName $action,
        int $nrOfCredits,
        DateTimeImmutable|null $atDateTime = null
    ): CreditAction {
        if ($atDateTime === null) {
            $atDateTime = new DateTimeImmutable();
        }
        $creditAction = new CreditAction($user, $action, $nrOfCredits, $atDateTime);
        $this->save($creditAction, true);
        if (!$user->getValidated()) {
            $user->setValidateIn($user->getValidateIn() + $nrOfCredits);
        }
        $user->setNrOfCredits($user->getNrOfCredits() + $nrOfCredits);

        $metaData = $this->getEntityManager()->getClassMetadata(User::class);
        $userRepos = new UserRepository($this->getEntityManager(), $metaData);

        $userRepos->save($user, true);
        return $creditAction;
    }
}
