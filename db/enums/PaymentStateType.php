<?php

namespace FCToernooi\Database\enums;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use FCToernooi\Payment\State as PaymentState;
use SportsHelpers\DbEnums\EnumDbType;

/**
 * @api
 */
final class PaymentStateType extends EnumDbType
{
    #[\Override]
    public static function getNameHelper(): string
    {
        return 'enum_PaymentState';
    }

    #[\Override]
    public function convertToPHPValue($value, AbstractPlatform $platform): PaymentState|null
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

    #[\Override]
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'varchar(10)';
    }
}