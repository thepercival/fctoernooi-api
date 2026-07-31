<?php

declare(strict_types=1);

namespace FCToernooi\Database\enums;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use FCToernooi\Tournament\StartEditMode;
use SportsHelpers\DbEnums\EnumDbType;

/**
 * @api
 */
final class StartEditModeType extends EnumDbType
{
    #[\Override]
    public static function getNameHelper(): string
    {
        return 'enum_StartEditMode';
    }

    #[\Override]
    public function convertToPHPValue($value, AbstractPlatform $platform): StartEditMode|null
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

    #[\Override]
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'VARCHAR(20)';
    }
}
