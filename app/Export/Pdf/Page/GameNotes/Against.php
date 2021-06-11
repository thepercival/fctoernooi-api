<?php
declare(strict_types=1);

namespace App\Export\Pdf\Page\GameNotes;

use App\Export\Pdf\Align;
use App\Export\Pdf\Document;
use FCToernooi\QRService;
use App\Export\Pdf\Page\GameNotes as GameNotesBase;
use Sports\Game\Against as AgainstGame;
use SportsHelpers\Against\Side as AgainstSide;
use Sports\Score\Config as ScoreConfig;
use Sports\Score\Config\Service as ScoreConfigService;
use FCToernooi\TranslationService;

class Against extends GameNotesBase
{
    public function __construct(Document $document, mixed $param1, protected AgainstGame $gameOne, protected AgainstGame|null $gameTwo)
    {
        parent::__construct($document, $param1);
        $this->setLineWidth(0.5);
        $this->scoreConfigService = new ScoreConfigService();
        $this->translationService = new TranslationService();
        $this->qrService = new QRService();
    }


    public function draw(): void
    {
        $y = $this->drawHeader("wedstrijdbriefje");
        $this->drawGame($this->gameOne, $y);

        $this->setLineColor(new \Zend_Pdf_Color_Html("black"));
        $this->setLineDashingPattern(array(10, 10));
        $this->drawLine(
            $this->getPageMargin(),
            $this->getHeight() / 2,
            $this->getWidth() - $this->getPageMargin(),
            $this->getHeight() / 2
        );
        $this->setLineDashingPattern(\Zend_Pdf_Page::LINE_DASHING_SOLID);
        if ($this->gameTwo !== null) {
            $y = $this->drawHeader("wedstrijdbriefje", ($this->getHeight() / 2) - $this->getPageMargin());
            $this->drawGame($this->gameTwo, $y);
        }
    }

