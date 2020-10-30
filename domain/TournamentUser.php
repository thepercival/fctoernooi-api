<?php

declare(strict_types=1);

namespace FCToernooi;

class TournamentUser
{
    /**
     * @var int
     */
    private $id;

    /**
     * @var Tournament
     */
    private $tournament;

    /**
     * @var User
     */
    private $user;

    /**
     * @var int
     */
    private $roles;

    public function __construct(Tournament $tournament, User $user, int $roles = null)
    {
        $this->tournament = $tournament;
        if ($roles === null) {
            $roles = 0;
        }
        $this->roles = $roles;
        $this->tournament->getUsers()->add($this);
        $this->user = $user;
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    public function getTournament(): Tournament
    {
        return $this->tournament;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user)
    {
        $this->user = $user;
    }

    public function getRoles(): int
    {
        return $this->roles;
    }

    public function setRoles(int $roles)
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
