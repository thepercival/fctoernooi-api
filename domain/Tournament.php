<?php

declare(strict_types=1);

namespace FCToernooi;

use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use FCToernooi\Tournament\CustomPlaceRanges;
use FCToernooi\Tournament\Rule;
use FCToernooi\Tournament\StartEditMode;
use League\Period\Period;
use Sports\Competition;
use Sports\Competition\Referee;
use SportsHelpers\Identifiable;
use SportsHelpers\Sport\Variant\MinNrOfPlacesCalculator;

class Tournament extends Identifiable
{
    private DateTimeImmutable $createdDateTime;
    private bool $public = false;
    private StartEditMode $startEditMode = StartEditMode::EditLongTerm;
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
    /**
     * @var Collection<int|string, Recess>
     */
    private Collection $recesses;
    /**
     * @var Collection<int|string, Rule>
     */
    private Collection $rules;
    protected int $exported = 0;
    protected bool $example = false;
    private string $intro;
    /**
     * @var array<string,string>
     */
    private array|null $theme = null;
    private string|null $logoExtension = null;
    protected string|null $location = null;

    public const MAX_LENGTH_LOCATION = 80;
    public const MAX_LENGTH_INTRO = 200;
    public const IMG_FOLDER = 'tournaments';


    public function __construct(
        string $intro,
        private Competition $competition)
    {
        $this->createdDateTime = new DateTimeImmutable();
        $this->users = new ArrayCollection();
        $this->sponsors = new ArrayCollection();
        $this->competitors = new ArrayCollection();
        $this->lockerRooms = new ArrayCollection();
        $this->recesses = new ArrayCollection();
        $this->rules = new ArrayCollection();
        $this->setIntro($intro);
    }

    public function getName(): string {
        return $this->getCompetition()->getLeague()->getName();
    }

    public function getCompetition(): Competition
    {
        return $this->competition;
    }

    public function getPublic(): bool
    {
        return $this->public;
    }

    public function setPublic(bool $public): void
    {
        $this->public = $public;
    }

    public function getExample(): bool
    {
        return $this->example;
    }

    public function setExample(bool $example): void
    {
        $this->example = $example;
    }

    public function getLocation(): string|null
    {
        return $this->location;
    }

    public function setLocation(string $location = null): void
    {
        if ($location !== null && strlen($location) > 0) {
            if (strlen($location) > self::MAX_LENGTH_LOCATION) {
                throw new \InvalidArgumentException(
                    "de locatie mag maximaal " . self::MAX_LENGTH_LOCATION . " karakters bevatten",
                    E_ERROR
                );
            }
        }
        $this->location = $location;
    }

    public function getLogoExtension(): string|null
    {
        return $this->logoExtension;
    }

    public function setLogoExtension(string $extension = null): void
    {
        $this->logoExtension = $extension;
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

    /**
     * @return Collection<int|string, Recess>
     */
    public function getRecesses(): Collection
    {
        return $this->recesses;
    }

    /**
     * @return list<Period>
     */
    public function createRecessPeriods(): array
    {
        return array_values($this->getRecesses()->map(function (Recess $recess): Period {
            return $recess->getPeriod();
        })->toArray());
    }

    /**
     * @return Collection<int|string, Rule>
     */
    public function getRules(): Collection
    {
        return $this->rules;
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

    public function getStartEditMode(): StartEditMode
    {
        return $this->startEditMode;
    }

    public function setStartEditMode(StartEditMode $startEditMode): void
    {
        $this->startEditMode = $startEditMode;
    }

    public function getIntro(): string
    {
        return $this->intro;
    }

    final public function setIntro(string $intro): void
    {
        if (strlen($intro) > self::MAX_LENGTH_INTRO) {
            throw new \InvalidArgumentException(
                'de tekst mag maximaal ' . self::MAX_LENGTH_INTRO . ' karakters bevatten',
                E_ERROR
            );
        }
        $this->intro = $intro;
    }

    /**
     * @return array<string, string>|null
     */
    public function getTheme(): array|null
    {
        return $this->theme;
    }

    public function setTheme(string $key, string $value): void
    {
        if( $this->theme === null) {
            $this->theme = [];
        }
        $this->theme[$key] = $value;
    }
}
