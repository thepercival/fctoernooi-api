<?php
/**
 * Created by PhpStorm.
 * User: coen
 * Date: 11-6-18
 * Time: 13:41
 */

namespace FCToernooi\Tournament;


class BreakX
{
    /**
     * @var \DateTimeImmutable
     */
    private $startDateTime;

    /**
     * @var int
     */
    private $duration;

    public function __construct( \DateTimeImmutable $startDatetime, int $duration )
    {
        $this->startDateTime = $startDatetime;
        $this->duration = $duration;
    }

    /**
     * @return \DateTimeImmutable
     */
    public function getStartDateTime()
    {
        return $this->startDateTime;
    }

    /**
     * @return int
     */
    public function getDuration()
    {
        return $this->duration;
    }
}