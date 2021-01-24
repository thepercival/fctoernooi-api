<?php

declare(strict_types=1);

namespace FCToernooi;

use SportsHelpers\Identifiable;

class Sponsor extends Identifiable
{

    /**
     * @var string
     */
    private $name;

    /**
     * @var string
     */
    private $url;

    /**
     * @var string|null
     */
    private $logoUrl;

    /**
     * @var int
     */
    private $screenNr;

    /**
     * @var Tournament
     */
    private $tournament;

    const MIN_LENGTH_NAME = 2;
    const MAX_LENGTH_NAME = 30;
    const MAX_LENGTH_URL = 100;

    public function __construct(Tournament $tournament, string $name)
    {
        $this->tournament = $tournament;
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

    public function setName(string $name)
    {
        if (strlen($name) < static::MIN_LENGTH_NAME or strlen($name) > static::MAX_LENGTH_NAME) {
            throw new \InvalidArgumentException(
                "de naam moet minimaal " . static::MIN_LENGTH_NAME . ' karakters bevatten en mag maximaal ' . static::MAX_LENGTH_NAME . " karakters bevatten",
                E_ERROR
            );
        }
        $this->name = $name;
    }

    /**
     * @return string
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * @param string|null $url
     */
    public function setUrl(string $url = null)
    {
        if ($url !== null && strlen($url) > 0) {
            if (strlen($url) > static::MAX_LENGTH_URL) {
                throw new \InvalidArgumentException(
                    "de url mag maximaal " . static::MAX_LENGTH_URL . " karakters bevatten",
                    E_ERROR
                );
            }
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                throw new \InvalidArgumentException("de url " . $url . " is niet valide (begin met https://)", E_ERROR);
            }
        }
        $this->url = $url;
    }

    /**
     * @return string|null
     */
    public function getLogoUrl()
    {
        return $this->logoUrl;
    }

    public function setLogoUrl(string $url = null )
    {
        if ($url !== null) {
            if (strlen($url) > static::MAX_LENGTH_URL) {
                throw new \InvalidArgumentException(
                    "de url mag maximaal " . static::MAX_LENGTH_URL . " karakters bevatten",
                    E_ERROR
                );
            }
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                throw new \InvalidArgumentException("de url " . $url . " is niet valide (begin met https://)", E_ERROR);
            }
        }
        $this->logoUrl = $url;
    }

    /**
     * @return int
     */
    public function getScreenNr()
    {
        return $this->screenNr;
    }

    /**
     * @param int $screenNr
     */
    public function setScreenNr($screenNr)
    {
        $this->screenNr = $screenNr;
    }
}