    public function drawGame(AgainstGame $game, float $nOffSetY): void
    {
        $this->setFont($this->getParent()->getFont(), $this->getParent()->getFontHeight());
        $y = $nOffSetY;

        $nFirstBorder = $this->getWidth() / 6;
        $nSecondBorder = $nFirstBorder + (($this->getWidth() - ($nFirstBorder + $this->getPageMargin())) / 2);
        $nMargin = 15;
        $nRowHeight = 20;

        $roundNumber = $game->getRound()->getNumber();
        $planningConfig = $roundNumber->getValidPlanningConfig();
        $x = $nFirstBorder + $nMargin;
        $bNeedsRanking = $game->getPoule()->needsRanking();
        $nWidth = $this->getWidth() - ($this->getPageMargin() + $x);
        $nWidthResult = $nWidth / 2;
        $x2 = $nSecondBorder + ($nMargin * 0.5);

        // qr-code
        $url = $this->getQrCodeUrlPrefix() . (string)$game->getId();

        $imgSize = $nFirstBorder - $this->getPageMargin();
        $imgSize *= 1.2;
        $qrPath = $this->qrService->writeGameToJpg($this->getParent()->getTournament(), $game, $url, (int)$imgSize);
        $img = \Zend_Pdf_Resource_ImageFactory::factory($qrPath);
        $this->drawImage($img, $this->getPageMargin(), $y - $imgSize, $this->getPageMargin() + $imgSize, $y);

        $nameService = $this->getParent()->getNameService();
        $roundNumberName = $nameService->getRoundNumberName($roundNumber);
        $this->drawCell("ronde", $x, $y, $nWidthResult - ($nMargin * 0.5), $nRowHeight, Align::Right);
        $this->drawCell(':', $nSecondBorder, $y, $nMargin, $nRowHeight);
        $this->drawCell($roundNumberName, $x2, $y, $nWidthResult, $nRowHeight);
        $y -= $nRowHeight;

        $sGame = $nameService->getPouleName($game->getPoule(), false);
        $sGmeDescription = $bNeedsRanking ? "poule" : "wedstrijd";
        $this->drawCell(
            $sGmeDescription,
            $x,
            $y,
            $nWidthResult - ($nMargin * 0.5),
            $nRowHeight,
            Align::Right
        );
        $this->drawCell(':', $nSecondBorder, $y, $nMargin, $nRowHeight);
        $this->drawCell($sGame, $x2, $y, $nWidthResult, $nRowHeight);
        $y -= $nRowHeight;

        $this->drawCell(
            'plekken',
            $x,
            $y,
            $nWidthResult - ($nMargin * 0.5),
            $nRowHeight,
            Align::Right
        );
        $this->drawCell(':', $nSecondBorder, $y, $nMargin, $nRowHeight);
        $homePlaces = $game->getSidePlaces(AgainstSide::HOME);
        $home = $nameService->getPlacesFromName($homePlaces, false, count($homePlaces) === 1);
        $awayPlaces = $game->getSidePlaces(AgainstSide::AWAY);
        $away = $nameService->getPlacesFromName($homePlaces, false, count($awayPlaces) === 1);
        $this->drawCell($home . " - " . $away, $x2, $y, $nWidthResult, $nRowHeight);
        $y -= $nRowHeight;

        if ($bNeedsRanking) {
            $this->drawCell(
                "speelronde",
                $x,
                $y,
                $nWidthResult - ($nMargin * 0.5),
                $nRowHeight,
                Align::Right
            );
            $this->drawCell(':', $nSecondBorder, $y, $nMargin, $nRowHeight);
            $this->drawCell((string)$game->getRound()->getNumberAsValue(), $x2, $y, $nWidthResult, $nRowHeight);
            $y -= $nRowHeight;
        }

        if ($roundNumber->getValidPlanningConfig()->getEnableTime()) {
            setlocale(LC_ALL, 'nl_NL.UTF-8'); //
            $localDateTime = $game->getStartDateTime()->setTimezone(new \DateTimeZone('Europe/Amsterdam'));
            $dateTime = strtolower(
                $localDateTime->format("H:i") . "     " . strftime("%a %d %b %Y", $localDateTime->getTimestamp())
            );
            // $dateTime = strtolower( $localDateTime->format("H:i") . "     " . $localDateTime->format("D d M") );
            $duration = $planningConfig->getMinutesPerGame() . ' min.';
            if ($planningConfig->getExtension()) {
                $duration .= ' (' . $planningConfig->getMinutesPerGameExt() . ' min.)';
            }

            $this->drawCell(
                "tijdstip",
                $x,
                $y,
                $nWidthResult - ($nMargin * 0.5),
                $nRowHeight,
                Align::Right
            );
            $this->drawCell(':', $nSecondBorder, $y, $nMargin, $nRowHeight);
            $this->drawCell($dateTime, $x2, $y, $nWidthResult, $nRowHeight);
            $y -= $nRowHeight;

            $this->drawCell(
                "duur",
                $x,
                $y,
                $nWidthResult - ($nMargin * 0.5),
                $nRowHeight,
                Align::Right
            );
            $this->drawCell(':', $nSecondBorder, $y, $nMargin, $nRowHeight);
            $this->drawCell($duration, $x2, $y, $nWidthResult, $nRowHeight);
            $y -= $nRowHeight;
        }

        {
            $this->drawCell(
                "veld",
                $x,
                $y,
                $nWidthResult - ($nMargin * 0.5),
                $nRowHeight,
                Align::Right
            );
            $this->drawCell(':', $nSecondBorder, $y, $nMargin, $nRowHeight);
            $fieldDescription = '';
            $field = $game->getField();
            if ($field !== null) {
                $fieldName = $field->getName();
                if ($fieldName !== null) {
                    $fieldDescription = $fieldName;
                }
            }
            if ($roundNumber->getCompetition()->hasMultipleSports()) {
                $fieldDescription .= ' - ' . $game->getCompetitionSport()->getSport()->getName();
            }
            $this->drawCell($fieldDescription, $x2, $y, $nWidthResult, $nRowHeight);
            $y -= $nRowHeight;
        }
        $referee = $game->getReferee();
        if ($referee !== null) {
            $this->drawCell(
                "scheidsrechter",
                $x,
                $y,
                $nWidthResult - ($nMargin * 0.5),
                $nRowHeight,
                Align::Right
            );
            $this->drawCell(':', $nSecondBorder, $y, $nMargin, $nRowHeight);
            $this->drawCell($referee->getInitials(), $x2, $y, $nWidthResult, $nRowHeight);
            $y -= $nRowHeight;
        } else {
            $refereePlace = $game->getRefereePlace();
            if ($refereePlace !== null) {
                $this->drawCell(
                    "scheidsrechter",
                    $x,
                    $y,
                    $nWidthResult - ($nMargin * 0.5),
                    $nRowHeight,
                    Align::Right
                );
                $this->drawCell(':', $nSecondBorder, $y, $nMargin, $nRowHeight);
                $this->drawCell(
                    $nameService->getPlaceName($refereePlace, true, true),
                    $x2,
                    $y,
                    $nWidthResult,
                    $nRowHeight
                );
                $y -= $nRowHeight;
            }
        }
        $firstScoreConfig = $game->getScoreConfig();

        $this->drawCell("score", $x, $y, $nWidthResult - ($nMargin * 0.5), $nRowHeight, Align::Right);
        $this->drawCell(':', $nSecondBorder, $y, $nMargin, $nRowHeight);
        $this->drawCell($this->getScoreConfigDescription($firstScoreConfig), $x2, $y, $nWidthResult, $nRowHeight);
        $y -= $nRowHeight;

        $y -= $nRowHeight; // extra lege regel

        $larger = 1.2;
        $nWidth = $nFirstBorder - $this->getPageMargin();

        // 2x font thuis - uit
        $this->setFont($this->getParent()->getFont(), $this->getParent()->getFontHeight() * $larger);
        $this->drawCell(
            'wedstrijd',
            $this->getPageMargin(),
            $y,
            $nWidth,
            $nRowHeight * $larger,
            Align::Right
        );
        $home = $nameService->getPlacesFromName($game->getSidePlaces(AgainstSide::HOME), true, true);
        $this->drawCell(
            $home,
            $x,
            $y,
            $nWidthResult - ($nMargin * 0.5),
            $nRowHeight * $larger,
            Align::Right
        );
        $this->drawCell('-', $nSecondBorder, $y, $nMargin, $nRowHeight * $larger);
        $away = $nameService->getPlacesFromName($game->getSidePlaces(AgainstSide::AWAY), true, true);
        $this->drawCell($away, $x2, $y, $nWidthResult, $nRowHeight * $larger);
        $y -= 2 * $nRowHeight; // extra lege regel

        $this->setFont($this->getParent()->getFont(), $this->getParent()->getFontHeight() * $larger);
        $x = $nFirstBorder + $nMargin;

        $calculateScoreConfig = $firstScoreConfig->getCalculate();

        $dots = '...............';
        $dotsWidth = $this->getTextWidth($dots);

        $maxNrOfScoreLines = self::MAXNROFSCORELINES - ($planningConfig->getExtension() ? 1 : 0);
        if ($firstScoreConfig !== $calculateScoreConfig) {
            $yDelta = 0;

            $nrOfScoreLines = $this->getNrOfScoreLines($calculateScoreConfig->getMaximum());
            for ($gameUnitNr = 1; $gameUnitNr <= $nrOfScoreLines && $gameUnitNr <= $maxNrOfScoreLines; $gameUnitNr++) {
                $descr = $this->translationService->getScoreNameSingular($calculateScoreConfig) . ' ' . $gameUnitNr;
                $this->drawCell(
                    $descr,
                    $this->getPageMargin(),
                    $y - $yDelta,
                    $nWidth,
                    $nRowHeight * $larger,
                    Align::Right
                );
                $this->drawCell(
                    $dots,
                    $x,
                    $y - $yDelta,
                    $nSecondBorder - $x,
                    $nRowHeight * $larger,
                    Align::Right
                );
                $this->drawCell('-', $nSecondBorder, $y - $yDelta, $nMargin, $nRowHeight * $larger);
                $this->drawCell($dots, $x2, $y - $yDelta, $dotsWidth, $nRowHeight * $larger);
                $yDelta += $nRowHeight * $larger;
            }
        } else {
            $this->drawCell(
                'uitslag',
                $this->getPageMargin(),
                $y,
                $nWidth,
                $nRowHeight * $larger,
                Align::Right
            );
            $this->drawCell($dots, $x, $y, $nSecondBorder - $x, $nRowHeight * $larger, Align::Right);
            $this->drawCell('-', $nSecondBorder, $y, $nMargin, $nRowHeight * $larger);
            $this->drawCell($dots, $x2, $y, $dotsWidth, $nRowHeight * $larger);
        }


        $descr = $this->getInputScoreConfigDescription($firstScoreConfig);
        if ($firstScoreConfig !== $calculateScoreConfig) {
            $yDelta = 0;
            $nrOfScoreLines = $this->getNrOfScoreLines($calculateScoreConfig->getMaximum());
            for ($gameUnitNr = 1; $gameUnitNr <= $nrOfScoreLines && $gameUnitNr <= $maxNrOfScoreLines; $gameUnitNr++) {
                $this->drawCell(
                    $descr,
                    $x2 + $dotsWidth,
                    $y - $yDelta,
                    $nWidthResult - ($this->getPageMargin() + $dotsWidth),
                    $nRowHeight * $larger,
                    Align::Right
                );
                $yDelta += $nRowHeight * $larger;
            }
            $y -= $yDelta;
        } else {
            $this->drawCell(
                $descr,
                $x2 + $dotsWidth,
                $y,
                $nWidthResult - ($this->getPageMargin() + $dotsWidth),
                $nRowHeight * $larger,
                Align::Right
            );
        }


        $y -= $nRowHeight; // extra lege regel

        if ($planningConfig->getExtension()) {
            $this->drawCell(
                'na verleng.',
                $this->getPageMargin(),
                $y,
                $nWidth,
                $nRowHeight * $larger,
                Align::Right
            );
            $this->drawCell($dots, $x, $y, $nSecondBorder - $x, $nRowHeight * $larger, Align::Right);
            $this->drawCell('-', $nSecondBorder, $y, $nMargin, $nRowHeight * $larger);
            $this->drawCell($dots, $x2, $y, $dotsWidth, $nRowHeight * $larger);

            $name = $this->translationService->getScoreNamePlural($firstScoreConfig);
            $this->drawCell(
                $name,
                $x2 + $dotsWidth,
                $y,
                $nWidthResult - ($this->getPageMargin() + $dotsWidth),
                $nRowHeight * $larger,
                Align::Right
            );
        }
    }

    protected function getNrOfScoreLines(int $scoreConfigMax): int
    {
        return (($scoreConfigMax * 2) - 1);
    }
}
