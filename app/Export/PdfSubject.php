<?php

declare(strict_types=1);

namespace App\Export;

enum PdfSubject: int
{
    case Structure = 1;
    case PoulePivotTables = 2;
    case Planning = 4;
    case GamesPerPoule = 8;
    case GamesPerField = 16;
    case GameNotes = 32;
    case LockerRooms = 64;
    case QrCode = 128;

    public static function all(): int
    {
        return 255;
    }

    /**
     * @param int $subjects
     * @return list<self>
     */
    public static function toFilteredArray(int $subjects): array
    {
        return array_values(array_filter(self::cases(), function (PdfSubject $subject) use ($subjects): bool {
            return (($subject->value & $subjects) === $subject->value);
        }));
    }

    /**
     * @param non-empty-list<self> $subjects
     * @return int
     */
    public static function sum(array $subjects): int
    {
        return array_sum(
            array_map(function (PdfSubject $subject): int {
                return $subject->value;
            }, $subjects)
        );
    }
}
