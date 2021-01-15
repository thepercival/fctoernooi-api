<?php

declare(strict_types=1);

namespace FCToernooi;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use FCToernooi\Competitor;
use SportsHelpers\Identifiable;

class LockerRoom extends Identifiable
{
    protected string $name;
    protected Tournament $tournament;
    /**
     * @var ArrayCollection|Competitor[]
     */
    private $competitors;
    /**
     * @var Collection|int[]
     */
    private $competitorIds;

    const MIN_LENGTH_NAME = 1;
    const MAX_LENGTH_NAME = 6;

    public function __construct(Tournament $tournament, string $name)
    {
        $this->tournament = $tournament;
        $this->tournament->getLockerRooms()->add($this);
        $this->competitors = new ArrayCollection();
        $this->setName($name);
    }

    public function getTournament(): Tournament
    {
        return $this->tournament;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name)
    {
        if (strlen($name) < static::MIN_LENGTH_NAME or strlen($name) > static::MAX_LENGTH_NAME) {
            throw new \InvalidArgumentException(
                "de naam moet minimaal " . static::MIN_LENGTH_NAME . " karakters bevatten en mag maximaal " . static::MAX_LENGTH_NAME . " karakters bevatten",
                E_ERROR
            );
        }
        $this->name = $name;
    }

    /**
     * @return Competitor[] | ArrayCollection
     */
    public function getCompetitors()
    {
        return $this->competitors;
    }

    /**
     * @param ArrayCollection $competitors
     */
    public function setCompetitors(ArrayCollection $competitors)
    {
        $this->competitors = $competitors;
    }

    /**
     * @return Collection|int[]
     */
    public function getCompetitorIds()
    {
        if ($this->competitorIds !== null) {
            return $this->competitorIds;
        }
        return $this->competitors->map(
            function ($competitor) {
                return $competitor->getId();
            }
        );
    }

    /**
     * @param Collection | int[] $competitorIds
     */
    public function setCompetitorIds(Collection $competitorIds)
    {
        $this->competitorIds = $competitorIds;
    }
}
