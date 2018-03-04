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

        $nFirstBorder = $this->getWidth() / 4;
        $nSecondBorder = $nFirstBorder + ( ( $this->getWidth() - $nFirstBorder ) / 2 );
        $nMargin = 15;
        $nRowHeight = 14;

        $arrLineColors = array( "b" => "black" );
        $nX = $this->drawCell( "FCToernooi", $this->getPageMargin(), $nY, $nFirstBorder - $this->getPageMargin(), $nRowHeight, ToernooiPdfPage::ALIGNRIGHT, $arrLineColors );
        $nX += $nMargin;
        $name = $this->getParent()->getTournament()->getCompetition()->getLeague()->getName();
        $this->drawCell( $name, $nX, $nY, $this->getWidth() - ($this->getPageMargin() + $nX), $nRowHeight, ToernooiPdfPage::ALIGNLEFT, $arrLineColors );
        $nY -= 2 * $nRowHeight;

        $structService = $this->getParent()->getStructureService();
        $roundsByNumber = $structService->getRoundsByNumber( $game->getRound() );
        $roundsName = $structService->getRoundsName( $game->getRound()->getNumber(), $roundsByNumber );
        $nX = $nFirstBorder + $nMargin;
        $nWidth = $this->getWidth() - ($this->getPageMargin() + $nX);
        $this->drawCell( $roundsName, $nX, $nY, $nWidth, $nRowHeight );

        $nY -= $nRowHeight;

        if( $structService->canCalculateStartDateTime($game->getRound()) === true ) {
            $dateTime = $game->getStartDateTime()->format("d M H:i");
            $duration = $game->getRound()->getConfig()->getMinutesPerGame() . ' min.';
            if( $game->getRound()->getConfig()->getHasExtension() === true ) {
                $duration .= ' (' . $game->getRound()->getConfig()->getMinutesPerGameExt() . ' min.)';
            }
            $this->drawCell( $dateTime . ' -> ' . $duration, $nX, $nY, $nWidth, $nRowHeight );
            $nY -= $nRowHeight;
        }

        $sGame = $structService->getPouleName($game->getPoule(), true) . ' -> speelronde ' . $game->getRoundNumber();
        $this->drawCell( $sGame, $nX, $nY, $nWidth, $nRowHeight );
        $nY -= 2 * $nRowHeight; // extra lege regel

        $nY -= $nRowHeight * 2;

        // 2x font thuis - uit
        $this->setFont( $this->getParent()->getFont(), $this->getParent()->getFontHeight() * 2 );
        $nX2 = $nSecondBorder + $nMargin;
        $nWidthResult = $nWidth / 2;
        $this->drawCell( $game->getHomePoulePlace()->getTeam()->getName(), $nX, $nY, $nWidthResult, $nRowHeight * 2, ToernooiPdfPage::ALIGNRIGHT );
        $this->drawCell( '-', $nSecondBorder, $nY, $nMargin, $nRowHeight );
        $this->drawCell( $game->getAwayPoulePlace()->getTeam()->getName(), $nX2, $nY, $nWidthResult, $nRowHeight * 2 );
        $nY -= 6 * $nRowHeight; // extra lege regel

        $this->setFont( $this->getParent()->getFont(), $this->getParent()->getFontHeight() * 1.5 );
        $nX = $nFirstBorder + $nMargin;
        $nWidth = $nFirstBorder - $this->getPageMargin();
        $this->drawCell( 'uitslag', $this->getPageMargin(), $nY, $nWidth, $nRowHeight, ToernooiPdfPage::ALIGNRIGHT );
        $this->drawCell( '...............', $nX, $nY, $nSecondBorder - $nX, $nRowHeight , ToernooiPdfPage::ALIGNRIGHT);
        $this->drawCell( '-', $nSecondBorder, $nY, $nMargin, $nRowHeight );
        $this->drawCell( '...............', $nX2, $nY, $nWidthResult, $nRowHeight );
        $scoreConfig = $game->getRound()->getInputScoreConfig();
        if( $scoreConfig !== null ) {
            $this->drawCell( $scoreConfig->getName(), $nX2, $nY, $nWidthResult - $this->getPageMargin(), $nRowHeight, ToernooiPdfPage::ALIGNRIGHT );
        }

        $nY -= 6 * $nRowHeight; // extra lege regel

        if( $game->getRound()->getConfig()->getHasExtension() ) {
            $this->drawCell( 'na verlenging', $this->getPageMargin(), $nY, $nWidth, $nRowHeight, ToernooiPdfPage::ALIGNRIGHT );
            $this->drawCell( '...............', $nX, $nY, $nSecondBorder - $nX, $nRowHeight , ToernooiPdfPage::ALIGNRIGHT);
            $this->drawCell( '-', $nSecondBorder, $nY, $nMargin, $nRowHeight );
            $this->drawCell( '...............', $nX2, $nY, $nWidthResult, $nRowHeight );
            if( $scoreConfig !== null ) {
                $this->drawCell( $scoreConfig->getName(), $nX2, $nY, $nWidthResult - $this->getPageMargin(), $nRowHeight, ToernooiPdfPage::ALIGNRIGHT );
            }
        }

    }
}