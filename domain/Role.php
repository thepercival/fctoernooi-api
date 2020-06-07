<?php

namespace FCToernooi;

class Role
{
    const ADMIN = 1;
    const ROLEADMIN = 2;
    const GAMERESULTADMIN = 4;
    const REFEREE = 8;
    const ALL = 15;

    public static function getName(int $role): string
    {
        if ($role === self::ADMIN) {
            return 'algemeen-beheerder';
        } else {
            if ($role === self::GAMERESULTADMIN) {
                return 'uitslagen-invoerder';
            } else {
                if ($role === self::ROLEADMIN) {
                    return 'rollen-beheerder';
                } else {
                    if ($role === self::REFEREE) {
                        return 'scheidsrechter';
                    }
                }
            }
        }
        return 'onbekend';
    }

    public static function getDefinitions(int $roles): array
    {
        $definitions = [];
        if (($roles & Role::ADMIN) === Role::ADMIN) {
            $definitions[] = [
                "name" => Role::getName(Role::ADMIN),
                "description" => 'kan alles behalve wat de andere rollen kunnen'
            ];
        }
        if (($roles & Role::ROLEADMIN) === Role::ROLEADMIN) {
            $definitions[] = [
                "name" => Role::getName(Role::ROLEADMIN),
                "description" => 'kan de gebruikers-rollen aanpassen'
            ];
        }
        if (($roles & Role::GAMERESULTADMIN) === Role::GAMERESULTADMIN) {
            $definitions[] = [
                "name" => Role::getName(Role::GAMERESULTADMIN),
                "description" => 'kan de uitslagen van alle wedstrijden aanpassen'
            ];
        }
        if (($roles & Role::REFEREE) === Role::REFEREE) {
            $definitions[] = [
                "name" => Role::getName(Role::REFEREE),
                "description" => 'kan de uitslagen van eigen wedstrijden aanpassen'
            ];
        }
        return $definitions;
    }
}