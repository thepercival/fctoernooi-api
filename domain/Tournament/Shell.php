<?php
/**
 * Created by PhpStorm.
 * User: coen
 * Date: 6-10-17
 * Time: 22:50
 */

namespace FCToernooi\Tournament;

use \Doctrine\Common\Collections\ArrayCollection;
use Voetbal\Competition;
use FCToernooi\Tournament;
use FCToernooi\Role;
use FCToernooi\User;

class Shell
{
    /**
     * @var int
     */
    private $tournamentId;

    /**
     * @var string
     */
    private $sport;

    /**
     * @var string
     */
    private $name;

    /**
     * @var \DateTimeImmutable
     */
    private $startDateTime;

    /**
     * @var bool
     */
    private $hasEditPermissions;

    public function __construct( Tournament $tournament, User $user = null )
    {
        $this->tournamentId = $tournament->getId();
        $competition = $tournament->getCompetition();
        $league = $competition->getLeague();
        $this->sport = $league->getSport();
        $this->name = $league->getName();
        $this->startDateTime = $competition->getStartDateTime();
        $this->hasEditPermissions = ( $user !== null && $tournament->hasRole( $user, Role::ADMIN ) );
    }

    /**
     * Get tournamentId
     *
     * @return int
     */
    public function getTournamentId()
    {
        return $this->tournamentId;
    }

    /**
     * @return string
     */
    public function getSport()
    {
        return $this->sport;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return \DateTimeImmutable
     */
    public function getStartDateTime()
    {
        return $this->startDateTime;
    }

    /**
     * @return boolean
     */
    public function getHasEditPermissions()
    {
        return $this->hasEditPermissions;
    }

}