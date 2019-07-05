<?php

namespace App\Pdf;

class TournamentConfig
{
    CONST GAMENOTES = 1;
    CONST STRUCTURE = 2;
    CONST RULES = 4;
    CONST GAMESPERFIELD = 8;
    CONST PLANNING = 16;
    CONST PIVOTTABLES = 32;
    CONST QRCODE = 64;

    /**
     * @var int
     */
    private $value;

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
        $this->value = $gamenotes ? static::GAMENOTES : 0;
        $this->value += $structure ? static::STRUCTURE : 0;
        $this->value += $rules ? static::RULES : 0;
        $this->value += $gamesperfield ? static::GAMESPERFIELD : 0;
        $this->value += $planning ? static::PLANNING : 0;
        $this->value += $poulePivotTables ? static::PIVOTTABLES : 0;
        $this->value += $qrcode ? static::QRCODE : 0;
    }

    /**
     * @return bool
     */
    public function getGamenotes()
    {
        return ( $this->value & static::GAMENOTES ) === static::GAMENOTES;
    }

    /**
     * @return bool
     */
    public function getStructure()
    {
        return ( $this->value & static::STRUCTURE ) === static::STRUCTURE;
    }

    /**
     * @return bool
     */
    public function getRules()
    {
        return ( $this->value & static::RULES ) === static::RULES;
    }

    /**
     * @return bool
     */
    public function getGamesperfield()
    {
        return ( $this->value & static::GAMESPERFIELD ) === static::GAMESPERFIELD;
    }

    /**
     * @return bool
     */
    public function getPlanning()
    {
        return ( $this->value & static::PLANNING ) === static::PLANNING;
    }

    /**
     * @return bool
     */
    public function getPoulePivotTables()
    {
        return ( $this->value & static::PIVOTTABLES ) === static::PIVOTTABLES;
    }

    /**
     * @return bool
     */
    public function getQRCode()
    {
        return ( $this->value & static::QRCODE ) === static::QRCODE;
    }

    /**
     * @return bool
     */
    public function allOptionsOff()
    {
        return $this->value === 0;
    }

    /**
     * @return bool
     */
    public function hasOnly( int $option )
    {
        return $this->value === $option;
    }
}
