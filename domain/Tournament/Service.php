<?php
/**
 * Created by PhpStorm.
 * User: coen
 * Date: 1-10-17
 * Time: 21:41
 */

namespace FCToernooi\Tournament;

use Voetbal\Association;
use FCToernooi\Tournament;
use Voetbal\Competition\Service as CompetitionService;
use League\Period\Period;

class Service
{
    public function __construct()
    {
    }

    /**
     * @param Tournament $tournament
     * @param \DateTimeImmutable $dateTime
     * @param int $ruleSet
     * @param Period|null $period
     * @return Tournament
     * @throws \Exception
     */
    public function changeBasics(
        Tournament $tournament,
        \DateTimeImmutable $dateTime,
        int $ruleSet,
        Period $period = null
    ) {
        $competitionService = new CompetitionService();
        $competition = $tournament->getCompetition();
        $competitionService->changeStartDateTime($competition, $dateTime);
        $competition->setRuleSet($ruleSet);
        $tournament->setBreak($period);

        return $tournament;
    }
}
