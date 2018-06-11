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
use FCToernooi\Tournament\BreakX;

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
     * @var \DateTimeImmutable
     */
    private $breakStartDateTime;

    /**
     * @var int
     */
    private $breakDuration;

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
        $this->breakDuration = 0;
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
     * @return \DateTimeImmutable
     */
    public function getBreakStartDateTime()
    {
        return $this->breakStartDateTime;
    }

    /**
     * @param \DateTimeImmutable $datetime
     */
    public function setBreakStartDateTime( \DateTimeImmutable $datetime = null )
    {
        $this->breakStartDateTime = $datetime;
    }

    /**
     * @return int
     */
    public function getBreakDuration()
    {
        return $this->breakDuration;
    }

    /**
     * @param int $breakDuration
     */
    public function setBreakDuration( int $breakDuration )
    {
        $this->breakDuration = $breakDuration;
    }

    /**
     * @param BreakX $break
     */
    public function setBreak( BreakX $break = null )
    {
        $breakStartDateTime = $break !== null ? $break->getStartDateTime() : null;
        $breakDuration = $break !== null ? $break->getDuration() : 0;
        $this->setBreakStartDateTime( $breakStartDateTime );
        $this->setBreakDuration( $breakDuration );
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