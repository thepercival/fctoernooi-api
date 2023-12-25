<?php

declare(strict_types=1);

namespace FCToernooi\Tournament;

use FCToernooi\Competitor;
use FCToernooi\Tournament;
use FCToernooi\User;
use Sports\Place\Location as PlaceLocation;
use SportsHelpers\Identifiable;
use FCToernooi\Tournament\Registration\State;

class Registration extends Identifiable
{
    private State $state = State::Created;
    private string $name;
    private Competitor|null $competitor = null;
    private string $info;

    public const MIN_LENGTH_NAME = 2;
    public const MAX_LENGTH_NAME = 30;
    public const MAX_LENGTH_INFO = 200;

    public function __construct(
        private Tournament $tournament,
        private int        $categoryNr,
        string             $name,
        private string     $emailaddress,
        private string     $telephone,
        string             $info
    )
    {
        $this->setName($name);
        $this->setInfo($info);
    }

    public function getTournament(): Tournament
    {
        return $this->tournament;
    }

    public function getCategoryNr(): int
    {
        return $this->categoryNr;
    }

    public function setCategoryNr(int $categoryNr): void
    {
        $this->categoryNr = $categoryNr;
    }

    public function getState(): State
    {
        return $this->state;
    }

    public function setState(State $state): void
    {
        $this->state = $state;
    }

    public function getName(): string
    {
        return $this->name;
    }

    final public function setName(string $name): void
    {
        if (strlen($name) < self::MIN_LENGTH_NAME or strlen($name) > self::MAX_LENGTH_NAME) {
            throw new \InvalidArgumentException(
                "de naam moet minimaal " . self::MIN_LENGTH_NAME . ' karakters bevatten en mag maximaal ' . self::MAX_LENGTH_NAME . " karakters bevatten",
                E_ERROR
            );
        }
        $this->name = $name;
    }

    public function getEmailaddress(): string
    {
        return $this->emailaddress;
    }

    final public function setEmailaddress(string $emailaddress): void
    {
        if (strlen($emailaddress) < User::MIN_LENGTH_EMAIL or strlen($emailaddress) > User::MAX_LENGTH_EMAIL) {
            throw new \InvalidArgumentException(
                "het emailadres moet minimaal " . User::MIN_LENGTH_EMAIL . ' karakters bevatten en mag maximaal ' . User::MAX_LENGTH_EMAIL . " karakters bevatten",
                E_ERROR
            );
        }
        $this->emailaddress = $emailaddress;
    }

    public function getTelephone(): string
    {
        return $this->telephone;
    }

    final public function setTelephone(string $telephone): void
    {
        if (strlen($telephone) > Competitor::MAX_LENGTH_TELEPHONE) {
            throw new \InvalidArgumentException(
                'het telefoonnr mag maximaal ' . Competitor::MAX_LENGTH_TELEPHONE . ' karakters bevatten',
                E_ERROR
            );
        }
        $this->telephone = $telephone;
    }

    public function getInfo(): string
    {
        return $this->info;
    }

    final public function setInfo(string $info): void
    {
        if (strlen($info) > self::MAX_LENGTH_INFO) {
            throw new \InvalidArgumentException(
                'de informatie mag maximaal ' . self::MAX_LENGTH_INFO . ' karakters bevatten',
                E_ERROR
            );
        }
        $this->info = $info;
    }

    /*public function getStartLocation(): PlaceLocation|null {
        if( $this->competitor === null ) {
            return null;
        }
        return new PlaceLocation(
            $this->competitor->getPouleNr(),
            $this->competitor->getPlaceNr()
        );
    }*/

    public function setCompetitor(Competitor|null $competitor): void {
        $this->competitor = $competitor;
    }
}
