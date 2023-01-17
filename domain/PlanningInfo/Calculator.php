<?php

declare(strict_types=1);

namespace FCToernooi\PlanningInfo;

use FCToernooi\PlanningInfo;
use FCToernooi\Recess;
use League\Period\Period;
use Sports\Category;
use Sports\Round;
use Sports\Round\Number as RoundNumber;
use Sports\Round\Number\PlanningAssigner;
use Sports\Round\Number\PlanningInputCreator;
use Sports\Round\Number\PlanningScheduler;
use Sports\Structure;
use SportsHelpers\Sport\GamePlaceCalculator;
use SportsHelpers\Sport\Variant\Against\GamesPerPlace as AgainstGpp;
use SportsHelpers\Sport\Variant\Against\H2h as AgainstH2h;
use SportsHelpers\Sport\Variant\AllInOneGame;
use SportsHelpers\Sport\Variant\Creator as VariantCreator;
use SportsHelpers\Sport\Variant\Single;
use SportsHelpers\SportRange;
use SportsPlanning\Input\Repository as InputRepository;

class Calculator
{
    private GamePlaceCalculator $sportGamePlaceCalculator;

    public function __construct(private InputRepository $inputRepository)
    {
        $this->sportGamePlaceCalculator = new GamePlaceCalculator();
    }

    public function calculate(Structure $structure, array $recesses, int $nrOfReferees): PlanningInfo|null
    {
        $firstRoundNumber = $structure->getFirstRoundNumber();
        if (!$this->assignGames($firstRoundNumber, $recesses, $nrOfReferees)) {
            return null;
        }

        $period = $this->getPeriod($firstRoundNumber);
        $competitorAmount = $this->getCompetitorAmount($structure);
        return new PlanningInfo($period, $competitorAmount);
    }

    /**
     * @param RoundNumber $roundNumber
     * @param list<Recess> $recesses
     * @param int $nrOfReferees
     * @return bool
     * @throws \SportsPlanning\Exception\NoBestPlanning
     */
    public function assignGames(RoundNumber $roundNumber, array $recesses, int $nrOfReferees): bool
    {
        $recessPeriods = array_map(fn(Recess $recess) => $recess->getPeriod(), $recesses);

        while ($roundNumber) {
            $planningInput = (new PlanningInputCreator())->create($roundNumber, $nrOfReferees);

            $input = $this->inputRepository->getFromInput($planningInput);
            if ($input === null) {
                return false;
            }
            $bestPlanning = $input->getBestPlanning(null);

            $planningAssigner = new PlanningAssigner(new PlanningScheduler($recessPeriods));
            $planningAssigner->assignPlanningToRoundNumber($roundNumber, $bestPlanning);

            $roundNumber = $roundNumber->getNext();
        }
        return true;
    }

    // van 1e ronde de startdatum ophalen en van de laatste ronde de einddatum ophalen
    private function getPeriod(RoundNumber $firstRoundNumber): Period
    {
        return new Period(
            $firstRoundNumber->getFirstGameStartDateTime(),
            $firstRoundNumber->getLast()->getLastGameEndDateTime()
        );
    }

    private function getCompetitorAmount(Structure $structure): CompetitorAmount
    {
        $sportVariants = $structure->getFirstRoundNumber()->getCompetition()->createSportVariants();

        $catCompetitorAmounts = array_map(
            fn($category) => $this->getCategoryAmount($category, $sportVariants),
            $structure->getCategories()
        );

        return $this->getOuterValues($catCompetitorAmounts);
    }

    /**
     * @param Category $category
     * @param list<AllInOneGame|Single|AgainstH2h|AgainstGpp> $sportVariants ,
     * @return CompetitorAmount
     */
    private function getCategoryAmount(Category $category, array $sportVariants): CompetitorAmount
    {
        return $this->getRoundAmount($category->getRootRound(), $sportVariants);
    }

    /**
     * @param Round $round
     * @param list<AllInOneGame|Single|AgainstH2h|AgainstGpp> $sportVariants
     * @return CompetitorAmount
     */
    private function getRoundAmount(
        Round $round,
        array $sportVariants
    ): CompetitorAmount {
        $competitorAmount = $this->getRoundAmountSelf($round, $sportVariants);
        if (count($round->getChildren()) === 0) {
            return $competitorAmount;
        }

        // map children to
        $childRoundAmounts = array_map(
            fn(Round $childRound) => $this->getRoundAmount($childRound, $sportVariants),
            $round->getChildren()
        );

        if ($round->getNrOfDropoutPlaces() > 0) {
            $childRoundAmounts[] = new CompetitorAmount();
        }
        return $competitorAmount->add($this->getOuterValues($childRoundAmounts));
    }

    /**
     * @param Round $round
     * @param list<AllInOneGame|Single|AgainstH2h|AgainstGpp> $sportVariants ,
     * @return CompetitorAmount
     */
    private function getRoundAmountSelf(Round $round, array $sportVariants): CompetitorAmount
    {
        $pouleStructure = $round->createPouleStructure();

        $roundNrOfGames = new SportRange(
            $this->sportGamePlaceCalculator->getMaxNrOfGamesPerPlace(
                (new VariantCreator())->createWithPoules($pouleStructure->getSmallestPoule(), $sportVariants)
            ),
            $this->sportGamePlaceCalculator->getMaxNrOfGamesPerPlace(
                (new VariantCreator())->createWithPoules($pouleStructure->getBiggestPoule(), $sportVariants)
            )
        );
        $maxNrOfMinutesPerGame = $round->getNumber()->getValidPlanningConfig()->getMaxNrOfMinutesPerGame();
        $roundNrOfMinutes = new SportRange(
            $roundNrOfGames->getMin() * $maxNrOfMinutesPerGame,
            $roundNrOfGames->getMax() * $maxNrOfMinutesPerGame,
        );
        return new CompetitorAmount($roundNrOfGames, $roundNrOfMinutes);
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
