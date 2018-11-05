<?php

namespace FCToernooi\Pdf;

class TournamentConfig
{
    /**
     * @var bool
     */
    private $gamenotes;

    /**
     * @var bool
     */
    private $structure;

    /**
     * @var bool
     */
    private $rules;

    /**
     * @var bool
     */
    private $gamesperfield;

    /**
     * @var bool
     */
    private $planning;

    /**
     * @var bool
     */
    private $poules;

    public function __construct(
        bool $gamenotes = true,
        bool $structure = false,
        bool $rules = false,
        bool $gamesperfield = false,
        bool $planning = false,
        bool $poules = false
    )
    {
        $this->gamenotes = $gamenotes;
        $this->structure = $structure;
        $this->rules = $rules;
        $this->gamesperfield = $gamesperfield;
        $this->planning = $planning;
        $this->poules = $poules;
    }

    /**
     * @return bool
     */
    public function getGamenotes()
    {
        return $this->gamenotes;
    }

    /**
     * @return bool
     */
    public function getStructure()
    {
        return $this->structure;
    }

    /**
     * @return bool
     */
    public function getRules()
    {
        return $this->rules;
    }

    /**
     * @return bool
     */
    public function getGamesperfield()
    {
        return $this->gamesperfield;
    }

    /**
     * @return bool
     */
    public function getPlanning()
    {
        return $this->planning;
    }

    /**
     * @return bool
     */
    public function getPoules()
    {
        return $this->poules;
    }

    /**
     * @return bool
     */
    public function allOptionsOff()
    {
        return (!$this->getPoules() && !$this->getPlanning() && !$this->getGamenotes() &&
         !$this->getGamesperfield() && !$this->getRules() && !$this->getStructure() );
    }
}
