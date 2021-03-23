<?php

declare(strict_types=1);

namespace App\Commands;

use FCToernooi\Tournament\StructureRanges as TournamentStructureRanges;
use Psr\Container\ContainerInterface;
use App\Command;
use Selective\Config\Configuration;

use SportsHelpers\Place\Range as PlaceRange;
use SportsHelpers\SportRange;
use SportsPlanning\Planning\Repository as PlanningRepository;
use SportsPlanning\Input\Repository as PlanningInputRepository;
use Symfony\Component\Console\Input\InputInterface;

class Planning extends Command
{
    protected PlanningInputRepository $planningInputRepos;
    protected PlanningRepository $planningRepos;

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
    ): ?PlaceRange {
        $placeRange = $tournamentStructureRanges->getFirstPlaceRange();
        /** @var string|null $placeRangeOption */
        $placeRangeOption = $input->getOption("placesRange");
        if ($placeRangeOption === null || strlen($placeRangeOption) === 0) {
            return null;
        }
        if (strpos($placeRangeOption, "-") === false) {
            throw new \Exception('misformat placesRange-option', E_ERROR);
        }
        $minMax = explode('-', $input->getOption('placesRange'));
        return new PlaceRange((int)$minMax[0], (int)$minMax[1], $placeRange->getPlacesPerPouleRange());
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
