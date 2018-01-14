<?php
/**
 * Created by PhpStorm.
 * User: coen
 * Date: 1-10-17
 * Time: 12:08
 */


namespace FCToernooi\Tournament;

use FCToernooi\Tournament;
use FCToernooi\User;

// use \Doctrine\Common\Collections\ArrayCollection;

class Role
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
    private $role;

    const ADMIN = 1;
    const STRUCTUREADMIN = 2;
    const PLANNER = 4;
    const GAMERESULTADMIN = 8;
    const ALL = 15;

    public function __construct( Tournament $tournament, User $user )
    {
        $this->tournament = $tournament;
        $this->tournament->getRoles()->add( $this );
        $this->user = $user;
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
     * @param $id
     */
    public function setId( $id )
    {
        $this->id = $id;
    }

    /**
     * @return Tournament
     */
    public function getTournament()
    {
        return $this->tournament;
    }

    /**
     * @param User $user
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * @param User $user
     */
    public function setUser( User $user )
    {
        $this->user = $user;
    }

    /**
     * @return int
     */
    public function getRole()
    {
        return $this->role;
    }

    /**
     * @param int $role
     */
    public function setRole( $role )
    {
        if ( !is_int( $role ) or ( ( $role & static::ALL ) !== $role ) ){
            throw new \InvalidArgumentException( "de rol heeft een onjuiste waarde", E_ERROR );
        }
        $this->role = $role;
    }
}