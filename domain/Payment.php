<?php

declare(strict_types=1);

namespace FCToernooi;

use FCToernooi\Payment\Type as PaymentType;

class Payment
{
    public function __construct(
        private User $user,
        private PaymentType $type,
        private int $nrOfCredits
    ) {
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getType(): PaymentType
    {
        return $this->type;
    }

    public function getNrOfCredits(): int
    {
        return $this->nrOfCredits;
    }

    public function getTypeNative(): string
    {
        return $this->type->value;
    }

    public function setTypeNative(string $type): void
    {
        $this->type = PaymentType::from($type);
    }
}
