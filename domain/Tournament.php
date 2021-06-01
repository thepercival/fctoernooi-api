<?php
declare(strict_types=1);

namespace FCToernooi;

use DateTimeImmutable;
use \Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\PersistentCollection;
use FCToernooi\Tournament\CustomPlaceRanges;
use Sports\Competition;
use League\Period\Period;
use Sports\Competition\Referee;
use SportsHelpers\Identifiable;
use SportsHelpers\Sport\Variant\MinNrOfPlacesCalculator;

class Tournament extends Identifiable
{
    private DateTimeImmutable $createdDateTime;
    private DateTimeImmutable|null $breakStartDateTime = null;
    private DateTimeImmutable|null $breakEndDateTime = null;
    private bool $public = false;
    /**
     * @phpstan-var ArrayCollection<int|string, TournamentUser>|PersistentCollection<int|string, TournamentUser>
     * @psalm-var ArrayCollection<int|string, TournamentUser>
     */
    private ArrayCollection|PersistentCollection $users;
    /**
     * @phpstan-var ArrayCollection<int|string, Sponsor>|PersistentCollection<int|string, Sponsor>
     * @psalm-var ArrayCollection<int|string, Sponsor>
     */
    private ArrayCollection|PersistentCollection $sponsors;
    /**
     * @phpstan-var ArrayCollection<int|string, Competitor>|PersistentCollection<int|string, Competitor>
     * @psalm-var ArrayCollection<int|string, Competitor>
     */
    private ArrayCollection|PersistentCollection $competitors;
    /**
     * @phpstan-var ArrayCollection<int|string, LockerRoom>|PersistentCollection<int|string, LockerRoom>
     * @psalm-var ArrayCollection<int|string, LockerRoom>
     */
    private ArrayCollection|PersistentCollection $lockerRooms;
    protected int $exported = 0;

    const EXPORTED_PDF = 1;
    const EXPORTED_EXCEL = 2;

    public function __construct(private Competition $competition)
    {
        $this->createdDateTime = new DateTimeImmutable();
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
     * @phpstan-return ArrayCollection<int|string, TournamentUser>|PersistentCollection<int|string, TournamentUser>
     * @psalm-return ArrayCollection<int|string, TournamentUser>
     */
    public function getUsers(): ArrayCollection|PersistentCollection
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
     * @phpstan-return ArrayCollection<int|string, Sponsor>|PersistentCollection<int|string, Sponsor>
     * @psalm-return ArrayCollection<int|string, Sponsor>
     */
    public function getSponsors(): ArrayCollection|PersistentCollection
    {
        return $this->sponsors;
    }

    /**
     * @phpstan-return ArrayCollection<int|string, Competitor>|PersistentCollection<int|string, Competitor>
     * @psalm-return ArrayCollection<int|string, Competitor>
     */
    public function getCompetitors(): ArrayCollection|PersistentCollection
    {
        return $this->competitors;
    }

    /**
     * @phpstan-return ArrayCollection<int|string, LockerRoom>|PersistentCollection<int|string, LockerRoom>
     * @psalm-return ArrayCollection<int|string, LockerRoom>
     */
    public function getLockerRooms(): ArrayCollection|PersistentCollection
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

    public function getCreatedDateTime(): DateTimeImmutable
    {
        return $this->createdDateTime;
    }

    public function getPlaceRanges(): CustomPlaceRanges
    {
        $sportVariants = $this->getCompetition()->createSportVariants();
        $minNrOfPlacesPerPoule = (new MinNrOfPlacesCalculator())->getMinNrOfPlacesPerPoule($sportVariants);
        return new CustomPlaceRanges($minNrOfPlacesPerPoule);
    }
}
