<?php

declare(strict_types=1);

namespace App\Export\Pdf\Page;

use App\Export\Pdf\Align;
use App\Export\Pdf\Document\Planning as PlanningDocument;
use App\Export\Pdf\Drawers\GameLine\Against as AgainstGameLine;
use App\Export\Pdf\Drawers\GameLine\Column\DateTime as DateTimeColumn;
use App\Export\Pdf\Drawers\GameLine\Together as TogetherGameLine;
use App\Export\Pdf\Line\Horizontal as HorizontalLine;
use App\Export\Pdf\Page as ToernooiPdfPage;
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

    public function __construct(PlanningDocument $document, mixed $param1, protected string $title)
    {
        parent::__construct($document, $param1);
        // $this->setFont($this->helper->getTimesFont(), $this->parent->getFontHeight());
        $this->setLineWidth(0.5);
    }

    public function getParent(): PlanningDocument
    {
        return $this->parent;
    }

//    public function getRowHeight(): int
//    {
//        return $this->parent->getConfig()->getRowHeight();
//    }

    public function getTitle(): string
    {
        return $this->title;
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
        $this->againstGameLine = null;
        $this->togetherGameLine = null;

        $competitionSports = $roundNumber->getCompetitionSports();
        foreach ($competitionSports as $competitionSport) {
            $sportVariant = $competitionSport->createVariant();
            if ($sportVariant instanceof AgainstSportVariant) {
                if ($this->againstGameLine === null) {
                    $this->againstGameLine = new AgainstGameLine(
                        $this,
                        $this->parent->getGameLineConfig(),
                        $roundNumber
                    );
                }
            } else {
                if ($this->togetherGameLine === null) {
                    $this->togetherGameLine = new TogetherGameLine(
                        $this,
                        $this->parent->getGameLineConfig(),
                        $roundNumber
                    );
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

    public function drawGamesHeader(RoundNumber $roundNumber, Rectangle $rectangle): void
    {
        $this->setFont($this->helper->getTimesFont(), $this->parent->getGameLineConfig()->getFontHeight());
        if ($this->hasOnlyAgainstGameMode($roundNumber->getCompetition())) {
            $gameLine = $this->getGameLineByGameMode(GameMode::Against);
        } else {
            $gameLine = $this->getGameLineByGameMode(GameMode::Single);
        }
        $gameLine->drawTableHeader($this->someStructureCellNeedsRanking($roundNumber), $rectangle);
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

    public function drawGame(
        AgainstGame|TogetherGame $game,
        HorizontalLine $horStartLine,
        bool $striped = false
    ): HorizontalLine {
        $gameFilter = $this->getGameFilter();
        if ($gameFilter !== null && !$gameFilter($game)) {
            return $horStartLine;
        }
        return $this->getGameLine($game)->drawGame($game, $horStartLine, $striped);
    }

    public function drawRecess(AgainstGame|TogetherGame $game, Recess $recess, Rectangle $rectangle): void
    {
        $dateTimeColumn = DateTimeColumn::getValue($game->getRound()->getNumber());
        $this->getGameLine($game)->drawRecess($recess, $rectangle, $dateTimeColumn);
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

    public function drawRoundNumberHeader(RoundNumber $roundNumber, HorizontalLine $horLine): HorizontalLine
    {
        $this->setFillColor(new \Zend_Pdf_Color_GrayScale(1));
        $roundNumberHeaderHeight = $this->parent->getGamesConfig()->getRoundNumberHeaderHeight();
        $roundNumberHeaderFontHeight = $this->parent->getGamesConfig()->getRoundNumberHeaderFontHeight();
        $this->setFont($this->helper->getTimesFont(true), $roundNumberHeaderFontHeight);
        $roundNumberName = $this->getStructureNameService()->getRoundNumberName($roundNumber);
        $cell = new Rectangle($horLine, -$roundNumberHeaderHeight);
        $this->drawCell($roundNumberName, $cell, Align::Center);
        // $this->setFont($this->helper->getTimesFont(), $this->getParent()->getConfig()->getFontHeight());
        return $horLine->addY(-(2 * $roundNumberHeaderHeight));
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
