<?php

declare(strict_types=1);

namespace FCToernooi;

use SportsHelpers\Identifiable;

class Payment extends Identifiable
{
    protected string $state = 'created';
    protected \DateTimeImmutable $updatedAt;
    public const EUROS_PER_CREDIT = 0.5;

    public function __construct(
        protected User $user,
        protected string $paymentId,
        protected string $method,
        protected float $amount
    ) {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getPaymentId(): string
    {
        return $this->paymentId;
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
        return $this->amount;
    }

    public function getState(): string
    {
        return $this->state;
    }

    public function setState(string $state): void
    {
        $this->state = $state;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function calculateNrOfCredits(): int
    {
        return (int)($this->amount / self::EUROS_PER_CREDIT);
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
