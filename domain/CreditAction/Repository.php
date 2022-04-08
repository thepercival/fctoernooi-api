<?php

declare(strict_types=1);

namespace FCToernooi\CreditAction;

use Doctrine\ORM\EntityRepository;
use FCToernooi\CreditAction;
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

    public const CREATE_ACCOUNT_CREDITS = 3;
    public const VALIDATE_ACCOUNT_CREDITS = 3;
    public const NR_OF_CREDITS_PER_TOURNAMENT = 1;

//    public function addCreateAccountCredits(User $user): CreditAction
//    {
//        $creditAction = new CreditAction(
//            $user,
//            CreditActionName::CreateAccountReward,
//            self::CREATE_ACCOUNT_CREDITS,
//            new DateTimeImmutable(),
//            null
//        );
//        $this->save($creditAction, true);
//
//        $user->setNrOfCredits(self::CREATE_ACCOUNT_CREDITS);
//
//        $metaData = $this->getEntityManager()->getClassMetadata(User::class);
//        $userRepos = new UserRepository($this->getEntityManager(), $metaData);
//        $userRepos->save($user, true);
//
//        return $creditAction;
//    }
//
//    public function addValidateCredits(User $user, DateTimeImmutable|null $atDateTime = null): CreditAction
//    {
//        if ($atDateTime === null) {
//            $atDateTime = new DateTimeImmutable();
//        }
//
//        $creditAction = new CreditAction(
//            $user,
//            CreditActionName::ValidateReward,
//            self::VALIDATE_ACCOUNT_CREDITS,
//            $atDateTime,
//            null
//        );
//        $this->save($creditAction, true);
//
//        $user->setNrOfCredits(self::VALIDATE_ACCOUNT_CREDITS);
//        $metaData = $this->getEntityManager()->getClassMetadata(User::class);
//        $userRepos = new UserRepository($this->getEntityManager(), $metaData);
//        $userRepos->save($user, true);
//
//        return $creditAction;
//    }
//
//    public function buyCredits(
//        Payment $payment,
//        DateTimeImmutable|null $atDateTime = null
//    ): CreditAction {
//        if ($atDateTime === null) {
//            $atDateTime = new DateTimeImmutable();
//        }
//        $user = $payment->getUser();
//
//        $creditAction = new CreditAction(
//            $user,
//            CreditActionName::Buy,
//            $payment->calculateNrOfCredits(),
//            $atDateTime,
//            $payment
//        );
//        $this->save($creditAction, true);
//
//        $user->setNrOfCredits($user->getNrOfCredits() + $payment->calculateNrOfCredits());
//
//        $metaData = $this->getEntityManager()->getClassMetadata(User::class);
//        $userRepos = new UserRepository($this->getEntityManager(), $metaData);
//
//        $userRepos->save($user, true);
//
//        return $creditAction;
//    }
//
//    public function cancelCredits(
//        Payment $payment,
//        DateTimeImmutable|null $atDateTime = null
//    ): void {
//        if ($atDateTime === null) {
//            $atDateTime = new DateTimeImmutable();
//        }
//        $user = $payment->getUser();
//
//        $creditAction = $this->findOneBy(['payment', $payment]);
//        if ($creditAction !== null) {
//            $this->remove($creditAction, true);
//        }
//
//        $user->setNrOfCredits($user->getNrOfCredits() - $payment->calculateNrOfCredits());
//        $metaData = $this->getEntityManager()->getClassMetadata(User::class);
//        $userRepos = new UserRepository($this->getEntityManager(), $metaData);
//        $userRepos->save($user, true);
//    }
//
//    public function removeCreateTournamentCredits(
//        User $user,
//        DateTimeImmutable|null $atDateTime = null
//    ): CreditAction {
//        if ($atDateTime === null) {
//            $atDateTime = new DateTimeImmutable();
//        }
//
//        $creditAction = new CreditAction(
//            $user,
//            CreditActionName::CreateTournament,
//            self::NR_OF_CREDITS_PER_TOURNAMENT,
//            $atDateTime
//        );
//        $this->save($creditAction, true);
//
//        if (!$user->getValidated()) {
//            $user->setValidateIn($user->getValidateIn() - self::NR_OF_CREDITS_PER_TOURNAMENT);
//        }
//        $user->setNrOfCredits($user->getNrOfCredits() - self::NR_OF_CREDITS_PER_TOURNAMENT);
//        $metaData = $this->getEntityManager()->getClassMetadata(User::class);
//        $userRepos = new UserRepository($this->getEntityManager(), $metaData);
//        $userRepos->save($user, true);
//
//        return $creditAction;
//    }
}
