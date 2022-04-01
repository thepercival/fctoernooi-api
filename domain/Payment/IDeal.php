<?php

declare(strict_types=1);

namespace FCToernooi\Payment;

use FCToernooi\Payment;
use FCToernooi\User;
use Mollie\Api\Types\PaymentMethod;

class IDeal extends Payment
{
    public function __construct(User $user, string $id, protected IDealIssuer $issuer, float $amount)
    {
        parent::__construct($user, $id, PaymentMethod::IDEAL, $amount);
    }

    public function getIssuer(): IDealIssuer
    {
        return $this->issuer;
    }



//    public function setMethodNative(string $method): void
//    {
//        $this->method = PaymentMethod::from($method);
//    }
}
