<?php
/**
 * Created by PhpStorm.
 * User: coen
 * Date: 22-5-18
 * Time: 12:14
 */

namespace FCToernooi;

use Doctrine\Common\Collections\ArrayCollection;
use Voetbal\Competitor;

class LockerRoom
{
    /**
     * @var int
     */
    private $id;
    /**
     * @var string
     */
    private $name;
    /**
     * @var Tournament
     */
    private $tournament;
    /**
     * @var ArrayCollection|Competitor[]
     */
    private $competitors;
    /**
     * @var array|int[]
     */
    private $competitorIds;

    const MIN_LENGTH_NAME = 1;
    const MAX_LENGTH_NAME = 6;

    public function __construct(Tournament $tournament, $name)
    {
        $this->tournament = $tournament;
        $this->tournament->getLockerRooms()->add($this);
        $this->competitors = new ArrayCollection();
        $this->setName($name);
    }

    /**
     * @return Tournament
     */
    public function getTournament()
    {
        return $this->tournament;
    }

    /**
     * @param Tournament $tournament
     */
    public function setTournament(Tournament $tournament)
    {
        $this->tournament = $tournament;
    }

    /**
     * Get id
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }


    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName($name)
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
     * @return array|ArrayCollection|int[]
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
     * @param ArrayCollection | int[] $competitorIds
     */
    public function setCompetitorIds(ArrayCollection $competitorIds)
    {
        $this->competitorIds = $competitorIds;
    }
}
