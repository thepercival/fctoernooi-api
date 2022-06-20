<?php

declare(strict_types=1);

namespace App\Export\Pdf\Page;

use App\Export\Pdf\Align;
use App\Export\Pdf\Configs\GameLineConfig;
use App\Export\Pdf\Document\Planning as PlanningDocument;
use App\Export\Pdf\Drawers\GameLine\Against as AgainstGameLine;
use App\Export\Pdf\Drawers\GameLine\Column\DateTime as DateTimeColumn;
use App\Export\Pdf\Drawers\GameLine\Column\Referee as RefereeColumn;
use App\Export\Pdf\Drawers\GameLine\Together as TogetherGameLine;
use App\Export\Pdf\Line\Horizontal as HorizontalLine;
use App\Export\Pdf\Page as ToernooiPdfPage;
use App\Export\Pdf\Point;
use App\Export\Pdf\Rectangle;
use FCToernooi\Recess;
use Sports\Competition;
use Sports\Game\Against as AgainstGame;
use Sports\Game\Together as TogetherGame;
use Sports\Round\Number as RoundNumber;
use SportsHelpers\GameMode;
use SportsHelpers\Sport\Variant\Against as AgainstSportVariant;

class Planning extends ToernooiPdfPage
{
    protected AgainstGameLine|null $againstGameLine = null;
    protected TogetherGameLine|null $togetherGameLine = null;
    /**
     * @var callable|null
     */
    protected mixed $gameFilter = null;
    protected string|null $title = null;

    public function __construct(PlanningDocument $document, mixed $param1)
    {
        parent::__construct($document, $param1);
        $this->setLineWidth(0.5);
    }

    public function getParent(): PlanningDocument
    {
        return $this->parent;
    }

