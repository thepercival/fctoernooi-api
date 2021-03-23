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
    /**
     * @var ArrayCollection<int|string, Competitor>
     */
    private ArrayCollection $competitors;

    const MIN_LENGTH_NAME = 1;
    const MAX_LENGTH_NAME = 6;

    public function __construct(protected Tournament $tournament, string $name)
    {
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

    public function setName(string $name): void
    {
        if (strlen($name) < self::MIN_LENGTH_NAME or strlen($name) > self::MAX_LENGTH_NAME) {
            throw new \InvalidArgumentException(
                'de naam moet minimaal ' . self::MIN_LENGTH_NAME . ' karakters bevatten en mag maximaal ' . self::MAX_LENGTH_NAME . " karakters bevatten",
                E_ERROR
            );
        }
        $this->name = $name;
    }

    /**
     * @return ArrayCollection<int|string, Competitor>
     */
    public function getCompetitors(): ArrayCollection
    {
        return $this->competitors;
    }

    /**
     * @return list<int|string>
     */
    public function getCompetitorIds(): array
    {
        return array_values($this->competitors->map(
            function (Competitor $competitor): string|int {
                return $competitor->getId();
            }
        )->toArray());
    }
}
