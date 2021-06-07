<?php
declare(strict_types=1);

namespace App\Export;

use FCToernooi\Tournament\ExportConfig;

class TournamentConfig
{
    public function __construct(private int $value) {
    }

    public function getGamenotes(): bool
    {
        return ($this->value & ExportConfig::GameNotes) === ExportConfig::GameNotes;
    }

    public function getStructure(): bool
    {
        return ($this->value & ExportConfig::Structure) === ExportConfig::Structure;
    }

    public function getGamesPerPoule(): bool
    {
        return ($this->value & ExportConfig::GamesPerPoule) === ExportConfig::GamesPerPoule;
    }

    public function getGamesperfield(): bool
    {
        return ($this->value & ExportConfig::GamesPerField) === ExportConfig::GamesPerField;
    }

    public function getPlanning(): bool
    {
        return ($this->value & ExportConfig::Planning) === ExportConfig::Planning;
    }

    public function getPoulePivotTables(): bool
    {
        return ($this->value & ExportConfig::PoulePivotTables) === ExportConfig::PoulePivotTables;
    }

    public function getQrCode(): bool
    {
        return ($this->value & ExportConfig::QrCode) === ExportConfig::QrCode;
    }

    public function getLockerRooms(): bool
    {
        return ($this->value & ExportConfig::LockerRooms) === ExportConfig::LockerRooms;
    }

    public function allOptionsOff(): bool
    {
        return $this->value === 0;
    }

    public function hasOnly(int $option): bool
    {
        return $this->value === $option;
    }
}
