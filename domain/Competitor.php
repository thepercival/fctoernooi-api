<?php

declare(strict_types=1);

namespace FCToernooi;

use InvalidArgumentException;
use Sports\Competition;
use Sports\Competitor as SportsCompetitor;
use Sports\Competitor\StartLocation;

class Competitor extends StartLocation implements SportsCompetitor
{

    public const MAX_LENGTH_TELEPHONE = 14;
    public const MAX_LENGTH_INFO = 200;
    public const IMG_FOLDER = 'competitors';

    protected int|string|null $id = null;
    protected bool $present = false;
    private string|null $emailaddress = null;
    private string|null $telephone = null;

    protected string|null $logoExtension = null;
    protected string|null $publicInfo = null;
    protected string|null $privateInfo = null;

    protected string $name;

    public function __construct(protected Tournament $tournament, StartLocation $startLocation, string $name)
    {
        parent::__construct($startLocation->getCategoryNr(), $startLocation->getPouleNr(), $startLocation->getPlaceNr());
        if (!$tournament->getCompetitors()->contains($this)) {
            $tournament->getCompetitors()->add($this);
        }
        $this->setName($name);
    }

    public function getId(): int|string|null
    {
        return $this->id;
    }

    public function setId(int|string|null $id): void
    {
        $this->id = $id;
    }

    public function getTournament(): Tournament
    {
        return $this->tournament;
    }

    public function getCompetition(): Competition
    {
        return $this->tournament->getCompetition();
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getEmailaddress(): string|null
    {
        return $this->emailaddress;
    }

    public function setEmailaddress(string|null $emailaddress): void
    {
        $this->emailaddress = $emailaddress;
    }

    public function getTelephone(): string|null
    {
        return $this->telephone;
    }

    public function setTelephone(string|null $telephone): void
    {
        $this->telephone = $telephone;
    }

    public function getPresent(): bool
    {
        return $this->present;
    }

    public function setPresent(bool $present): void
    {
        $this->present = $present;
    }

    public function getLogoExtension(): string|null
    {
        return $this->logoExtension;
    }

    public function setLogoExtension(string|null $logoExtension): void
    {
        $this->logoExtension = $logoExtension;
    }


    public function getPublicInfo(): ?string
    {
        return $this->publicInfo;
    }

    public function setPublicInfo(string $publicInfo = null): void
    {
        if ($publicInfo !== null && strlen($publicInfo) === 0) {
            $publicInfo = null;
        }
        if ($publicInfo !== null && strlen($publicInfo) > self::MAX_LENGTH_INFO) {
            throw new InvalidArgumentException('de extra-info mag maximaal ' . self::MAX_LENGTH_INFO . ' karakters bevatten', E_ERROR);
        }
        $this->publicInfo = $publicInfo;
    }

    public function getPrivateInfo(): ?string
    {
        return $this->privateInfo;
    }

    public function setPrivateInfo(string $privateInfo = null): void
    {
        if ($privateInfo !== null && strlen($privateInfo) === 0) {
            $privateInfo = null;
        }
        if ($privateInfo !== null && strlen($privateInfo) > self::MAX_LENGTH_INFO) {
            throw new InvalidArgumentException('de extra-info mag maximaal ' . self::MAX_LENGTH_INFO . ' karakters bevatten', E_ERROR);
        }
        $this->privateInfo = $privateInfo;
    }
}
