<?php

declare(strict_types=1);

namespace FCToernooi\Payment;

use FCToernooi\Payment;
use FCToernooi\User;
use Mollie\Api\Types\PaymentMethod;

class CreditCard extends Payment
{
    public function __construct(
        User $user,
        string $id,
        protected string $cardNumber,
        protected string $cvc,
        float $amount
    ) {
        parent::__construct($user, $id, PaymentMethod::CREDITCARD, $amount);
    }

    public function getCardNumber(): string
    {
        return $this->cardNumber;
    }

    public function getCvc(): string
    {
        return $this->cvc;
    }
}