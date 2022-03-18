<?php

declare(strict_types=1);

namespace FCToernooi;

use DateTimeImmutable;
use FCToernooi\CreditAction\Name as CreditActionName;
use SportsHelpers\Identifiable;

class CreditAction extends Identifiable
{
    protected string|null $paymentId = null;

    public function __construct(
        protected User $user,
        protected CreditActionName $action,
        protected int $nrOfCredits,
        protected DateTimeImmutable $atDateTime
    ) {
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getCreditActionName(): CreditActionName
    {
        return $this->action;
    }

    public function getNrOfCredits(): int
    {
        return $this->nrOfCredits;
    }

    public function getAtDateTime(): DateTimeImmutable
    {
        return $this->atDateTime;
    }

    public function getPaymentId(): string|null
    {
        return $this->paymentId;
    }

    public function setPaymentId(string $paymentId): void
    {
        $this->paymentId = $paymentId;
    }
}
