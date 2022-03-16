<?php

declare(strict_types=1);

namespace FCToernooi\CreditAction;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use SportsHelpers\EnumDbType;

class NameType extends EnumDbType
{
    public static function getNameHelper(): string
    {
        return 'enum_CreditAction';
    }

    public function convertToPHPValue($value, AbstractPlatform $platform)
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

    public function getSQLDeclaration(array $column, AbstractPlatform $platform)
    {
        return 'varchar(20)';
    }
}
