<?php

declare(strict_types=1);

namespace FCToernooi\PlanningTotals;

use SportsHelpers\SportRange;

class CompetitorAmount
{
    private SportRange $nrOfGames;
    private SportRange $nrOfMinutes;

    public function __construct(SportRange|null $nrOfGames = null, SportRange|null $nrOfMinutes = null)
    {
        $this->nrOfGames = $nrOfGames !== null ? $nrOfGames : new SportRange(0, 0);
        $this->nrOfMinutes = $nrOfMinutes !== null ? $nrOfMinutes : new SportRange(0, 0);
    }

    public function getOuterValues(self $competitorAmount): self
    {
        $nrOfGames = new SportRange(
            min($competitorAmount->getNrOfGames()->getMin(), $this->getNrOfGames()->getMin()),
            max($competitorAmount->getNrOfGames()->getMax(), $this->getNrOfGames()->getMax())
        );
        $nrOfMinutes = new SportRange(
            min($competitorAmount->getNrOfMinutes()->getMin(), $this->getNrOfMinutes()->getMin()),
            max($competitorAmount->getNrOfMinutes()->getMax(), $this->getNrOfMinutes()->getMax())
        );
        return new self($nrOfGames, $nrOfMinutes);
    }

    public function getNrOfGames(): SportRange
    {
        return $this->nrOfGames;
    }

    public function getNrOfMinutes(): SportRange
    {
        return $this->nrOfMinutes;
    }

    public function add(CompetitorAmount $competitorAmount): self
    {
        $nrOfGames = new SportRange(
            $competitorAmount->getNrOfGames()->getMin() + $this->getNrOfGames()->getMin(),
            $competitorAmount->getNrOfGames()->getMax() + $this->getNrOfGames()->getMax()
        );
        $nrOfMinutes = new SportRange(
            $competitorAmount->getNrOfMinutes()->getMin() + $this->getNrOfMinutes()->getMin(),
            $competitorAmount->getNrOfMinutes()->getMax() + $this->getNrOfMinutes()->getMax()
        );
        return new self($nrOfGames, $nrOfMinutes);
    }
}
