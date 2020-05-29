<?php

namespace FCToernooi;

class Role
{
    const ADMIN = 1;
    const ROLEADMIN = 2;
    const GAMERESULTADMIN = 4;
    const REFEREE = 8;
    const ALL = 15;

    public static function getDescription(int $role): string
    {
        if ($role === self::ADMIN) {
            return 'beheerder algemeen';
        } else {
            if ($role === self::GAMERESULTADMIN) {
                return 'beheerder uitslagen';
            } else {
                if ($role === self::ROLEADMIN) {
                    return 'beheerder rollen';
                } else {
                    if ($role === self::REFEREE) {
                        return 'scheidsrchter';
                    }
                }
            }
        }
        return 'onbekend';
    }
}