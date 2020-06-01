<?php
/**
 * Created by PhpStorm.
 * User: coen
 * Date: 1-10-17
 * Time: 12:08
 */


namespace FCToernooi\Tournament;

use DateTimeImmutable;
use FCToernooi\Tournament;
use FCToernooi\Role;

// use \Doctrine\Common\Collections\ArrayCollection;

class Invitation
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
     * @var string
     */
    private $emailaddress;
    /**
     * @var int
     */
    private $roles;
    /**
     * @var DateTimeImmutable
     */
    private $createdDateTime;

    public function __construct(Tournament $tournament, string $emailaddress, int $roles)
    {
        $this->tournament = $tournament;
        $this->emailaddress = $emailaddress;
        $this->roles = $roles;
    }

    /**
     * Get id
     *
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

    public function getEmailaddress(): string
    {
        return $this->emailaddress;
    }

    public function setEmailaddress(string $emailaddress)
    {
        $this->emailaddress = $emailaddress;
    }

    public function getCreatedDateTime(): ?DateTimeImmutable
    {
        return $this->createdDateTime;
    }

    public function setCreatedDateTime(DateTimeImmutable $createdDateTime)
    {
        $this->createdDateTime = $createdDateTime;
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
}