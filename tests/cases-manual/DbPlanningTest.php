<?php
/**
 * Created by PhpStorm.
 * User: coen
 * Date: 8-6-19
 * Time: 21:27
 */

namespace FCToernooiTestManual;

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Voetbal\NameService;
use Psr\Container\ContainerInterface;
use Voetbal\Planning;
use Voetbal\Planning\Input;
use Voetbal\Planning\Input\Iterator as PlanningInputIterator;
use Voetbal\Planning\Input\Repository as PlanningInputRepository;
use Voetbal\Output\Planning\Batch as BatchOutput;
use Voetbal\Range as VoetbalRange;
use Voetbal\Referee;
use Voetbal\Structure\Options as StructureOptions;
use Voetbal\Planning\Validator as PlanningValidator;
use Voetbal\Planning\Service as PlanningService;
use Voetbal\Qualify\Group as QualifyGroup;

class DbPlanningTest extends \PHPUnit\Framework\TestCase
{
    public function testAll()
    {
        /** @var ContainerInterface $container */
        $container = (require __DIR__ . '/../../config/bootstrap.php')->getContainer();

        $planningInputRepos = $container->get(PlanningInputRepository::class);

        $planningService = new PlanningService();

        $structureOptions = new StructureOptions(
            new VoetbalRange(1, 10), // poules
            new VoetbalRange(2, 20), // places
            new VoetbalRange(2, 10)
        );

        $planningInputIterator = new PlanningInputIterator(
            $structureOptions,
            new VoetbalRange(1, 1),
            new VoetbalRange(1, 10),
            new VoetbalRange(0, 10),
            new VoetbalRange(1, 2),
        );

        // $planningInputIt = $planningInputIterator->increment();
        while ($planningInputIt = $planningInputIterator->increment()) {
            // $this->logger->info( $this->inputToString( $planningInput ) );

//            if( !($planningInputIt->getNrOfPlaces() === 8 && $planningInputIt->getNrOfPoules() === 1
//                && $planningInputIt->getNrOfFields() === 1
//                && $planningInputIt->getNrOfReferees() === 0
//                && $planningInputIt->getTeamup() === true  && $planningInputIt->getSelfReferee() === false
//                && $planningInputIt->getNrOfHeadtohead() === 1 ) ) {
//                continue;
//            }
            $planningInput = $planningInputRepos->getFromInput($planningInputIt);

            // $this->assertNotEquals( $planningInput, null );

            if ($planningInput === null) {
                continue;
            }

            $bestPlanning = $planningService->getBestPlanning($planningInput);
            if ($bestPlanning === null) {
                continue;
            }

            $validator = new PlanningValidator($bestPlanning);
            $hasEnoughTotalNrOfGames = $validator->hasEnoughTotalNrOfGames();
            if ($hasEnoughTotalNrOfGames === false) {
                $this->consolePlanning($bestPlanning);
            }
            self::assertTrue($hasEnoughTotalNrOfGames, "the planning has not enough games");


            $placeOneTimePerGame = $validator->placeOneTimePerGame();
            if ($placeOneTimePerGame === false) {
                $this->consolePlanning($bestPlanning);
            }
            self::assertTrue($placeOneTimePerGame, "a place is assigned more than one in a game");

            $allPlacesSameNrOfGames = $validator->allPlacesSameNrOfGames();
            if ($allPlacesSameNrOfGames === false) {
                $this->consolePlanning($bestPlanning);
            }
            self::assertTrue($allPlacesSameNrOfGames, "not all places within poule have same number of games");

            $gamesInARow = $validator->checkGamesInARow();
            if ($gamesInARow === false) {
                $this->consolePlanning($bestPlanning);
            }
            self::assertTrue($gamesInARow, "more than allowed nrofmaxgamesinarow");

            $validResourcesPerBatch = $validator->validResourcesPerBatch();
            if ($validResourcesPerBatch === false) {
                $this->consolePlanning($bestPlanning);
            }
            self::assertTrue($validResourcesPerBatch, "more resources per batch than allowed");
        }
    }

    protected function consolePlanning(Planning $planning)
    {
        $logger = new Logger('planning-create');
        $handler = new StreamHandler('php://stdout', Logger::INFO);
        $logger->pushHandler($handler);
        $logger->info($this->inputToString($planning->getInput()));
        $output = new BatchOutput($logger);
        $output->output($planning->getFirstBatch(), "allPlacesSameNrOfGames");
    }

    protected function inputToString(Input $planningInput): string
    {
        $sports = array_map(
            function (array $sportConfig): string {
                return '' . $sportConfig["nrOfFields"];
            },
            $planningInput->getSportConfig()
        );
        return 'id(' . ($planningInput->getId()) . ') => structure [' . implode(
                '|',
                $planningInput->getStructureConfig()
            ) . ']'
            . ', sports [' . implode(',', $sports) . ']'
            . ', referees ' . $planningInput->getNrOfReferees()
            . ', teamup ' . ($planningInput->getTeamup() ? '1' : '0')
            . ', selfRef ' . ($planningInput->getSelfReferee() ? '1' : '0')
            . ', nrOfH2h ' . $planningInput->getNrOfHeadtohead();
    }
}
