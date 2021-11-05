<?php
declare(strict_types=1);

namespace App\Commands;

use App\Command;
use FCToernooi\Tournament\CustomPlaceRanges as TournamentStructureRanges;
use Psr\Container\ContainerInterface;
use Selective\Config\Configuration;
use SportsHelpers\PlaceRanges;
use SportsHelpers\SportRange;
use SportsPlanning\Input\Repository as PlanningInputRepository;
use SportsPlanning\Planning\Repository as PlanningRepository;
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
        /** @var Configuration $config */
        $config = $container->get(Configuration::class);
        parent::__construct($config);
    }

    protected function getPlacesRange(InputInterface $input): SportRange
    {
        $placesRange = new SportRange(
            PlaceRanges::MinNrOfPlacesPerPoule,
            TournamentStructureRanges::MaxNrOfPlacesPerRoundSmall
        );
        $placeRangeOption = $input->getOption("placesRange");
        if (!is_string($placeRangeOption) || strlen($placeRangeOption) === 0) {
            return $placesRange;
        }
        if (strpos($placeRangeOption, '-') === false) {
            throw new \Exception('misformat placesRange-option', E_ERROR);
        }
        $minMax = explode('-', $placeRangeOption);
        return new SportRange((int)$minMax[0], (int)$minMax[1]);
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
//            $this->getLogger()->info("refereeplaces could not be equally assigned");
//            $planning->setValidity( PlanningValidator::UNEQUALLY_ASSIGNED_REFEREEPLACES );
//
//            $planningOutput = new PlanningOutput($this->getLogger());
//            $planningOutput->outputWithGames($planning, false);
//            $planningOutput->outputWithTotals($planning, false);
//        }
//
//        $planning->setState(PlanningBase::STATE_SUCCESS);
//        $this->planningRepos->save($planning);
//
//        $planningInput->setState(PlanningInput::STATE_ALL_PLANNINGS_TRIED);
//        $this->planningInputRepos->save($planningInput);
//        $this->getLogger()->info('   update state => STATE_ALL_PLANNINGS_TRIED');
//    }
}
