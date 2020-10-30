<?php

declare(strict_types=1);

namespace App\Export\Pdf\Page;

use App\Export\Pdf\Page as ToernooiPdfPage;
use FCToernooi\QRService;
use Sports\Game;
use Sports\NameService;
use Sports\Sport\ScoreConfig as SportScoreConfig;
use Sports\Sport\ScoreConfig\Service as SportScoreConfigService;
use FCToernooi\TranslationService;

class Gamenotes extends ToernooiPdfPage
{
    const MAXNROFSCORELINES = 5;

    /**
     * @var SportScoreConfigService
     */
    protected $sportScoreConfigService;
    /**
     * @var TranslationService
     */
    protected $translationService;
    /**
     * @var QRService
     */
    protected $qrService;
    /**
     * @var string
     */
    protected $qrCodeUrlPrefix;
    /**
     * @var Game
     */
    protected $gameOne;
    /**
     * @var Game
     */
    protected $gameTwo;

    public function __construct($param1, Game $gameA = null, Game $gameB = null)
    {
        parent::__construct($param1);
        $this->setLineWidth(0.5);
        $this->gameOne = $gameA;
        $this->gameTwo = $gameB;
        $this->sportScoreConfigService = new SportScoreConfigService();
        $this->translationService = new TranslationService();
        $this->qrService = new QRService();
    }

    public function getPageMargin()
    {
        return 20;
    }

    public function getHeaderHeight()
    {
        return 0;
    }

    protected function getQrCodeUrlPrefix()
    {
        if ($this->qrCodeUrlPrefix === null) {
            $this->qrCodeUrlPrefix = $this->getParent()->getUrl() . "admin/game/" . $this->getParent()->getTournament(
                )->getId() . "/";
        }
        return $this->qrCodeUrlPrefix;
    }

