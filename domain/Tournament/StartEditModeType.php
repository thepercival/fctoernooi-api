<?php

declare(strict_types=1);

namespace FCToernooi\Tournament;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use SportsHelpers\EnumDbType;

class StartEditModeType extends EnumDbType
{
    public static function getNameHelper(): string
    {
        return 'enum_StartEditMode';
    }

    public function convertToPHPValue($value, AbstractPlatform $platform)
    {
        if ($value === StartEditMode::EditLongTerm->value) {
            return StartEditMode::EditLongTerm;
        }
        if ($value === StartEditMode::EditShortTerm->value) {
            return StartEditMode::EditShortTerm;
        }
        if ($value === StartEditMode::ReadOnly->value) {
            return StartEditMode::ReadOnly;
        }
        return null;
    }

    public function getSQLDeclaration(array $column, AbstractPlatform $platform)
    {
        return 'varchar(20)';
    }
}
