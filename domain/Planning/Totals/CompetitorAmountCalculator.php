<?php

declare(strict_types=1);

namespace FCToernooi\Planning\Totals;

use Sports\Category;
use Sports\Poule;
use Sports\Round;
use SportsHelpers\PouleStructure\Balanced as BalancedPouleStructure;
use SportsHelpers\Sport\VariantWithFields;
use SportsHelpers\SportRange;
use SportsPlanning\PouleStructure as PlanningPouleStructure;
use SportsPlanning\Referee\Info as RefereeInfo;

class CompetitorAmountCalculator
{
    /**
     * @param list<Category> $categories
     * @param list<VariantWithFields> $sportVariantsWithFields
     * @return CompetitorAmount
     */
    public function calculate(array $categories, array $sportVariantsWithFields): CompetitorAmount
    {
        $catCompetitorAmounts = array_map(
            fn($category) => $this->getCategoryAmount($category, $sportVariantsWithFields),
            $categories
        );

        return $this->getOuterValues($catCompetitorAmounts);
    }

    /**
     * @param Category $category
     * @param list<VariantWithFields> $sportVariantsWithFields
     * @return CompetitorAmount
     */
    private function getCategoryAmount(Category $category, array $sportVariantsWithFields): CompetitorAmount
    {
        return $this->getRoundAmount($category->getRootRound(), $sportVariantsWithFields);
    }

    // je bent hier de hyrarchie nodig en daarnaast moet je weten per ronde
    // hoeveel wat de minimum en maximum te spelen wedstrijden zijn

    /**
     * @param Round $round
     * @param list<VariantWithFields> $sportVariantsWithFields
     * @return CompetitorAmount
     */
    private function getRoundAmount(Round $round, array $sportVariantsWithFields): CompetitorAmount {
        $refereeInfo = $round->getNumber()->getRefereeInfo();
        $maxNrOfMinutesPerGame = $round->getNumber()->getValidPlanningConfig()->getMaxNrOfMinutesPerGame();

        $nrOfPlaces = array_map(function (Poule $poule): int {
            return $poule->getPlaces()->count();
        }, $round->getPoules()->toArray() );
        $pouleStructure = new BalancedPouleStructure(...$nrOfPlaces);

        $planningRefereeInfo = new RefereeInfo($refereeInfo);
        $competitorAmount = $this->getCompetitorAmountSelf(
            $pouleStructure, $maxNrOfMinutesPerGame, $sportVariantsWithFields, $planningRefereeInfo);
        if (count($round->getChildren()) === 0) {
            return $competitorAmount;
        }

        // map children to
        $childRoundAmounts = array_map(
            fn(Round $childRound) => $this->getRoundAmount($childRound, $sportVariantsWithFields),
            $round->getChildren()
        );

        if ($round->getNrOfDropoutPlaces() > 0) {
            $childRoundAmounts[] = new CompetitorAmount();
        }
        return $competitorAmount->add($this->getOuterValues($childRoundAmounts));
    }

    /**
     * @param BalancedPouleStructure $pouleStructure ,
     * @param int $maxNrOfMinutesPerGame,
     * @param list<VariantWithFields> $sportVariantsWithFields
     * @return CompetitorAmount
     */
    private function getCompetitorAmountSelf(
        BalancedPouleStructure $pouleStructure,
        int $maxNrOfMinutesPerGame,
        array $sportVariantsWithFields,
        RefereeInfo $refereeInfo ): CompetitorAmount
    {
        // als poules van twee plaatsen en scheidsrechter is zelf eigen poule dan mag hier op doorgegaan worden
        // er zou iets van een exception moeten komen om te kijken welke optie je wilt afvangen
        // OWN_POULE : TOO_FEW_PLACES_EXCEPTION
        // OTHER_POULE : TOO_FEW_PLACES_EXCEPTION
//        if( $pouleStructure,
//            $sportVariantsWithFields,
//            $refereeInfo)


        $planningPouleStructure = new PlanningPouleStructure(
            $pouleStructure,
            $sportVariantsWithFields,
            $refereeInfo
        );
        $maxNrOfGamesPerPlaceRange = $planningPouleStructure->getMaxNrOfGamesPerPlaceRange();
        $roundNrOfMinutes = new SportRange(
            $maxNrOfGamesPerPlaceRange->getMin() * $maxNrOfMinutesPerGame,
            $maxNrOfGamesPerPlaceRange->getMax() * $maxNrOfMinutesPerGame,
        );
        return new CompetitorAmount($maxNrOfGamesPerPlaceRange, $roundNrOfMinutes);
    }

    private function getOuterValues(array $competitorAmounts): CompetitorAmount
    {
        $outerCompetitorAmount = null;
        foreach ($competitorAmounts as $competitorAmount) {
            if ($outerCompetitorAmount === null) {
                $outerCompetitorAmount = $competitorAmount;
                continue;
            }
            $outerCompetitorAmount = $outerCompetitorAmount->getOuterValues($competitorAmount);
        }
        return $outerCompetitorAmount;
    }
}