    public function getRowHeight(): int {
        return (new GameLineConfig(DateTimeColumn::None, RefereeColumn::None))->getRowHeight();
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    protected function getGameLine(AgainstGame|TogetherGame $game): AgainstGameLine|TogetherGameLine
    {
        return $this->getGameLineByGameMode($game->getCompetitionSport()->getGameMode());
    }

    protected function getGameLineByGameMode(GameMode $gameMode): AgainstGameLine|TogetherGameLine
    {
        if ($gameMode === GameMode::Against && $this->againstGameLine !== null) {
            return $this->againstGameLine;
        }
        if ($gameMode === GameMode::Single && $this->togetherGameLine !== null) {
            return $this->togetherGameLine;
        }
        if ($gameMode === GameMode::AllInOneGame && $this->togetherGameLine !== null) {
            return $this->togetherGameLine;
        }
        throw new \Exception('gameline should be implemented', E_ERROR);
    }

    public function getGameFilter(): callable|null
    {
        return $this->gameFilter;
    }

    public function setGameFilter(callable|null $gameFilter): void
    {
        $this->gameFilter = $gameFilter;
    }

    public function initGameLines(RoundNumber $roundNumber): void
    {
        $config = new GameLineConfig(
            $this->getShowDateTime($roundNumber),
            $this->getShowReferee($roundNumber)
        );
        $competitionSports = $roundNumber->getCompetitionSports();
        foreach ($competitionSports as $competitionSport) {
            $sportVariant = $competitionSport->createVariant();
            if ($sportVariant instanceof AgainstSportVariant) {
                if ($this->againstGameLine === null) {
                    $this->againstGameLine = new AgainstGameLine($this, $config);
                }
            } else {
                if ($this->togetherGameLine === null) {
                    $this->togetherGameLine = new TogetherGameLine($this, $config);
                }
            }
        }
    }

    protected function hasOnlyAgainstGameMode(Competition $competition): bool
    {
        foreach ($competition->createSportVariants() as $sportVariant) {
            if (!($sportVariant instanceof AgainstSportVariant)) {
                return false;
            }
        }
        return true;
    }

    protected function getShowReferee(RoundNumber $roundNumber): RefereeColumn
    {
        $planningConfig = $roundNumber->getValidPlanningConfig();
        if ($planningConfig->selfRefereeEnabled()) {
            return RefereeColumn::SelfReferee;
        } elseif ($roundNumber->getCompetition()->getReferees()->count() >= 1) {
            return RefereeColumn::Referee;
        }
        return RefereeColumn::None;
    }

    protected function getShowDateTime(RoundNumber $roundNumber): DateTimeColumn
    {
        $planningConfig = $roundNumber->getValidPlanningConfig();
        if (!$planningConfig->getEnableTime()) {
            return DateTimeColumn::None;
        }
        if ($this->parent->gamesOnSameDay($roundNumber)) {
            return DateTimeColumn::Time;
        }
        return DateTimeColumn::DateTime;
    }

    public function drawGamesHeader(RoundNumber $roundNumber, float $y): float
    {
        if ($this->hasOnlyAgainstGameMode($roundNumber->getCompetition())) {
            $gameLine = $this->getGameLineByGameMode(GameMode::Against);
        } else {
            $gameLine = $this->getGameLineByGameMode(GameMode::Single);
        }
        return $gameLine->drawHeader($this->someStructureCellNeedsRanking($roundNumber), $y);
    }

    protected function someStructureCellNeedsRanking(RoundNumber $roundNumber): bool
    {
        foreach ($roundNumber->getStructureCells() as $cell) {
            if ($cell->needsRanking()) {
                return true;
            }
        }
        return false;
    }

    public function drawGame(AgainstGame|TogetherGame $game, float $y, bool $striped = false): float
    {
        $gameFilter = $this->getGameFilter();
        if ($gameFilter !== null && !$gameFilter($game)) {
            return $y;
        }
        return $this->getGameLine($game)->drawGame($game, new Point(self::PAGEMARGIN, $y), $striped);
    }

    public function drawRecess(
        AgainstGame|TogetherGame $game,
        Recess $recess,
        Point $start
    ): Point {
        return $this->getGameLine($game)->drawRecess($recess, $start);
    }

    /**
     * @param RoundNumber $roundNumber
     * @return list<AgainstGame|TogetherGame>
     */
    public function getFilteredGames(RoundNumber $roundNumber): array
    {
        $games = [];
        foreach ($roundNumber->getRounds() as $round) {
            foreach ($round->getPoules() as $poule) {
                foreach ($poule->getGames() as $game) {
                    $gameFilter = $this->getGameFilter();
                    if ($gameFilter === null || $gameFilter($game)) {
                        $games[] = $game;
                    }
                }
            }
        }
        return $games;
    }

    public function drawRoundNumberHeader(RoundNumber $roundNumber, float $y): float
    {
        $this->setFillColor(new \Zend_Pdf_Color_GrayScale(1));
        $fontHeightSubHeader = $this->parent->getFontHeightSubHeader();
        $this->setFont($this->helper->getTimesFont(true), $this->parent->getFontHeightSubHeader());
        $subHeader = $this->parent->getStructureNameService()->getRoundNumberName($roundNumber);
        $cell = new Rectangle(
            new HorizontalLine(new Point(self::PAGEMARGIN, $y), $this->getDisplayWidth()),
            $fontHeightSubHeader
        );
        $this->drawCell($subHeader, $cell, Align::Center);
        $this->setFont($this->helper->getTimesFont(), $this->parent->getFontHeight());
        return $y - (2 * $fontHeightSubHeader);
    }


//    protected function getRoundNameStructure( Round $round, NameService $nameService ): string
//    {
//        $roundName = $nameService->getRoundName( $round );
//        if( $round->getNumber() === 2 and $round->getOpposingRound() !== null ) {
//            $roundName .= ' - ' . $nameService->getWinnersLosersDescription($round->getWinnersOrlosers()) . 's';
//        }
//        return $roundName;
//    }
}
