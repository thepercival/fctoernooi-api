<?php

declare(strict_types=1);

namespace FCToernooi\Database\enums;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use FCToernooi\CreditAction\Name;
use SportsHelpers\DbEnums\EnumDbType;

/**
 * @api
 */
final class CreditActionNameEnumDbType extends EnumDbType
{
    #[\Override]
    public static function getNameHelper(): string
    {
        return 'enum_CreditAction';
    }
    #[\Override]
    public function convertToPHPValue($value, AbstractPlatform $platform): Name|null
    {
        if ($value === Name::Buy->value) {
            return Name::Buy;
        }
        if ($value === Name::ValidateReward->value) {
            return Name::ValidateReward;
        }
        if ($value === Name::CreateTournament->value) {
            return Name::CreateTournament;
        }
        return null;
    }

    #[\Override]
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'varchar(20)';
    }
}
