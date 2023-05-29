<?php

declare(strict_types=1);

namespace FCToernooi\Payment;

enum State: string
{
    case Open = 'open';
    case Pending = 'pending';
    case Paid = 'paid';
    case Failed = 'failed';
    case Canceled = 'canceled';
    case Expired = 'expired';

    public static function getValue(string $state): self
    {
        switch ($state) {
            case self::Open->value:
                return self::Open;
            case self::Pending->value:
                return self::Pending;
            case self::Paid->value:
                return self::Paid;
            case self::Failed->value:
                return self::Failed;
            case self::Canceled->value:
                return self::Canceled;
            case self::Expired->value:
                return self::Expired;
        }
        throw new \Exception('an unknown payment state');
    }
}