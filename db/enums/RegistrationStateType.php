<?php

declare(strict_types=1);

namespace FCToernooi\Database\enums;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use FCToernooi\Tournament\Registration\State;
use SportsHelpers\DbEnums\EnumDbType;

/**
 * @api
 */
final class RegistrationStateType extends EnumDbType
{
    #[\Override]
    public static function getNameHelper(): string
    {
        return 'enum_RegistrationState';
    }

    #[\Override]
    public function convertToPHPValue($value, AbstractPlatform $platform): State|null
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

    #[\Override]
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'VARCHAR(10)';
    }
}
