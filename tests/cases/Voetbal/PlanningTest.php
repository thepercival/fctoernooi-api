<?php
/**
 * Created by PhpStorm.
 * User: coen
 * Date: 8-6-19
 * Time: 21:27
 */

namespace Voetbal\Tests;

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Voetbal\NameService;
use Psr\Container\ContainerInterface;
use Voetbal\Planning\Input;
use Voetbal\Planning\Input\Iterator as PlanningInputIterator;
use Voetbal\Planning\Input\Repository as PlanningInputRepository;
use Voetbal\Planning\Output;
use Voetbal\Range as VoetbalRange;
use Voetbal\Referee;
use Voetbal\Structure\Options as StructureOptions;
use Voetbal\Planning\Validator as PlanningValidator;
use Voetbal\Planning\Service as PlanningService;
use Voetbal\Qualify\Group as QualifyGroup;


class PlanningTest extends \PHPUnit\Framework\TestCase
{
    public function testAll()
    {
        /** @var ContainerInterface $container */
        $container = (require __DIR__ . '/../../../config/bootstrap.php')->getContainer();

        $planningInputRepos = $container->get(PlanningInputRepository::class);

        $planningService = new PlanningService();

        $structureOptions = new StructureOptions(
            new VoetbalRange(1, 10), // poules
            new VoetbalRange(9/*2*/, 9/*20*/), // places
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

            //            if(  $planningInput->getNrOfPlaces() === 20 && $planningInput->getNrOfPoules() === 2
            //                && $planningInput->getNrOfFields() === 2
            //                && $planningInput->getNrOfReferees() === 0
            //                && $planningInput->getTeamup() === false  && $planningInput->getSelfReferee() === true
            //                && $planningInput->getNrOfHeadtohead() === 2 ) {
            //                $x = 2;

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
            $this->assertTrue($validator->placeOneTimePerGame());

            $this->assertTrue($validator->allPlacesSameNrOfGames());

            $gamesInARow = $validator->gamesInARow();
            if ($gamesInARow === false) {
                $logger = new Logger('planning-create');
                $handler = new StreamHandler('php://stdout', Logger::INFO);
                $logger->pushHandler($handler);
                $logger->info($this->inputToString($planningInput));
                $output = new Output($logger);
                $output->consoleBatch($bestPlanning->getFirstBatch(), "allPlacesSameNrOfGames");
            }
            $this->assertTrue($gamesInARow, "more than allowed nrofmaxgamesinarow");

            deze controle moet nog naar de validator worden verplaatst => assertValidResourcesPerBatch
        }
    }

    protected function inputToString(Input $planningInput): string
    {
        $sports = array_map(
            function (array $sportConfig) {
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