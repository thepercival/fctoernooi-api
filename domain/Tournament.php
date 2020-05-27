<?php
/**
 * Created by PhpStorm.
 * User: coen
 * Date: 6-10-17
 * Time: 22:50
 */

namespace FCToernooi;

use DateTimeImmutable;
use \Doctrine\Common\Collections\ArrayCollection;
use Voetbal\Competition;
use League\Period\Period;

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
     * @var DateTimeImmutable
     */
    private $breakStartDateTime;
    /**
     * @var DateTimeImmutable
     */
    private $breakEndDateTime;
    /**
     * @var bool
     */
    private $public;
    /**
     * @var ArrayCollection
     */
    private $users;
    /**
     * @var ArrayCollection|Sponsor[]
     */
    private $sponsors;
    /**
     * @var ArrayCollection|LockerRoom[]
     */
    private $lockerRooms;
    /**
     * @var integer
     */
    protected $exported;
    /**
     * @var bool
     */
    protected $updated; // DEP, false = old struct

    const EXPORTED_PDF = 1;
    const EXPORTED_EXCEL = 2;

    public function __construct(Competition $competition)
    {
        $this->competition = $competition;
        $this->users = new ArrayCollection();
        $this->sponsors = new ArrayCollection();
        $this->lockerRooms = new ArrayCollection();
        $this->updated = true;
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
    public function setCompetition(Competition $competition)
    {
        $this->competition = $competition;
    }

    /**
     * @return DateTimeImmutable|null
     */
    public function getBreakStartDateTime(): ?DateTimeImmutable
    {
        return $this->breakStartDateTime;
    }

    /**
     * @param DateTimeImmutable $datetime
     */
    public function setBreakStartDateTime(DateTimeImmutable $datetime = null)
    {
        $this->breakStartDateTime = $datetime;
    }

    /**
     * @return DateTimeImmutable|null
     */
    public function getBreakEndDateTime(): ?DateTimeImmutable
    {
        return $this->breakEndDateTime;
    }

    /**
     * @param DateTimeImmutable $datetime
     */
    public function setBreakEndDateTime(DateTimeImmutable $datetime = null)
    {
        $this->breakEndDateTime = $datetime;
    }

    public function getBreak(): ?Period
    {
        if ($this->getBreakStartDateTime() === null || $this->getBreakEndDateTime() === null) {
            return null;
        }
        return new Period($this->getBreakStartDateTime(), $this->getBreakEndDateTime());
    }

    /**
     * @param Period|null $period
     */
    public function setBreak(Period $period = null)
    {
        $this->setBreakStartDateTime($period !== null ? $period->getStartDate() : null);
        $this->setBreakEndDateTime($period !== null ? $period->getEndDate() : null);
    }

    /**
     * @return ?bool
     */
    public function getPublic()
    {
        return $this->public;
    }

    /**
     * @param bool $public
     */
    public function setPublic(bool $public)
    {
        $this->public = $public;
    }

    /**
     * @return TournamentUser[] | ArrayCollection
     */
    public function getUsers()
    {
        return $this->users;
    }

    /**
     * @param ArrayCollection $users
     */
    public function setUsers(ArrayCollection $users)
    {
        $this->users = $users;
    }

    public function getUser(User $user): ?TournamentUser
    {
        $filteredUsers = $this->getUsers()->filter(
            function (TournamentUser $tournamentUser) use ($user) : bool {
                return $user === $tournamentUser->getUser();
            }
        );
        $user = $filteredUsers->first();
        return $user ? $user : null;
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
    public function setSponsors(ArrayCollection $sponsors)
    {
        $this->sponsors = $sponsors;
    }

    /**
     * @return LockerRoom[] | ArrayCollection
     */
    public function getLockerRooms()
    {
        return $this->lockerRooms;
    }

    /**
     * @param ArrayCollection $lockerRooms
     */
    public function setLockerRooms(ArrayCollection $lockerRooms)
    {
        $this->lockerRooms = $lockerRooms;
    }

    /**
     * @return integer
     */
    public function getExported()
    {
        return $this->exported;
    }

    /**
     * @param integer $exported
     */
    public function setExported($exported)
    {
        $this->exported = $exported;
    }

    public function getReferee(string $emailaddress)
    {
        $referees = $this->getCompetition()->getReferees();
        foreach ($referees as $referee) {
            if ($referee->getEmailaddress() === $emailaddress) {
                return $referee;
            }
        }
        return null;
    }
}
