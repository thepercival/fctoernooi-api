<?php
/**
 * Created by PhpStorm.
 * User: coen
 * Date: 2-2-18
 * Time: 15:03
 */

namespace FCToernooi\Pdf\Page;

use \FCToernooi\Pdf\Page as ToernooiPdfPage;
use Voetbal\Game;

class ScheduledGames extends ToernooiPdfPage
{
//    protected $m_sOuterBorder = "black";
//    protected $m_sInnerBorder = "#A8A8A8";
//    protected $m_sOdd = "#F0F0F0";
//    protected $m_sEven = "white";
    //protected $m_bHeadersFirstTime = true;
    protected $gameOne;
    protected $gameTwo;

    public function __construct( $param1, Game $gameA = null, Game $gameB = null )
    {
        parent::__construct( $param1 );
        $this->setLineWidth( 0.5 );
        $this->gameOne = $gameA;
        $this->gameTwo = $gameB;
    }

    public function getPageMargin(){ return 20; }
    public function getHeaderHeight(){ return 0; }

    public function draw()
    {
        $this->drawGame( $this->gameOne, $this->getHeight() );

        $this->setLineColor(new \Zend_Pdf_Color_Html( "black" ));
        $this->setLineDashingPattern(array(10,10));
        $this->drawLine(0, $this->getHeight() / 2, $this->getWidth(), $this->getHeight() / 2);
        $this->setLineDashingPattern(\Zend_Pdf_Page::LINE_DASHING_SOLID);
        if( $this->gameTwo !== null ) {
            $this->drawGame($this->gameTwo, $this->getHeight() / 2);
        }
    }

