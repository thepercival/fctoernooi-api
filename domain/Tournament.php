<?php
/**
 * Created by PhpStorm.
 * User: coen
 * Date: 6-10-17
 * Time: 22:50
 */

namespace FCToernooi;

use \Doctrine\Common\Collections\ArrayCollection;
use Voetbal\Competition;

class Tournament
{
    /**
     * @var int
     */
    private $id;

    /**
     * @var Competition
     */
    private $competition;

    /**
     * @var ArrayCollection
     */
    private $roles;

    /**
     * @var ArrayCollection
     */
    private $sponsors;

    const MINNROFCOMPETITORS = 2;
    const MAXNROFCOMPETITORS = 32;

    public function __construct( Competition $competition )
    {
        $this->competition = $competition;
        $this->roles = new ArrayCollection();
        $this->sponsors = new ArrayCollection();
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
     * @return Competition
     */
    public function getCompetition()
    {
        return $this->competition;
    }

    /**
     * @param Competition $competition
     */
    public function setCompetition( Competition $competition )
    {
        $this->competition = $competition;
    }

    /**
     * @return Role[] | ArrayCollection
     */
    public function getRoles()
    {
        return $this->roles;
    }

    /**
     * @param ArrayCollection $roles
     */
    public function setRoles( ArrayCollection $roles)
    {
        $this->roles = $roles;
    }

    public function hasRole( User $user, $roleValue ) {
        return ( count(array_filter( $this->getRoles()->toArray(), function ( $roleIt, $roleId ) use ( $user, $roleValue ) {
            return ( $roleIt->getUser() === $user && (( $roleIt->getValue() & $roleValue ) === $roleIt->getValue() ) );
        }, ARRAY_FILTER_USE_BOTH)) > 0);
    }

    /**
     * @return Sponsor[] | ArrayCollection
     */
    public function getSponsors()
    {
        return $this->sponsors;
    }

    /**
     * @param ArrayCollection $sponsors
     */
    public function setSponsors( ArrayCollection $sponsors)
    {
        $this->sponsors = $sponsors;
    }
}