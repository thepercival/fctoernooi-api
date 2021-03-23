<?php
declare(strict_types=1);

namespace FCToernooi;

use SportsHelpers\Identifiable;

class TournamentUser extends Identifiable
{
    private int $roles;

    public function __construct(private Tournament $tournament, private User $user, int $roles = null)
    {
        $this->roles = $roles === null ? 0 : $roles;
        $this->tournament->getUsers()->add($this);
    }

    public function getTournament(): Tournament
    {
        return $this->tournament;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getRoles(): int
    {
        return $this->roles;
    }

    public function setRoles(int $roles): void
    {
        if (($roles & Role::ALL) !== $roles) {
            throw new \InvalidArgumentException("de rol heeft een onjuiste waarde", E_ERROR);
        }
        $this->roles = $roles;
    }

    public function hasRoles(int $roleValue): bool
    {
        return ($this->roles & $roleValue) === $roleValue;
    }

    public function hasARole(int $roleValue): bool
    {
        return ($this->roles & $roleValue) > 0;
    }
}
