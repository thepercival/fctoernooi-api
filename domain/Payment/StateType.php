<?php

namespace FCToernooi\Payment;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use FCToernooi\Payment\State as PaymentState;
use SportsHelpers\EnumDbType;

class StateType extends EnumDbType
{
    public static function getNameHelper(): string
    {
        return 'enum_PaymentState';
    }

    public function convertToPHPValue($value, AbstractPlatform $platform)
    {
        switch ($value) {
            case PaymentState::Open->value:
                return PaymentState::Open;
            case PaymentState::Pending->value:
                return PaymentState::Pending;
            case PaymentState::Paid->value:
                return PaymentState::Paid;
            case PaymentState::Failed->value:
                return PaymentState::Failed;
            case PaymentState::Canceled->value:
                return PaymentState::Canceled;
            case PaymentState::Expired->value:
                return PaymentState::Expired;
        }
        return null;
    }

    public function getSQLDeclaration(array $column, AbstractPlatform $platform)
    {
        return 'varchar(10)';
    }
}