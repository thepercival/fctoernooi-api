<?php

declare(strict_types=1);

namespace FCToernooi\Tournament;

use FCToernooi\Tournament;
use Sports\Priority\Prioritizable;
use SportsHelpers\Identifiable;

/**
 * @api
 */
final class Rule extends Identifiable implements Prioritizable
{
    private string $text;
    private int $priority;

    public const int MIN_LENGTH_TEXT = 5;
    public const int MAX_LENGTH_TEXT = 80;
    public const int MAX_PER_TOURNAMENT = 25;

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

    #[\Override]
    public function getPriority(): int
    {
        return $this->priority;
    }

    #[\Override]
    final public function setPriority(int $priority): void
    {
        $this->priority = $priority;
    }
}
