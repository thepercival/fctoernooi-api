<?php
/**
 * Created by PhpStorm.
 * User: coen
 * Date: 2-2-18
 * Time: 15:03
 */

namespace App\Export\Pdf\Page;

use App\Export\Pdf\Page as ToernooiPdfPage;
use Voetbal\Game;
use Voetbal\NameService;
use Voetbal\Sport\ScoreConfig as SportScoreConfig;
use Voetbal\Sport\ScoreConfig\Service as SportScoreConfigService;
use FCToernooi\TranslationService;

class Gamenotes extends ToernooiPdfPage
{
    /**
     * @var SportScoreConfigService
     */
    protected $sportScoreConfigService;
    /**
     * @var TranslationService
     */
    protected $translationService;

    protected $gameOne;
    protected $gameTwo;

    public function __construct( $param1, Game $gameA = null, Game $gameB = null )
    {
        parent::__construct( $param1 );
        $this->setLineWidth( 0.5 );
        $this->gameOne = $gameA;
        $this->gameTwo = $gameB;
        $this->sportScoreConfigService = new SportScoreConfigService();
        $this->translationService = new TranslationService();
    }

    public function getPageMargin(){ return 20; }
    public function getHeaderHeight(){ return 0; }

    public function draw()
    {
        $nY = $this->drawHeader( "wedstrijdbriefje" );
        $this->drawGame( $this->gameOne, $nY );

        $this->setLineColor(new \Zend_Pdf_Color_Html( "black" ));
        $this->setLineDashingPattern(array(10,10));
        $this->drawLine($this->getPageMargin(), $this->getHeight() / 2, $this->getWidth() - $this->getPageMargin(), $this->getHeight() / 2);
        $this->setLineDashingPattern(\Zend_Pdf_Page::LINE_DASHING_SOLID);
        if( $this->gameTwo !== null ) {
            $nY = $this->drawHeader( "wedstrijdbriefje", ( $this->getHeight() / 2 ) - $this->getPageMargin() );
            $this->drawGame($this->gameTwo, $nY);
        }
    }

