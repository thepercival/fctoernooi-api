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

    protected int|string|null $id = null;
    protected bool $registered = false;
    private string|null $emailaddress = null;
    private string|null $telephone = null;

    protected bool $hasLogo = false;
    protected string|null $info = null;

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

    public function getRegistered(): bool
    {
        return $this->registered;
    }

    public function setRegistered(bool $registered): void
    {
        $this->registered = $registered;
    }

    public function getHasLogo(): bool
    {
        return $this->hasLogo;
    }

    public function setHasLogo(bool $hasLogo): void
    {
        $this->hasLogo = $hasLogo;
    }


    public function getInfo(): ?string
    {
        return $this->info;
    }

    public function setInfo(string $info = null): void
    {
        if ($info !== null && strlen($info) === 0) {
            $info = null;
        }
        if ($info !== null && strlen($info) > self::MAX_LENGTH_INFO) {
            throw new InvalidArgumentException('de extra-info mag maximaal ' . self::MAX_LENGTH_INFO . ' karakters bevatten', E_ERROR);
        }
        $this->info = $info;
    }
}
