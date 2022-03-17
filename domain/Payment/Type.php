<?php

declare(strict_types=1);

namespace FCToernooi\Payment;

enum Type: string
{
    case IDeal = 'IDeal';
    case CreditCard = 'CreditCard';
}