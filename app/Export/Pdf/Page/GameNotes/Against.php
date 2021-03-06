<?php
declare(strict_types=1);

namespace App\Export\Pdf\Page\GameNotes;

use App\Export\Pdf\Align;
use App\Export\Pdf\Document;
use App\Export\Pdf\Page\GameNotes;
use FCToernooi\QRService;
use App\Export\Pdf\Page\GameNotes as GameNotesBase;
use Sports\Game\Against as AgainstGame;
use Sports\Game\Together as TogetherGame;
use SportsHelpers\Against\Side as AgainstSide;
use Sports\Score\Config as ScoreConfig;
use Sports\Score\Config\Service as ScoreConfigService;
use FCToernooi\TranslationService;

class Against extends GameNotesBase
{
    public function __construct(Document $document, mixed $param1, AgainstGame $gameOne, AgainstGame|null $gameTwo)
    {
        parent::__construct($document, $param1, $gameOne, $gameTwo);
    }

    protected function drawPlaces(AgainstGame|TogetherGame $game, float $x, float $y, float $width, float $height): void
    {
        if ($game instanceof TogetherGame) {
            return;
        }
        $nameService = $this->getParent()->getNameService();
        $homePlaces = $game->getSidePlaces(AgainstSide::HOME);
        $home = $nameService->getPlacesFromName($homePlaces, false, count($homePlaces) === 1);
        $awayPlaces = $game->getSidePlaces(AgainstSide::AWAY);
        $away = $nameService->getPlacesFromName($homePlaces, false, count($awayPlaces) === 1);
        $this->drawCell($home . ' - ' . $away, $x, $y, $width, $height);
    }

    protected function drawGameRoundNumber(AgainstGame|TogetherGame $game, float $x, float $y, float $width, float $height): float
    {
        if ($game instanceof TogetherGame) {
            return $y;
        }
        $gameRoundNumber = (string)$game->getGameRoundNumber();
        $this->drawCell($gameRoundNumber, $x, $y, $width, $height);
        return $y - $height;
    }

    protected function drawScore(AgainstGame|TogetherGame $game, float $y): void
    {
        if ($game instanceof TogetherGame) {
            return;
        }
        $nameService = $this->getParent()->getNameService();
        $roundNumber = $game->getRound()->getNumber();
        $planningConfig = $roundNumber->getValidPlanningConfig();
        $firstScoreConfig = $game->getScoreConfig();
        $margin = GameNotes::Margin;
        $larger = 1.2;
        $height = GameNotes::RowHeight * $larger;
        $leftPartWidth = $this->getLeftPartWidth();
        $homeStart = $this->getStartDetailLabel();
        $homeWidth = $this->getDetailPartWidth();
        $sepStartX = $homeStart + $homeWidth;
        $awayStart = $this->getStartDetailValue();
        $dotsWidth = $this->getPartWidth();
        $unitStart = $awayStart + $dotsWidth + $margin;
        $unitWidth = $this->getPartWidth();
//        $detailValueWidth = $this->getDetailValueWidth($x);
//        $x2 = $this->getXSecondBorder() + ($margin * 0.5);

        // 2x font thuis - uit
        $this->setFont($this->getParent()->getFont(), $this->getParent()->getFontHeight() * $larger);
        $this->drawCell('wedstrijd', $this->getPageMargin(), $y, $leftPartWidth, $height, Align::Right);

        // COMPETITORS
        $home = $nameService->getPlacesFromName($game->getSidePlaces(AgainstSide::HOME), true, true);
        $this->drawCell($home, $homeStart, $y, $homeWidth, $height, Align::Right);
        $this->drawCell('-', $sepStartX, $y, GameNotesBase::Margin, $height, Align::Center);
        $away = $nameService->getPlacesFromName($game->getSidePlaces(AgainstSide::AWAY), true, true);
        $this->drawCell($away, $awayStart, $y, $dotsWidth, $height);
        $y -= 2 * $height;

        $this->setFont($this->getParent()->getFont(), $this->getParent()->getFontHeight() * $larger);

        $calculateScoreConfig = $firstScoreConfig->getCalculate();

        $dots = '...............';
        $nrOfScoreLines = $this->getNrOfScoreLines($game->getRound(), $game->getCompetitionSport());

        // DOTS
        if ($firstScoreConfig !== $calculateScoreConfig) {
            $yDelta = 0;
            for ($gameUnitNr = 1; $gameUnitNr <= $nrOfScoreLines; $gameUnitNr++) {
                $descr = $this->translationService->getScoreNameSingular($calculateScoreConfig) . ' ' . $gameUnitNr;
                $this->drawCell($descr, $this->getPageMargin(), $y - $yDelta, $leftPartWidth, $height, Align::Right);
                $this->drawCell($dots, $homeStart, $y - $yDelta, $homeWidth, $height, Align::Right);
                $this->drawCell('-', $sepStartX, $y - $yDelta, $margin, $height, Align::Center);
                $this->drawCell($dots, $awayStart, $y - $yDelta, $dotsWidth, $height);
                $yDelta += $height;
            }
        } else {
            $this->drawCell('score', $this->getPageMargin(), $y, $leftPartWidth, $height, Align::Right);
            $this->drawCell($dots, $homeStart, $y, $homeWidth, $height, Align::Right);
            $this->drawCell('-', $sepStartX, $y, $margin, $height, Align::Center);
            $this->drawCell($dots, $awayStart, $y, $dotsWidth, $height);
        }

        // SCOREUNITS
        $descr = $this->getInputScoreConfigDescription($firstScoreConfig);
        if ($firstScoreConfig !== $calculateScoreConfig) {
            $yDelta = 0;
            for ($gameUnitNr = 1; $gameUnitNr <= $nrOfScoreLines; $gameUnitNr++) {
                $this->drawCell($descr, $unitStart, $y - $yDelta, $unitWidth, $height, Align::Right);
                $yDelta += $height;
            }
            $y -= $yDelta;
        } else {
            $this->drawCell($descr, $unitStart, $y, $unitWidth, $height, Align::Right);
        }

        $y -= $height; // extra lege regel

        if ($planningConfig->getExtension()) {
            $this->drawCell('na verleng.', $this->getPageMargin(), $y, $leftPartWidth, $height, Align::Right);
            $this->drawCell($dots, $homeStart, $y, $homeWidth, $height, Align::Right);
            $this->drawCell('-', $sepStartX, $y, $margin, $height, Align::Center);
            $this->drawCell($dots, $awayStart, $y, $dotsWidth, $height);

            $name = $this->translationService->getScoreNamePlural($firstScoreConfig);
            $this->drawCell($name, $unitStart, $y, $unitWidth, $height, Align::Right);
        }
    }
}
