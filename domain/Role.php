<?php

declare(strict_types=1);

namespace FCToernooi;

class Role
{
    public const ADMIN = 1;
    public const ROLEADMIN = 2;
    public const GAMERESULTADMIN = 4;
    public const REFEREE = 8;
    public const ALL = 15;

    public static function getName(int $role): string
    {
        if ($role === self::ADMIN) {
            return 'algemeen-beheerder';
        } elseif ($role === self::GAMERESULTADMIN) {
            return 'uitslagen-invoerder';
        } elseif ($role === self::ROLEADMIN) {
            return 'rollen-beheerder';
        } elseif ($role === self::REFEREE) {
            return 'scheidsrechter';
        }
        return 'onbekend';
    }

    /**
     * @param int $roles
     * @return list<array<string, string>>
     */
    public static function getDefinitions(int $roles): array
    {
        $definitions = [];
        if (($roles & Role::ADMIN) === Role::ADMIN) {
            $definitions[] = [
                'name' => Role::getName(Role::ADMIN),
                'description' => 'kan alles behalve wat de andere rollen kunnen'
            ];
        }
        if (($roles & Role::ROLEADMIN) === Role::ROLEADMIN) {
            $definitions[] = [
                'name' => Role::getName(Role::ROLEADMIN),
                'description' => 'kan de gebruikers-rollen aanpassen'
            ];
        }
        if (($roles & Role::GAMERESULTADMIN) === Role::GAMERESULTADMIN) {
            $definitions[] = [
                'name' => Role::getName(Role::GAMERESULTADMIN),
                'description' => 'kan de uitslagen van alle wedstrijden aanpassen'
            ];
        }
        if (($roles & Role::REFEREE) === Role::REFEREE) {
            $definitions[] = [
                'name' => Role::getName(Role::REFEREE),
                'description' => 'kan de uitslagen van eigen wedstrijden aanpassen'
            ];
        }
        return $definitions;
    }
}
