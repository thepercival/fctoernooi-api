<?php

declare(strict_types=1);

namespace FCToernooi;

use SportsHelpers\Identifiable;

class Sponsor extends Identifiable
{
    private string $name;
    private string|null $url = null;
    private string|null $logoUrl = null;
    private int $screenNr = 0;

    public const MIN_LENGTH_NAME = 2;
    public const MAX_LENGTH_NAME = 30;
    public const MAX_LENGTH_URL = 100;

    public function __construct(private Tournament $tournament, string $name)
    {
        $this->setName($name);
    }

    public function getTournament(): Tournament
    {
        return $this->tournament;
    }

    public function getName(): string
    {
        return $this->name;
    }

    final public function setName(string $name): void
    {
        if (strlen($name) < self::MIN_LENGTH_NAME or strlen($name) > self::MAX_LENGTH_NAME) {
            throw new \InvalidArgumentException(
                "de naam moet minimaal " . self::MIN_LENGTH_NAME . ' karakters bevatten en mag maximaal ' . self::MAX_LENGTH_NAME . " karakters bevatten",
                E_ERROR
            );
        }
        $this->name = $name;
    }

    public function getUrl(): string|null
    {
        return $this->url;
    }

    public function setUrl(string $url = null): void
    {
        if ($url !== null && strlen($url) > 0) {
            if (strlen($url) > self::MAX_LENGTH_URL) {
                throw new \InvalidArgumentException(
                    "de url mag maximaal " . self::MAX_LENGTH_URL . " karakters bevatten",
                    E_ERROR
                );
            }
            if (filter_var($url, FILTER_VALIDATE_URL) === false) {
                throw new \InvalidArgumentException("de url " . $url . " is niet valide (begin met https://)", E_ERROR);
            }
        }
        $this->url = $url;
    }

    public function getLogoUrl(): string|null
    {
        return $this->logoUrl;
    }

    public function setLogoUrl(string $url = null): void
    {
        if ($url !== null) {
            if (strlen($url) > self::MAX_LENGTH_URL) {
                throw new \InvalidArgumentException(
                    'de url mag maximaal ' . self::MAX_LENGTH_URL . ' karakters bevatten',
                    E_ERROR
                );
            }
            if (filter_var($url, FILTER_VALIDATE_URL) === false) {
                throw new \InvalidArgumentException('de url ' . $url . ' is niet valide (begin met https://)', E_ERROR);
            }
        }
        $this->logoUrl = $url;
    }

    public function getScreenNr(): int
    {
        return $this->screenNr;
    }

    public function setScreenNr(int $screenNr): void
    {
        $this->screenNr = $screenNr;
    }
}
