<?php

namespace App\Commands;

use FCToernooi\Tournament\StructureRanges as TournamentStructureRanges;
use Psr\Container\ContainerInterface;
use App\Command;
use Selective\Config\Configuration;

use SportsHelpers\Place\Range as PlaceRange;
use SportsPlanning\Planning as PlanningBase;
use SportsPlanning\Planning\Validator as PlanningValidator;
use SportsPlanning\Planning\Repository as PlanningRepository;
use SportsPlanning\Input\Repository as PlanningInputRepository;
use SportsPlanning\Resource\RefereePlace\Service as RefereePlaceService;
use SportsPlanning\Planning\Output as PlanningOutput;
use SportsPlanning\Input as PlanningInput;
use SportsPlanning\Batch\SelfReferee as SelfRefereeBatch;
use Symfony\Component\Console\Input\InputInterface;

class Planning extends Command
{
    /**
     * @var PlanningInputRepository
     */
    protected $planningInputRepos;
    /**
     * @var PlanningRepository
     */
    protected $planningRepos;


    public function __construct(ContainerInterface $container)
    {
        // $settings = $container->get('settings');
        $this->planningInputRepos = $container->get(PlanningInputRepository::class);
        $this->planningRepos = $container->get(PlanningRepository::class);
        parent::__construct($container->get(Configuration::class));
    }

    protected function getPlaceRange(
        InputInterface $input,
        TournamentStructureRanges $tournamentStructureRanges
    ): PlaceRange {
        $placeRange = $tournamentStructureRanges->getFirstPlaceRange();
        if (strlen($input->getOption("placesRange")) > 0) {
            if (strpos($input->getOption("placesRange"), "-") === false ) {
                throw new \Exception("misformat placesRange-option");
            }
            $minMax = explode('-', $input->getOption('placesRange'));
            $placeRange->min = (int)$minMax[0];
            $placeRange->max = (int)$minMax[1];
        }
        return $placeRange;
    }

//    protected function updateSelfReferee(PlanningInput $planningInput)
//    {
//        $planning = $planningInput->getBestPlanning();
//        if ($planning === null) {
//            throw new \Exception("there should be a best planning", E_ERROR);
//        }
//
//        $firstBatch = new SelfRefereeBatch( $planning->createFirstBatch() );
//        $refereePlaceService = new RefereePlaceService($planning);
//        if (!$refereePlaceService->assign($firstBatch)) {
//            $this->logger->info("refereeplaces could not be equally assigned");
//            $planning->setValidity( PlanningValidator::UNEQUALLY_ASSIGNED_REFEREEPLACES );
//
//            $planningOutput = new PlanningOutput($this->logger);
//            $planningOutput->outputWithGames($planning, false);
//            $planningOutput->outputWithTotals($planning, false);
//        }
//
//        $planning->setState(PlanningBase::STATE_SUCCESS);
//        $this->planningRepos->save($planning);
//
//        $planningInput->setState(PlanningInput::STATE_ALL_PLANNINGS_TRIED);
//        $this->planningInputRepos->save($planningInput);
//        $this->logger->info('   update state => STATE_ALL_PLANNINGS_TRIED');
//    }
}
