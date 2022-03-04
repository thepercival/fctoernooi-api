<?php

declare(strict_types=1);

namespace FCToernooi;

use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use FCToernooi\Tournament\CustomPlaceRanges;
use League\Period\Period;
use Sports\Competition;
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
     * @var Collection<int|string, TournamentUser>
     */
    private Collection $users;
    /**
     * @var Collection<int|string, Sponsor>
     */
    private Collection $sponsors;
    /**
     * @var Collection<int|string, Competitor>
     */
    private Collection $competitors;
    /**
     * @var Collection<int|string, LockerRoom>
     */
    private Collection $lockerRooms;
    protected int $exported = 0;

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
        $start = $this->getBreakStartDateTime();
        $end = $this->getBreakEndDateTime();
        return ($start !== null && $end !== null) ? new Period($start, $end) : null;
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
     * @return Collection<int|string, TournamentUser>
     */
    public function getUsers(): Collection
    {
        return $this->users;
    }

    public function getUser(User $user): TournamentUser|null
    {
        $filteredUsers = $this->getUsers()->filter(
            function (TournamentUser $tournamentUser) use ($user): bool {
                return $user === $tournamentUser->getUser();
            }
        );
        $user = $filteredUsers->first();
        return $user !== false ? $user : null;
    }

    /**
     * @return Collection<int|string, Sponsor>
     */
    public function getSponsors(): Collection
    {
        return $this->sponsors;
    }

    /**
     * @return Collection<int|string, Competitor>
     */
    public function getCompetitors(): Collection
    {
        return $this->competitors;
    }

    /**
     * @return Collection<int|string, LockerRoom>
     */
    public function getLockerRooms(): Collection
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
