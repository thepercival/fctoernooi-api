<?php

declare(strict_types=1);

namespace FCToernooi\Tournament\Registration;

use SportsHelpers\EnumDbType;
use Doctrine\DBAL\Platforms\AbstractPlatform;

class StateType extends EnumDbType
{
    public static function getNameHelper(): string
    {
        return 'enum_RegistrationState';
    }

    public function convertToPHPValue($value, AbstractPlatform $platform)
    {
        if ($value === State::Created->value) {
            return State::Created;
        }
        if ($value === State::Accepted->value) {
            return State::Accepted;
        }
        if ($value === State::Substitute->value) {
            return State::Substitute;
        }
        if ($value === State::Declined->value) {
            return State::Declined;
        }
        return null;
    }

    public function getSQLDeclaration(array $column, AbstractPlatform $platform)
    {
        return 'varchar(10)';
    }
}
