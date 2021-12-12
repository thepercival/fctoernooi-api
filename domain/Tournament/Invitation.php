<?php

declare(strict_types=1);

namespace FCToernooi\Tournament;

use DateTimeImmutable;
use FCToernooi\Role;
use FCToernooi\Tournament;
use FCToernooi\User;
use SportsHelpers\Identifiable;

class Invitation extends Identifiable
{
    private string $emailaddress;
    private int $roles;
    private DateTimeImmutable|null $createdDateTime = null;

    public function __construct(private Tournament $tournament, string $emailaddress, int $roles)
    {
        $this->setEmailaddress($emailaddress);
        $this->setRoles($roles);
    }

    public function getTournament(): Tournament
    {
        return $this->tournament;
    }

    public function getEmailaddress(): string
    {
        return $this->emailaddress;
    }

    final public function setEmailaddress(string $emailaddress): void
    {
        if (strlen($emailaddress) < User::MIN_LENGTH_EMAIL or strlen($emailaddress) > User::MAX_LENGTH_EMAIL) {
            throw new \InvalidArgumentException(
                "het emailadres moet minimaal " . User::MIN_LENGTH_EMAIL . " karakters bevatten en mag maximaal " . User::MAX_LENGTH_EMAIL . " karakters bevatten",
                E_ERROR
            );
        }

        if (filter_var($emailaddress, FILTER_VALIDATE_EMAIL) === false) {
            throw new \InvalidArgumentException("het emailadres " . $emailaddress . " is niet valide", E_ERROR);
        }
        $this->emailaddress = $emailaddress;
    }

    public function getCreatedDateTime(): ?DateTimeImmutable
    {
        return $this->createdDateTime;
    }

    public function setCreatedDateTime(DateTimeImmutable $createdDateTime): void
    {
        $this->createdDateTime = $createdDateTime;
    }

    public function getRoles(): int
    {
        return $this->roles;
    }

    final public function setRoles(int $roles): void
    {
        if (($roles & Role::ALL) !== $roles) {
            throw new \InvalidArgumentException("de rol heeft een onjuiste waarde", E_ERROR);
        }
        $this->roles = $roles;
    }
}
