<?php

declare(strict_types=1);

namespace FCToernooi;

use DateTimeImmutable;
use \Doctrine\Common\Collections\ArrayCollection;
use Sports\Competition;
use League\Period\Period;
use SportsHelpers\Identifiable;

class Tournament extends Identifiable
{
    private Competition $competition;
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
     * @var ArrayCollection|Competitor[]
     */
    private $competitors;
    /**
     * @var ArrayCollection|LockerRoom[]
     */
    private $lockerRooms;
    /**
     * @var integer
     */
    protected $exported;
    /**
     * @var DateTimeImmutable
     */
    private $createdDateTime;

    const EXPORTED_PDF = 1;
    const EXPORTED_EXCEL = 2;

    public function __construct(Competition $competition)
    {
        $this->competition = $competition;
        $this->users = new ArrayCollection();
        $this->sponsors = new ArrayCollection();
        $this->competitors = new ArrayCollection();
        $this->lockerRooms = new ArrayCollection();
    }

    /**
     * @return Competition
     */
    public function getCompetition()
    {
        return $this->competition;
    }

    public function getBreakStartDateTime(): ?DateTimeImmutable
    {
        return $this->breakStartDateTime;
    }

    public function setBreakStartDateTime(DateTimeImmutable $datetime = null)
    {
        $this->breakStartDateTime = $datetime;
    }

    public function getBreakEndDateTime(): ?DateTimeImmutable
    {
        return $this->breakEndDateTime;
    }

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
     * @return Competitor[] | ArrayCollection
     */
    public function getCompetitors()
    {
        return $this->competitors;
    }

    /**
     * @param ArrayCollection | Competitor[] $competitors
     */
    public function setCompetitors(ArrayCollection $competitors)
    {
        $this->competitors = $competitors;
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
     * @return int
     */
    public function getExported()
    {
        return $this->exported;
    }

    /**
     * @param int $exported
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

    public function getCreatedDateTime(): ?DateTimeImmutable
    {
        return $this->createdDateTime;
    }

    public function setCreatedDateTime(DateTimeImmutable $createdDateTime)
    {
        $this->createdDateTime = $createdDateTime;
    }
}
