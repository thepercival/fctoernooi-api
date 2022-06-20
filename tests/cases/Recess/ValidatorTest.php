<?php

declare(strict_types=1);

namespace FCToernooiTest\Recess;

use DateTimeImmutable;
use FCToernooi\Recess;
use FCToernooi\Tournament;
use FCToernooiTest\TestHelper\CompetitionCreator;
use FCToernooiTest\TestHelper\StructureEditorCreator;
use League\Period\Period;
use PHPUnit\Framework\TestCase;
use Sports\Season;

final class ValidatorTest extends TestCase
{
    use CompetitionCreator;
    use StructureEditorCreator;

    public function testRecessStartBeforeCompetitionStart(): void
    {
        $season = new Season("2080/2081", new Period(
            new DateTimeImmutable("2080-08-01"),
            new DateTimeImmutable("2081-07-01"),
        ));
        $startDateTime = new DateTimeImmutable("2080-01-01T12:00:00.000Z");
        $competition = $this->createCompetition(null, $season, $startDateTime);
        $tournament = new Tournament($competition);
        $recessPeriod = new Period($startDateTime->modify('-10 minutes'), $startDateTime->modify('+10 minutes'));

        $validator = new Recess\Validator();

        self::expectNotToPerformAssertions();
        $validator->validateNewPeriod($recessPeriod, $tournament);
    }

    public function testRecessEndBeforeCompetitionStart(): void
    {
        $season = new Season("2080/2081", new Period(
            new DateTimeImmutable("2080-08-01"),
            new DateTimeImmutable("2081-07-01"),
        ));
        $startDateTime = new DateTimeImmutable("2080-01-01T12:00:00.000Z");
        $competition = $this->createCompetition(null, $season, $startDateTime);
        $tournament = new Tournament($competition);
        $recessPeriod = new Period($startDateTime->modify('-10 minutes'), $startDateTime->modify('-5 minutes'));

        $validator = new Recess\Validator();

        self::expectException(\Exception::class);
        $validator->validateNewPeriod($recessPeriod, $tournament);
    }

    public function testOverlapping(): void
    {
        $season = new Season("2080/2081", new Period(
            new DateTimeImmutable("2080-08-01"),
            new DateTimeImmutable("2081-07-01"),
        ));
        $startDateTime = new DateTimeImmutable("2081-01-01T12:00:00.000Z");
        $competition = $this->createCompetition(null, $season, $startDateTime);
        $tournament = new Tournament($competition);
        $recessPeriod1 = new Period($startDateTime->modify('+10 minutes'), $startDateTime->modify('+20 minutes'));
        new Recess($tournament, 'pauze', $recessPeriod1);

        $recessPeriod2 = new Period($startDateTime->modify('+15 minutes'), $startDateTime->modify('+25 minutes'));

        $validator = new Recess\Validator();

        self::expectException(\Exception::class);
        $validator->validateNewPeriod($recessPeriod2, $tournament);
    }
}
