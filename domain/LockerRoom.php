<?php
declare(strict_types=1);

namespace FCToernooi;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\PersistentCollection;
use FCToernooi\Competitor;
use SportsHelpers\Identifiable;

class LockerRoom extends Identifiable
{
    protected string $name;
    /**
     * @phpstan-var ArrayCollection<int|string, Competitor>|PersistentCollection<int|string, Competitor>
     * @psalm-var ArrayCollection<int|string, Competitor>
     */
    private ArrayCollection|PersistentCollection $competitors;

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

    final public function setName(string $name): void
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
     * @phpstan-return ArrayCollection<int|string, Competitor>|PersistentCollection<int|string, Competitor>
     * @psalm-return ArrayCollection<int|string, Competitor>
     */
    public function getCompetitors(): ArrayCollection|PersistentCollection
    {
        return $this->competitors;
    }

    /**
     * @return list<int>
     */
    public function getCompetitorIds(): array
    {
        return array_values($this->competitors->map(
            function (Competitor $competitor): int {
                return (int)$competitor->getId();
            }
        )->toArray());
    }
}
