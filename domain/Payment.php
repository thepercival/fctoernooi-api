<?php

declare(strict_types=1);

namespace FCToernooi;

use FCToernooi\Payment\State as PaymentState;
use SportsHelpers\Identifiable;

class Payment extends Identifiable
{
    protected PaymentState $state = PaymentState::Open;
    protected \DateTimeImmutable $updatedAt;
    public const EUROS_PER_CREDIT = 0.5;


    public function __construct(
        protected User $user,
        protected string|null $paymentId,
        protected string $method,
        protected string $amount
    ) {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getPaymentId(): string|null
    {
        return $this->paymentId;
    }

    public function setPaymentId(string $paymentId): void
    {
        $this->paymentId = $paymentId;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getAmount(): float
    {
        return (float)$this->amount;
    }

    public function getState(): PaymentState
    {
        return $this->state;
    }

    public function setState(PaymentState $state): void
    {
        $this->state = $state;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function calculateNrOfCredits(): int
    {
        return (int)($this->getAmount() / self::EUROS_PER_CREDIT);
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