    public function drawGame( Game $game, $nOffSetY )
    {
        $this->setFont( $this->getParent()->getFont(), $this->getParent()->getFontHeight() );
        $nY = $nOffSetY - $this->getPageMargin();

        $nFirstBorder = $this->getWidth() / 6;
        $nSecondBorder = $nFirstBorder + ( ( $this->getWidth() - ( $nFirstBorder + $this->getPageMargin() ) ) / 2 );
        $nMargin = 15;
        $nRowHeight = 20;

        $arrLineColors = array( "b" => "black" );
        $nX = $this->drawCell( "FCToernooi", $this->getPageMargin(), $nY, $nFirstBorder - $this->getPageMargin(), $nRowHeight, ToernooiPdfPage::ALIGNRIGHT, $arrLineColors );
        $nX += $nMargin;
        $name = $this->getParent()->getTournament()->getCompetition()->getLeague()->getName();
        $this->drawCell( $name, $nX, $nY, $this->getWidth() - ($this->getPageMargin() + $nX), $nRowHeight, ToernooiPdfPage::ALIGNLEFT, $arrLineColors );
        $nY -= 2 * $nRowHeight;

        $img = \Zend_Pdf_Resource_ImageFactory::factory( __DIR__ . '/../logo.jpg');
        $imgWidthHeight = $nFirstBorder - $this->getPageMargin();
        $this->drawImage( $img, $nFirstBorder - $imgWidthHeight, $nY - $imgWidthHeight, $nFirstBorder, $nY );

        $roundNumber = $game->getRound()->getNumber();
        $planningService = $this->getParent()->getPlanningService();
        $nX = $nFirstBorder + $nMargin;
        $bNeedsRanking = $game->getPoule()->needsRanking();
        $nWidth = $this->getWidth() - ($this->getPageMargin() + $nX);
        $nWidthResult = $nWidth / 2;
        $nX2 = $nSecondBorder + ( $nMargin * 0.5 );

        $nameService = $this->getParent()->getStructureService()->getNameService();
        $roundsByNumber = $this->getParent()->getRoundsByRumber( $roundNumber );
        $roundsName = $nameService->getRoundsName( $roundNumber, $roundsByNumber );
        $this->drawCell( "ronde", $nX, $nY, $nWidthResult - ( $nMargin * 0.5 ), $nRowHeight, ToernooiPdfPage::ALIGNRIGHT );
        $this->drawCell( ':', $nSecondBorder, $nY, $nMargin, $nRowHeight );
        $this->drawCell( $roundsName, $nX2, $nY, $nWidthResult, $nRowHeight );
        $nY -= $nRowHeight;

        $sGame = $nameService->getPouleName($game->getPoule(), false);
        $sGmeDescription = $bNeedsRanking ? "poule" : "wedstrijd";
        $this->drawCell( $sGmeDescription, $nX, $nY, $nWidthResult - ( $nMargin * 0.5 ), $nRowHeight, ToernooiPdfPage::ALIGNRIGHT );
        $this->drawCell( ':', $nSecondBorder, $nY, $nMargin, $nRowHeight );
        $this->drawCell( $sGame, $nX2, $nY, $nWidthResult, $nRowHeight );
        $nY -= $nRowHeight;
        if( $bNeedsRanking ) {
            $this->drawCell( "speelronde", $nX, $nY, $nWidthResult - ( $nMargin * 0.5 ), $nRowHeight, ToernooiPdfPage::ALIGNRIGHT );
            $this->drawCell( ':', $nSecondBorder, $nY, $nMargin, $nRowHeight );
            $this->drawCell( $game->getRoundNumber(), $nX2, $nY, $nWidthResult, $nRowHeight );
            $nY -= $nRowHeight;
        }

        $competition = $game->getRound()->getCompetition();
        if( $planningService->canCalculateStartDateTime($competition, $roundNumber) === true ) {
            $localDateTime = $game->getStartDateTime()->setTimezone(new \DateTimeZone('Europe/Amsterdam'));
            $dateTime = $localDateTime->format("H:i") . "     " . $localDateTime->format("d M");
            $duration = $game->getRound()->getConfig()->getMinutesPerGame() . ' min.';
            if( $game->getRound()->getConfig()->getHasExtension() === true ) {
                $duration .= ' (' . $game->getRound()->getConfig()->getMinutesPerGameExt() . ' min.)';
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
            $this->drawCell( $game->getField()->getName(), $nX2, $nY, $nWidthResult, $nRowHeight );
            $nY -= $nRowHeight;
        }

        if( $game->getReferee() !== null ) {
            $this->drawCell( "scheidrechter", $nX, $nY, $nWidthResult - ( $nMargin * 0.5 ), $nRowHeight, ToernooiPdfPage::ALIGNRIGHT );
            $this->drawCell( ':', $nSecondBorder, $nY, $nMargin, $nRowHeight );
            $this->drawCell( $game->getReferee()->getInitials(), $nX2, $nY, $nWidthResult, $nRowHeight );
            $nY -= $nRowHeight;
        }

        $nY -= $nRowHeight; // extra lege regel

        $larger = 1.2;
        $nY -= $nRowHeight * $larger;
        $nWidth = $nFirstBorder - $this->getPageMargin();

        // 2x font thuis - uit
        $this->setFont( $this->getParent()->getFont(), $this->getParent()->getFontHeight() * $larger );

        $this->drawCell( 'wedstrijd', $this->getPageMargin(), $nY, $nWidth, $nRowHeight, ToernooiPdfPage::ALIGNRIGHT );
        $this->drawCell( $game->getHomePoulePlace()->getTeam()->getName(), $nX, $nY, $nWidthResult - ( $nMargin * 0.5 ), $nRowHeight * $larger, ToernooiPdfPage::ALIGNRIGHT );
        $this->drawCell( '-', $nSecondBorder, $nY, $nMargin, $nRowHeight * $larger );
        $this->drawCell( $game->getAwayPoulePlace()->getTeam()->getName(), $nX2, $nY, $nWidthResult, $nRowHeight * $larger );
        $nY -= 3 * $nRowHeight; // extra lege regel

        $this->setFont( $this->getParent()->getFont(), $this->getParent()->getFontHeight() * $larger );
        $nX = $nFirstBorder + $nMargin;

        $this->drawCell( 'uitslag', $this->getPageMargin(), $nY, $nWidth, $nRowHeight, ToernooiPdfPage::ALIGNRIGHT );
        $this->drawCell( '...............', $nX, $nY, $nSecondBorder - $nX, $nRowHeight * $larger, ToernooiPdfPage::ALIGNRIGHT);
        $this->drawCell( '-', $nSecondBorder, $nY, $nMargin, $nRowHeight * $larger );
        $this->drawCell( '...............', $nX2, $nY, $nWidthResult, $nRowHeight * $larger );
        $scoreConfig = $game->getRound()->getConfig()->getInputScore();
        if( $scoreConfig !== null ) {
            $this->drawCell( $scoreConfig->getName(), $nX2, $nY, $nWidthResult - $this->getPageMargin(), $nRowHeight * $larger, ToernooiPdfPage::ALIGNRIGHT );
        }

        $nY -= 3 * $nRowHeight; // extra lege regel

        if( $game->getRound()->getConfig()->getHasExtension() ) {
            $this->drawCell( 'na verleng.', $this->getPageMargin(), $nY, $nWidth, $nRowHeight * $larger, ToernooiPdfPage::ALIGNRIGHT );
            $this->drawCell( '...............', $nX, $nY, $nSecondBorder - $nX, $nRowHeight * $larger, ToernooiPdfPage::ALIGNRIGHT);
            $this->drawCell( '-', $nSecondBorder, $nY, $nMargin, $nRowHeight * $larger );
            $this->drawCell( '...............', $nX2, $nY, $nWidthResult, $nRowHeight * $larger );
            if( $scoreConfig !== null ) {
                $this->drawCell( $scoreConfig->getName(), $nX2, $nY, $nWidthResult - $this->getPageMargin(), $nRowHeight, ToernooiPdfPage::ALIGNRIGHT );
            }
        }

    }
}