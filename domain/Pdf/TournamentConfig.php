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
    private $poulePivotTables;

    /**
     * @var bool
     */
    private $qrcode;

    public function __construct(
        bool $gamenotes = true,
        bool $structure = false,
        bool $rules = false,
        bool $gamesperfield = false,
        bool $planning = false,
        bool $poulePivotTables = false,
        bool $qrcode = false
    )
    {
        $this->gamenotes = $gamenotes;
        $this->structure = $structure;
        $this->rules = $rules;
        $this->gamesperfield = $gamesperfield;
        $this->planning = $planning;
        $this->poulePivotTables = $poulePivotTables;
        $this->qrcode = $qrcode;
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
    public function getPoulePivotTables()
    {
        return $this->poulePivotTables;
    }

    /**
     * @return bool
     */
    public function getQRCode()
    {
        return $this->qrcode;
    }

    /**
     * @return bool
     */
    public function allOptionsOff()
    {
        return (!$this->getStructure() && !$this->getPlanning() && !$this->getGamenotes() &&
         !$this->getGamesperfield() && !$this->getRules() && !$this->getPoulePivotTables()
            && !$this->getQRCode() );
    }
}