    public function drawGame( Game $game, $nOffSetY )
    {
        $this->setFont( $this->getParent()->getFont(), $this->getParent()->getFontHeight() );
        $nY = $nOffSetY;

        $nFirstBorder = $this->getWidth() / 6;
        $nSecondBorder = $nFirstBorder + ( ( $this->getWidth() - ( $nFirstBorder + $this->getPageMargin() ) ) / 2 );
        $nMargin = 15;
        $nRowHeight = 20;

        $roundNumber = $game->getRound()->getNumber();
        $planningConfig = $roundNumber->getValidPlanningConfig();
        $planningService = $this->getParent()->getPlanningService();
        $nX = $nFirstBorder + $nMargin;
        $bNeedsRanking = $game->getPoule()->needsRanking();
        $nWidth = $this->getWidth() - ($this->getPageMargin() + $nX);
        $nWidthResult = $nWidth / 2;
        $nX2 = $nSecondBorder + ( $nMargin * 0.5 );

        $nameService = new NameService();
        $roundNumberName = $nameService->getRoundNumberName( $roundNumber );
        $this->drawCell( "ronde", $nX, $nY, $nWidthResult - ( $nMargin * 0.5 ), $nRowHeight, ToernooiPdfPage::ALIGNRIGHT );
        $this->drawCell( ':', $nSecondBorder, $nY, $nMargin, $nRowHeight );
        $this->drawCell( $roundNumberName, $nX2, $nY, $nWidthResult, $nRowHeight );
        $nY -= $nRowHeight;

        $sGame = $nameService->getPouleName($game->getPoule(), false);
        $sGmeDescription = $bNeedsRanking ? "poule" : "wedstrijd";
        $this->drawCell( $sGmeDescription, $nX, $nY, $nWidthResult - ( $nMargin * 0.5 ), $nRowHeight, ToernooiPdfPage::ALIGNRIGHT );
        $this->drawCell( ':', $nSecondBorder, $nY, $nMargin, $nRowHeight );
        $this->drawCell( $sGame, $nX2, $nY, $nWidthResult, $nRowHeight );
        $nY -= $nRowHeight;

        $this->drawCell( 'plekken', $nX, $nY, $nWidthResult - ( $nMargin * 0.5 ), $nRowHeight, ToernooiPdfPage::ALIGNRIGHT );
        $this->drawCell( ':', $nSecondBorder, $nY, $nMargin, $nRowHeight );
        $home = $nameService->getPlacesFromName( $game->getPlaces( Game::HOME ), false, !$planningConfig->getTeamup() );
        $away = $nameService->getPlacesFromName( $game->getPlaces( Game::AWAY ), false, !$planningConfig->getTeamup() );
        $this->drawCell( $home . " - " . $away, $nX2, $nY, $nWidthResult, $nRowHeight );
        $nY -= $nRowHeight;

        if( $bNeedsRanking ) {
            $this->drawCell( "speelronde", $nX, $nY, $nWidthResult - ( $nMargin * 0.5 ), $nRowHeight, ToernooiPdfPage::ALIGNRIGHT );
            $this->drawCell( ':', $nSecondBorder, $nY, $nMargin, $nRowHeight );
            $this->drawCell( $game->getRound()->getNumber()->getNumber(), $nX2, $nY, $nWidthResult, $nRowHeight );
            $nY -= $nRowHeight;
        }

        if( $roundNumber->getValidPlanningConfig()->getEnableTime() ) {
            setlocale(LC_ALL, 'nl_NL.UTF-8'); //
            $localDateTime = $game->getStartDateTime()->setTimezone(new \DateTimeZone('Europe/Amsterdam'));
            $dateTime = strtolower( $localDateTime->format("H:i") . "     " . strftime("%a %d %b %Y", $localDateTime->getTimestamp() ) );
            // $dateTime = strtolower( $localDateTime->format("H:i") . "     " . $localDateTime->format("D d M") );
            $duration = $planningConfig->getMinutesPerGame() . ' min.';
            if( $planningConfig->hasExtension() === true ) {
                $duration .= ' (' . $planningConfig->getMinutesPerGameExt() . ' min.)';
            }

            $this->drawCell( "tijdstip", $nX, $nY, $nWidthResult - ( $nMargin * 0.5 ), $nRowHeight, ToernooiPdfPage::ALIGNRIGHT );
            $this->drawCell( ':', $nSecondBorder, $nY, $nMargin, $nRowHeight );
            $this->drawCell( $dateTime, $nX2, $nY, $nWidthResult, $nRowHeight );
            $nY -= $nRowHeight;

            $this->drawCell( "duur", $nX, $nY, $nWidthResult - ( $nMargin * 0.5 ), $nRowHeight, ToernooiPdfPage::ALIGNRIGHT );
            $this->drawCell( ':', $nSecondBorder, $nY, $nMargin, $nRowHeight );
            $this->drawCell( $duration, $nX2, $nY, $nWidthResult, $nRowHeight );
            $nY -= $nRowHeight;
        }

        if( $game->getField() !== null ) {
            $this->drawCell( "veld", $nX, $nY, $nWidthResult - ( $nMargin * 0.5 ), $nRowHeight, ToernooiPdfPage::ALIGNRIGHT );
            $this->drawCell( ':', $nSecondBorder, $nY, $nMargin, $nRowHeight );
            $fieldDescription = $game->getField()->getName();
            if( $roundNumber->getCompetition()->hasMultipleSportConfigs() ) {
                $fieldDescription .= " - " . $game->getField()->getSport()->getName();
            }
            $this->drawCell( $fieldDescription, $nX2, $nY, $nWidthResult, $nRowHeight );
            $nY -= $nRowHeight;
        }

        if( $game->getReferee() !== null ) {
            $this->drawCell( "scheidrechter", $nX, $nY, $nWidthResult - ( $nMargin * 0.5 ), $nRowHeight, ToernooiPdfPage::ALIGNRIGHT );
            $this->drawCell( ':', $nSecondBorder, $nY, $nMargin, $nRowHeight );
            $this->drawCell( $game->getReferee()->getInitials(), $nX2, $nY, $nWidthResult, $nRowHeight );
            $nY -= $nRowHeight;
        } else if( $game->getRefereePlace() !== null ) {
            $this->drawCell( "scheidrechter", $nX, $nY, $nWidthResult - ( $nMargin * 0.5 ), $nRowHeight, ToernooiPdfPage::ALIGNRIGHT );
            $this->drawCell( ':', $nSecondBorder, $nY, $nMargin, $nRowHeight );
            $this->drawCell( $nameService->getPlaceName( $game->getRefereePlace(), true, true), $nX2, $nY, $nWidthResult, $nRowHeight );
            $nY -= $nRowHeight;
        }

        $nY -= $nRowHeight; // extra lege regel

        $larger = 1.2;
        $nY -= $nRowHeight * $larger;
        $nWidth = $nFirstBorder - $this->getPageMargin();

        // 2x font thuis - uit
        $this->setFont( $this->getParent()->getFont(), $this->getParent()->getFontHeight() * $larger );

        $this->drawCell( 'wedstrijd', $this->getPageMargin(), $nY, $nWidth, $nRowHeight * $larger, ToernooiPdfPage::ALIGNRIGHT );
        $home = $nameService->getPlacesFromName( $game->getPlaces( Game::HOME ), true, true );
        $this->drawCell( $home, $nX, $nY, $nWidthResult - ( $nMargin * 0.5 ), $nRowHeight * $larger, ToernooiPdfPage::ALIGNRIGHT );
        $this->drawCell( '-', $nSecondBorder, $nY, $nMargin, $nRowHeight * $larger );
        $away = $nameService->getPlacesFromName( $game->getPlaces( Game::AWAY ), true, true );
        $this->drawCell( $away, $nX2, $nY, $nWidthResult, $nRowHeight * $larger );
        $nY -= 3 * $nRowHeight; // extra lege regel

        $this->setFont( $this->getParent()->getFont(), $this->getParent()->getFontHeight() * $larger );
        $nX = $nFirstBorder + $nMargin;

        $n = $roundNumber->getNumber();
        $inputScoreConfig = $this->sportScoreConfigService->getInput( $game->getSportScoreConfig() );
        $calculateScoreConfig = $this->sportScoreConfigService->getCalculate( $game->getSportScoreConfig() );

        $dots = '...............';
        $dotsWidth = $this->getTextWidth( $dots );
        if( $inputScoreConfig !== null ) {
            if( $inputScoreConfig !== $calculateScoreConfig ) {
                $nYDelta = 0;
                $nrOfScoreLines = $this->getNrOfScoreLines($calculateScoreConfig->getMaximum());
                for( $gameUnitNr = 1 ; $gameUnitNr <= $nrOfScoreLines ; $gameUnitNr++ ) {
                    $descr = $this->translationService->getScoreNameMultiple(TranslationService::language, $calculateScoreConfig) . ' ' . $gameUnitNr;
                    $this->drawCell( $descr, $this->getPageMargin(), $nY - $nYDelta, $nWidth, $nRowHeight * $larger, ToernooiPdfPage::ALIGNRIGHT );
                    $this->drawCell( $dots, $nX, $nY - $nYDelta, $nSecondBorder - $nX, $nRowHeight * $larger, ToernooiPdfPage::ALIGNRIGHT);
                    $this->drawCell( '-', $nSecondBorder, $nY - $nYDelta, $nMargin, $nRowHeight * $larger );
                    $this->drawCell( $dots, $nX2, $nY - $nYDelta, $dotsWidth, $nRowHeight * $larger );
                    $nYDelta += $nRowHeight * $larger;
                }
            } else {
                $this->drawCell( 'uitslag', $this->getPageMargin(), $nY, $nWidth, $nRowHeight * $larger, ToernooiPdfPage::ALIGNRIGHT );
                $this->drawCell( $dots, $nX, $nY, $nSecondBorder - $nX, $nRowHeight * $larger, ToernooiPdfPage::ALIGNRIGHT);
                $this->drawCell( '-', $nSecondBorder, $nY, $nMargin, $nRowHeight * $larger );
                $this->drawCell( $dots, $nX2, $nY, $dotsWidth, $nRowHeight * $larger );
            }
        }


        if( $inputScoreConfig !== null ) {
            $descr = $this->getInputScoreConfigDescription( $inputScoreConfig, $planningConfig->getEnableTime() );
            if( $inputScoreConfig !== $calculateScoreConfig ) {
                $nYDelta = 0;
                $nrOfScoreLines = $this->getNrOfScoreLines($calculateScoreConfig->getMaximum());
                for( $gameUnitNr = 1 ; $gameUnitNr <= $nrOfScoreLines ; $gameUnitNr++ ) {
                    $this->drawCell($descr, $nX2 + $dotsWidth, $nY - $nYDelta,
                        $nWidthResult - ($this->getPageMargin() + $dotsWidth), $nRowHeight * $larger,
                        ToernooiPdfPage::ALIGNRIGHT);
                    $nYDelta += $nRowHeight * $larger;
                }
                $nY -= $nYDelta;
            } else {
                $this->drawCell( $descr, $nX2 + $dotsWidth, $nY, $nWidthResult - ( $this->getPageMargin() + $dotsWidth ), $nRowHeight * $larger, ToernooiPdfPage::ALIGNRIGHT );
            }
        }

        $nY -= $nRowHeight; // extra lege regel

        if( $planningConfig->hasExtension() ) {
            $this->drawCell( 'na verleng.', $this->getPageMargin(), $nY, $nWidth, $nRowHeight * $larger, ToernooiPdfPage::ALIGNRIGHT );
            $this->drawCell( $dots, $nX, $nY, $nSecondBorder - $nX, $nRowHeight * $larger, ToernooiPdfPage::ALIGNRIGHT);
            $this->drawCell( '-', $nSecondBorder, $nY, $nMargin, $nRowHeight * $larger );
            $this->drawCell( $dots, $nX2, $nY, $dotsWidth, $nRowHeight * $larger );
            if( $inputScoreConfig !== null ) {
                $name = $this->translationService->getScoreNameMultiple(TranslationService::language, $inputScoreConfig);
                $this->drawCell( $name, $nX2 + $dotsWidth, $nY, $nWidthResult - ( $this->getPageMargin() + $dotsWidth ), $nRowHeight  * $larger, ToernooiPdfPage::ALIGNRIGHT );
            }
        }
    }

    protected function getInputScoreConfigDescription( SportScoreConfig $inputScoreConfig, $timeEnabled): string {
        $direction = $this->getDirectionName($inputScoreConfig);
        $name = $this->translationService->getScoreNameMultiple(TranslationService::language, $inputScoreConfig);
        if( $inputScoreConfig->getMaximum() === 0 || $timeEnabled === true ) {
            return $name;
        }
        return $direction . ' ' . $inputScoreConfig->getMaximum() . ' ' . $name;
    }

    protected function getDirectionName(SportScoreConfig $scoreConfig ) {
        return $scoreConfig->getDirection() === SportScoreConfig::UPWARDS ? 'naar' : 'vanaf';
    }

    protected function getNrOfScoreLines( int $scoreConfigMax ) : int {
        $nrOfScoreLines = ($scoreConfigMax * 2) - 1;
        if( $nrOfScoreLines > 5 ) {
            $nrOfScoreLines = 5;
        }
        return $nrOfScoreLines;
    }
}