    public function draw()
    {
        $nY = $this->drawHeader("wedstrijdbriefje");
        $this->drawGame($this->gameOne, $nY);

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
            $nY = $this->drawHeader("wedstrijdbriefje", ($this->getHeight() / 2) - $this->getPageMargin());
            $this->drawGame($this->gameTwo, $nY);
        }
    }

    public function drawGame(Game $game, $nOffSetY)
    {
        $this->setFont($this->getParent()->getFont(), $this->getParent()->getFontHeight());
        $nY = $nOffSetY;

        $nFirstBorder = $this->getWidth() / 6;
        $nSecondBorder = $nFirstBorder + (($this->getWidth() - ($nFirstBorder + $this->getPageMargin())) / 2);
        $nMargin = 15;
        $nRowHeight = 20;

        $roundNumber = $game->getRound()->getNumber();
        $planningConfig = $roundNumber->getValidPlanningConfig();
        $nX = $nFirstBorder + $nMargin;
        $bNeedsRanking = $game->getPoule()->needsRanking();
        $nWidth = $this->getWidth() - ($this->getPageMargin() + $nX);
        $nWidthResult = $nWidth / 2;
        $nX2 = $nSecondBorder + ($nMargin * 0.5);

        // qr-code
        $url = $this->getQrCodeUrlPrefix() . $game->getId();

        $imgSize = $nFirstBorder - $this->getPageMargin();
        $imgSize *= 1.2;
        $qrPath = $this->qrService->writeGameToJpg($this->getParent()->getTournament(), $game, $url, (int)$imgSize);
        $img = \Zend_Pdf_Resource_ImageFactory::factory($qrPath);
        $this->drawImage($img, $this->getPageMargin(), $nY - $imgSize, $this->getPageMargin() + $imgSize, $nY);

        $nameService = $this->getParent()->getNameService();
        $roundNumberName = $nameService->getRoundNumberName($roundNumber);
        $this->drawCell("ronde", $nX, $nY, $nWidthResult - ($nMargin * 0.5), $nRowHeight, ToernooiPdfPage::ALIGNRIGHT);
        $this->drawCell(':', $nSecondBorder, $nY, $nMargin, $nRowHeight);
        $this->drawCell($roundNumberName, $nX2, $nY, $nWidthResult, $nRowHeight);
        $nY -= $nRowHeight;

        $sGame = $nameService->getPouleName($game->getPoule(), false);
        $sGmeDescription = $bNeedsRanking ? "poule" : "wedstrijd";
        $this->drawCell(
            $sGmeDescription,
            $nX,
            $nY,
            $nWidthResult - ($nMargin * 0.5),
            $nRowHeight,
            ToernooiPdfPage::ALIGNRIGHT
        );
        $this->drawCell(':', $nSecondBorder, $nY, $nMargin, $nRowHeight);
        $this->drawCell($sGame, $nX2, $nY, $nWidthResult, $nRowHeight);
        $nY -= $nRowHeight;

        $this->drawCell(
            'plekken',
            $nX,
            $nY,
            $nWidthResult - ($nMargin * 0.5),
            $nRowHeight,
            ToernooiPdfPage::ALIGNRIGHT
        );
        $this->drawCell(':', $nSecondBorder, $nY, $nMargin, $nRowHeight);
        $home = $nameService->getPlacesFromName($game->getPlaces(Game::HOME), false, !$planningConfig->getTeamup());
        $away = $nameService->getPlacesFromName($game->getPlaces(Game::AWAY), false, !$planningConfig->getTeamup());
        $this->drawCell($home . " - " . $away, $nX2, $nY, $nWidthResult, $nRowHeight);
        $nY -= $nRowHeight;

        if ($bNeedsRanking) {
            $this->drawCell(
                "speelronde",
                $nX,
                $nY,
                $nWidthResult - ($nMargin * 0.5),
                $nRowHeight,
                ToernooiPdfPage::ALIGNRIGHT
            );
            $this->drawCell(':', $nSecondBorder, $nY, $nMargin, $nRowHeight);
            $this->drawCell($game->getRound()->getNumber()->getNumber(), $nX2, $nY, $nWidthResult, $nRowHeight);
            $nY -= $nRowHeight;
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
                $nX,
                $nY,
                $nWidthResult - ($nMargin * 0.5),
                $nRowHeight,
                ToernooiPdfPage::ALIGNRIGHT
            );
            $this->drawCell(':', $nSecondBorder, $nY, $nMargin, $nRowHeight);
            $this->drawCell($dateTime, $nX2, $nY, $nWidthResult, $nRowHeight);
            $nY -= $nRowHeight;

            $this->drawCell(
                "duur",
                $nX,
                $nY,
                $nWidthResult - ($nMargin * 0.5),
                $nRowHeight,
                ToernooiPdfPage::ALIGNRIGHT
            );
            $this->drawCell(':', $nSecondBorder, $nY, $nMargin, $nRowHeight);
            $this->drawCell($duration, $nX2, $nY, $nWidthResult, $nRowHeight);
            $nY -= $nRowHeight;
        }

        {
            $this->drawCell(
                "veld",
                $nX,
                $nY,
                $nWidthResult - ($nMargin * 0.5),
                $nRowHeight,
                ToernooiPdfPage::ALIGNRIGHT
            );
            $this->drawCell(':', $nSecondBorder, $nY, $nMargin, $nRowHeight);
            $fieldDescription = $game->getField()->getName();
            if ($roundNumber->getCompetition()->hasMultipleSportConfigs()) {
                $fieldDescription .= " - " . $game->getField()->getSport()->getName();
            }
            $this->drawCell($fieldDescription, $nX2, $nY, $nWidthResult, $nRowHeight);
            $nY -= $nRowHeight;
        }

        if ($game->getReferee() !== null) {
            $this->drawCell(
                "scheidsrechter",
                $nX,
                $nY,
                $nWidthResult - ($nMargin * 0.5),
                $nRowHeight,
                ToernooiPdfPage::ALIGNRIGHT
            );
            $this->drawCell(':', $nSecondBorder, $nY, $nMargin, $nRowHeight);
            $this->drawCell($game->getReferee()->getInitials(), $nX2, $nY, $nWidthResult, $nRowHeight);
            $nY -= $nRowHeight;
        } else {
            if ($game->getRefereePlace() !== null) {
                $this->drawCell(
                    "scheidsrechter",
                    $nX,
                    $nY,
                    $nWidthResult - ($nMargin * 0.5),
                    $nRowHeight,
                    ToernooiPdfPage::ALIGNRIGHT
                );
                $this->drawCell(':', $nSecondBorder, $nY, $nMargin, $nRowHeight);
                $this->drawCell(
                    $nameService->getPlaceName($game->getRefereePlace(), true, true),
                    $nX2,
                    $nY,
                    $nWidthResult,
                    $nRowHeight
                );
                $nY -= $nRowHeight;
            }
        }
        $firstScoreConfig = $game->getSportScoreConfig();

        $this->drawCell("score", $nX, $nY, $nWidthResult - ($nMargin * 0.5), $nRowHeight, ToernooiPdfPage::ALIGNRIGHT);
        $this->drawCell(':', $nSecondBorder, $nY, $nMargin, $nRowHeight);
        $this->drawCell($this->getScoreConfigDescription($firstScoreConfig), $nX2, $nY, $nWidthResult, $nRowHeight);
        $nY -= $nRowHeight;

        $nY -= $nRowHeight; // extra lege regel

        $larger = 1.2;
        $nWidth = $nFirstBorder - $this->getPageMargin();

        // 2x font thuis - uit
        $this->setFont($this->getParent()->getFont(), $this->getParent()->getFontHeight() * $larger);
        $this->drawCell(
            'wedstrijd',
            $this->getPageMargin(),
            $nY,
            $nWidth,
            $nRowHeight * $larger,
            ToernooiPdfPage::ALIGNRIGHT
        );
        $home = $nameService->getPlacesFromName($game->getPlaces(Game::HOME), true, true);
        $this->drawCell(
            $home,
            $nX,
            $nY,
            $nWidthResult - ($nMargin * 0.5),
            $nRowHeight * $larger,
            ToernooiPdfPage::ALIGNRIGHT
        );
        $this->drawCell('-', $nSecondBorder, $nY, $nMargin, $nRowHeight * $larger);
        $away = $nameService->getPlacesFromName($game->getPlaces(Game::AWAY), true, true);
        $this->drawCell($away, $nX2, $nY, $nWidthResult, $nRowHeight * $larger);
        $nY -= 2 * $nRowHeight; // extra lege regel

        $this->setFont($this->getParent()->getFont(), $this->getParent()->getFontHeight() * $larger);
        $nX = $nFirstBorder + $nMargin;

        $calculateScoreConfig = $firstScoreConfig->getCalculate();

        $dots = '...............';
        $dotsWidth = $this->getTextWidth($dots);

        $maxNrOfScoreLines = self::MAXNROFSCORELINES - ($planningConfig->getExtension() ? 1 : 0);
        if ($firstScoreConfig !== $calculateScoreConfig) {
            $nYDelta = 0;

            $nrOfScoreLines = $this->getNrOfScoreLines($calculateScoreConfig->getMaximum());
            for ($gameUnitNr = 1; $gameUnitNr <= $nrOfScoreLines && $gameUnitNr <= $maxNrOfScoreLines; $gameUnitNr++) {
                $descr = $this->translationService->getScoreNameSingular($calculateScoreConfig) . ' ' . $gameUnitNr;
                $this->drawCell(
                    $descr,
                    $this->getPageMargin(),
                    $nY - $nYDelta,
                    $nWidth,
                    $nRowHeight * $larger,
                    ToernooiPdfPage::ALIGNRIGHT
                );
                $this->drawCell(
                    $dots,
                    $nX,
                    $nY - $nYDelta,
                    $nSecondBorder - $nX,
                    $nRowHeight * $larger,
                    ToernooiPdfPage::ALIGNRIGHT
                );
                $this->drawCell('-', $nSecondBorder, $nY - $nYDelta, $nMargin, $nRowHeight * $larger);
                $this->drawCell($dots, $nX2, $nY - $nYDelta, $dotsWidth, $nRowHeight * $larger);
                $nYDelta += $nRowHeight * $larger;
            }
        } else {
            $this->drawCell(
                'uitslag',
                $this->getPageMargin(),
                $nY,
                $nWidth,
                $nRowHeight * $larger,
                ToernooiPdfPage::ALIGNRIGHT
            );
            $this->drawCell($dots, $nX, $nY, $nSecondBorder - $nX, $nRowHeight * $larger, ToernooiPdfPage::ALIGNRIGHT);
            $this->drawCell('-', $nSecondBorder, $nY, $nMargin, $nRowHeight * $larger);
            $this->drawCell($dots, $nX2, $nY, $dotsWidth, $nRowHeight * $larger);
        }


        $descr = $this->getInputScoreConfigDescription($firstScoreConfig, $planningConfig->getEnableTime());
        if ($firstScoreConfig !== $calculateScoreConfig) {
            $nYDelta = 0;
            $nrOfScoreLines = $this->getNrOfScoreLines($calculateScoreConfig->getMaximum());
            for ($gameUnitNr = 1; $gameUnitNr <= $nrOfScoreLines && $gameUnitNr <= $maxNrOfScoreLines; $gameUnitNr++) {
                $this->drawCell(
                    $descr,
                    $nX2 + $dotsWidth,
                    $nY - $nYDelta,
                    $nWidthResult - ($this->getPageMargin() + $dotsWidth),
                    $nRowHeight * $larger,
                    ToernooiPdfPage::ALIGNRIGHT
                );
                $nYDelta += $nRowHeight * $larger;
            }
            $nY -= $nYDelta;
        } else {
            $this->drawCell(
                $descr,
                $nX2 + $dotsWidth,
                $nY,
                $nWidthResult - ($this->getPageMargin() + $dotsWidth),
                $nRowHeight * $larger,
                ToernooiPdfPage::ALIGNRIGHT
            );
        }


        $nY -= $nRowHeight; // extra lege regel

        if ($planningConfig->getExtension()) {
            $this->drawCell(
                'na verleng.',
                $this->getPageMargin(),
                $nY,
                $nWidth,
                $nRowHeight * $larger,
                ToernooiPdfPage::ALIGNRIGHT
            );
            $this->drawCell($dots, $nX, $nY, $nSecondBorder - $nX, $nRowHeight * $larger, ToernooiPdfPage::ALIGNRIGHT);
            $this->drawCell('-', $nSecondBorder, $nY, $nMargin, $nRowHeight * $larger);
            $this->drawCell($dots, $nX2, $nY, $dotsWidth, $nRowHeight * $larger);

            $name = $this->translationService->getScoreNamePlural($firstScoreConfig);
            $this->drawCell(
                $name,
                $nX2 + $dotsWidth,
                $nY,
                $nWidthResult - ($this->getPageMargin() + $dotsWidth),
                $nRowHeight * $larger,
                ToernooiPdfPage::ALIGNRIGHT
            );
        }
    }

    protected function getInputScoreConfigDescription(SportScoreConfig $firstScoreConfig, $timeEnabled): string
    {
        $scoreNamePlural = $this->translationService->getScoreNamePlural($firstScoreConfig);
        if ($firstScoreConfig->getMaximum() === 0) {
            return $scoreNamePlural;
        }
        $direction = $this->getDirectionName($firstScoreConfig);
        return $direction . ' ' . $firstScoreConfig->getMaximum() . ' ' . $scoreNamePlural;
    }

    protected function getScoreConfigDescription(SportScoreConfig $scoreConfig): string
    {
        $text = "";
        if ($scoreConfig->hasNext() && $scoreConfig->getNext()->getEnabled()) {
            if ($scoreConfig->getNext()->getMaximum() === 0) {
                $text .= "zoveel mogelijk ";
                $text .= $this->translationService->getScoreNamePlural($scoreConfig->getNext());
            } else {
                $text .= "eerst bij ";
                $text .= $scoreConfig->getNext()->getMaximum() . " ";
                $text .= $this->translationService->getScoreNamePlural($scoreConfig->getNext());
            }
            $text .= ", " . $scoreConfig->getMaximum() . " ";
            $text .= $this->translationService->getScoreNamePlural($scoreConfig) . " per ";
            $text .= $this->translationService->getScoreNameSingular($scoreConfig->getNext());
        } else {
            if ($scoreConfig->getMaximum() === 0) {
                $text .= "zoveel mogelijk ";
                $text .= $this->translationService->getScoreNamePlural($scoreConfig);
            } else {
                $text .= "eerst bij ";
                $text .= $scoreConfig->getMaximum() . " ";
                $text .= $this->translationService->getScoreNamePlural($scoreConfig);
            }
        }
        return $text;
    }

    protected function getDirectionName(SportScoreConfig $scoreConfig)
    {
        return $this->translationService->getScoreDirection(TranslationService::language, $scoreConfig->getDirection());
    }

    protected function getNrOfScoreLines(int $scoreConfigMax): int
    {
        return (($scoreConfigMax * 2) - 1);
    }
}
