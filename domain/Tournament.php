<?php
declare(strict_types=1);

namespace FCToernooi;

use DateTimeImmutable;
use \Doctrine\Common\Collections\ArrayCollection;
use Sports\Competition;
use League\Period\Period;
use Sports\Competition\Referee;
use SportsHelpers\Identifiable;

class Tournament extends Identifiable
{
    private DateTimeImmutable|null $breakStartDateTime = null;
    private DateTimeImmutable|null $breakEndDateTime = null;
    private bool $public = false;
    /**
     * @var ArrayCollection<int|string, TournamentUser>
     */
    private ArrayCollection $users;
    /**
     * @var ArrayCollection<int|string, Sponsor>
     */
    private ArrayCollection $sponsors;
    /**
     * @var ArrayCollection<int|string, Competitor>
     */
    private ArrayCollection $competitors;
    /**
     * @var ArrayCollection<int|string, LockerRoom>
     */
    private ArrayCollection $lockerRooms;
    protected int $exported = 0;
    private DateTimeImmutable|null $createdDateTime = null;

    const EXPORTED_PDF = 1;
    const EXPORTED_EXCEL = 2;

    public function __construct(private Competition $competition)
    {
        $this->users = new ArrayCollection();
        $this->sponsors = new ArrayCollection();
        $this->competitors = new ArrayCollection();
        $this->lockerRooms = new ArrayCollection();
    }

    public function getCompetition(): Competition
    {
        return $this->competition;
    }

    public function getBreakStartDateTime(): ?DateTimeImmutable
    {
        return $this->breakStartDateTime;
    }

    public function setBreakStartDateTime(DateTimeImmutable $datetime = null): void
    {
        $this->breakStartDateTime = $datetime;
    }

    public function getBreakEndDateTime(): ?DateTimeImmutable
    {
        return $this->breakEndDateTime;
    }

    public function setBreakEndDateTime(DateTimeImmutable $datetime = null): void
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

    public function setBreak(Period $period = null): void
    {
        $this->setBreakStartDateTime($period !== null ? $period->getStartDate() : null);
        $this->setBreakEndDateTime($period !== null ? $period->getEndDate() : null);
    }

    public function getPublic(): bool
    {
        return $this->public;
    }

    public function setPublic(bool $public): void
    {
        $this->public = $public;
    }

    /**
     * @return ArrayCollection<int|string, TournamentUser>
     */
    public function getUsers(): ArrayCollection
    {
        return $this->users;
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
     * @return ArrayCollection<int|string, Sponsor>
     */
    public function getSponsors(): ArrayCollection
    {
        return $this->sponsors;
    }

    /**
     * @return ArrayCollection<int|string, Competitor>
     */
    public function getCompetitors(): ArrayCollection
    {
        return $this->competitors;
    }

    /**
     * @return ArrayCollection<int|string, LockerRoom>
     */
    public function getLockerRooms(): ArrayCollection
    {
        return $this->lockerRooms;
    }

    public function getExported(): int
    {
        return $this->exported;
    }

    public function setExported(int $exported): void
    {
        $this->exported = $exported;
    }

    public function getReferee(string $emailaddress): Referee|null
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

    public function setCreatedDateTime(DateTimeImmutable $createdDateTime): void
    {
        $this->createdDateTime = $createdDateTime;
    }
}
