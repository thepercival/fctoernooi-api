<?php

namespace App;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\DateTimeImmutableType;

final class UTCDateTimeType extends DateTimeImmutableType
{
    /**
     * @var \DateTimeZone|null
     */
    private static $utc;

    #[\Override]
    public function convertToDatabaseValue($value, AbstractPlatform $platform): string|null
    {
//        if ($value instanceof \DateTimeImmutable && $value->getTimezone() === ) {
//            $value = $value->setTimezone(self::getUtc());
//        }

        return parent::convertToDatabaseValue($value, $platform);
    }

    private static function getUtc(): \DateTimeZone
    {
        return self::$utc ?: self::$utc = new \DateTimeZone('UTC');
    }

    #[\Override]
    public function convertToPHPValue($value, AbstractPlatform $platform): \DateTimeImmutable|null
    {
        if (null === $value || $value instanceof \DateTimeImmutable) {
            return $value;
        }

        $converted = \DateTimeImmutable::createFromFormat(
            $platform->getDateTimeFormatString(),
            $value,
            self::getUtc()
        );

        if (!$converted) {
            throw new ConversionException($value . ' is not a valid UTC date.');
        }

        return $converted;
    }
}