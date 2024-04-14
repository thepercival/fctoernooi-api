<?php

declare(strict_types=1);

namespace FCToernooi\Tournament;

use FCToernooi\Competitor;
use FCToernooi\Tournament;
use FCToernooi\User;
use Sports\Place\Location as PlaceLocation;
use Sports\Priority\Prioritizable;
use SportsHelpers\Identifiable;
use FCToernooi\Tournament\Registration\State;

class Rule extends Identifiable implements Prioritizable
{
    private string $text;
    private int $priority;

    public const MIN_LENGTH_TEXT = 5;
    public const MAX_LENGTH_TEXT = 80;

    public function __construct(
        private Tournament $tournament,
        string             $text,
    )
    {
        $this->tournament->getRules()->add($this);
        $this->priority = count($this->tournament->getRules());
        $this->setText($text);
    }

    public function getTournament(): Tournament
    {
        return $this->tournament;
    }



    public function getText(): string
    {
        return $this->text;
    }

    final public function setText(string $text): void
    {
        if (strlen($text) < self::MIN_LENGTH_TEXT or strlen($text) > self::MAX_LENGTH_TEXT) {
            throw new \InvalidArgumentException(
                "de tekst moet minimaal " . self::MIN_LENGTH_TEXT . ' karakters bevatten en mag maximaal ' . self::MAX_LENGTH_TEXT . " karakters bevatten",
                E_ERROR
            );
        }
        $this->text = $text;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    final public function setPriority(int $priority): void
    {
        $this->priority = $priority;
    }
}
