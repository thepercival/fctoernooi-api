<?php
/**
 * Created by PhpStorm.
 * User: coen
 * Date: 2-2-18
 * Time: 15:03
 */

namespace FCToernooi\Pdf\Page;

use \FCToernooi\Pdf\Page as ToernooiPdfPage;
use Voetbal\Structure\NameService;
use Voetbal\Poule;
use Voetbal\Round\Number as RoundNumber;
use FCToernooi\Pdf\Page;

class PoulePivotTables extends ToernooiPdfPage
{
    protected $nameColumnWidth;
    protected $pointsColumnWidth;
    protected $rankColumnWidth;
    protected $versusColumnsWidth;
    /*protected $maxPoulesPerLine;
    protected $poulePlaceWidthStructure;
    protected $pouleMarginStructure;
    */protected $rowHeight;

    public function __construct( $param1 )
    {
        parent::__construct( $param1 );
        $this->setLineWidth( 0.5 );
        $this->nameColumnWidth = $this->getDisplayWidth() * 0.25;
        $this->pointsColumnWidth = $this->getDisplayWidth() * 0.1;
        $this->rankColumnWidth = $this->getDisplayWidth() * 0.1;
        $this->versusColumnsWidth = $this->getDisplayWidth() * 0.55;

        /*$this->maxPoulesPerLine = 3;
        $this->poulePlaceWidthStructure = 30;
        $this->pouleMarginStructure = 10;*/
    }

    public function getPageMargin(){ return 20; }
    public function getHeaderHeight(){ return 0; }

    protected function getRowHeight() {
        if( $this->rowHeight === null ) {
            $this->rowHeight = 18;
        }
        return $this->rowHeight;
    }

    public function drawRoundNumberHeader( RoundNumber $roundNumber, $nY )
    {
        $fontHeightSubHeader = $this->getParent()->getFontHeightSubHeader();
        $this->setFont( $this->getParent()->getFont( true ), $this->getParent()->getFontHeightSubHeader() );
        $nX = $this->getPageMargin();
        $displayWidth = $this->getDisplayWidth();
        $subHeader = (new NameService())->getRoundNumberName( $roundNumber);
        $this->drawCell( $subHeader, $nX, $nY, $displayWidth, $fontHeightSubHeader, Page::ALIGNCENTER );
        $this->setFont( $this->getParent()->getFont(), $this->getParent()->getFontHeight() );
        return $nY - ( 2 * $fontHeightSubHeader );
    }

    /**
     * t/m 3 teams 0g, t/m 8 teams 45g, hoger 90g
     *
     * @param Poule $poule
     */
    public function getPouleHeight( Poule $poule )
    {
        $nrOfPlaces = $poule->getPlaces()->count();

        $height = 0;
        // header row

        // places
        $height += $this->getRowHeight() * $nrOfPlaces;

        return $height;
    }



    /*public function draw()
    {
        $tournament = $this->getParent()->getTournament();
        $firstRound = $this->getParent()->getStructure()->getRootRound();
        $this->setQual( $firstRound );
        $nY = $this->drawHeader( "draaitabel per poule" );
        $nY = $this->draw( $firstRound, $nY );
    }*/

    /*protected function setQual( Round $parentRound )
    {
        foreach ($parentRound->getChildRounds() as $childRound) {
            $qualifyService = new QualifyService($childRound);
            $qualifyService->setQualifyRules();
            $this->setQual( $childRound );
        }
    }*/

    protected function getPouleName( Poule $poule )
    {
        $nameService = new NameService();
        return $nameService->getPouleName( $poule, true );
    }

    public function drawPouleHeader( Poule $poule, $nY )
    {
        $nRowHeight = $this->getRowHeight();

        $nX = $this->getPageMargin();
        $nX = $this->drawCell( (new NameService())->getPouleName( $poule, true ), $nX, $nY, $this->nameColumnWidth, $nRowHeight, Page::ALIGNLEFT, 'black' );

        // CDK  TODO
        // kijken als namen passen in $this->versusColumnsWidth
        // zo niet kijk dan wat de minimale aantal graden zijn, zodat het wel past
        // hierbij is 90 graden het max.
        // wanneer 90 graden en tekst te lang voor maximale hoogte, dan kijken als de tekst over meerdere regels kan worden uitgesplitst
        // zo nee, dan afkappen
        $nrOfPlaces = $poule->getPlaces()->count();
        $versusColumnWidth = $this->versusColumnsWidth / $nrOfPlaces;
        $nVersus = 0;
        foreach( $poule->getPlaces() as $poulePlace ) {
            $nX += $versusColumnWidth;
            /*$nX = $this->getPageMargin();
            $nX = $this->drawCell((new NameService())->getPoulePlaceName($poulePlace), $nX, $nY, $this->nameColumnWidth,
                $nRowHeight, Page::ALIGNLEFT, 'black');

            // draw versus
            for ($nI = 0; $nI < $nrOfPlaces; $nI++) {
                if ($nVersus === $nI) {
                    $this->setFillColor(new \Zend_Pdf_Color_Html("lightgrey"));
                }
                $nX = $this->drawCell(null, $nX, $nY, $versusColumnWidth, $nRowHeight, Page::ALIGNLEFT, 'black');
                if ($nVersus === $nI) {
                    $this->setFillColor(new \Zend_Pdf_Color_Html("white"));
                }
            }*/
            $nVersus++;
        }
        // draw pointsrectangle
        $nX = $this->drawCell( "punten", $nX, $nY, $this->pointsColumnWidth, $nRowHeight, Page::ALIGNRIGHT, 'black' );

        // draw rankrectangle
        $this->drawCell( "plek", $nX, $nY, $this->rankColumnWidth, $nRowHeight, Page::ALIGNRIGHT, 'black' );

        return $nY - $nRowHeight;
    }

    public function draw( Poule $poule, $nY )
    {
        // draw first row
        $nY = $this->drawPouleHeader( $poule, $nY );

        $nRowHeight = $this->getRowHeight();
        $nrOfPlaces = $poule->getPlaces()->count();
        $versusColumnWidth = $this->versusColumnsWidth / $nrOfPlaces;

        $nVersus = 0;
        foreach( $poule->getPlaces() as $poulePlace ) {
            $nX = $this->getPageMargin();
            $nX = $this->drawCell( (new NameService())->getPoulePlaceName( $poulePlace ), $nX, $nY, $this->nameColumnWidth, $nRowHeight, Page::ALIGNLEFT, 'black' );

            // draw versus
            for( $nI = 0 ; $nI < $nrOfPlaces ; $nI++ ) {
                if( $nVersus === $nI ) {
                    $this->setFillColor( new \Zend_Pdf_Color_Html( "lightgrey" ) );
                }
                $nX = $this->drawCell( null, $nX, $nY, $versusColumnWidth, $nRowHeight, Page::ALIGNLEFT, 'black' );
                if( $nVersus === $nI ) {
                    $this->setFillColor( new \Zend_Pdf_Color_Html( "white" ) );
                }
            }
            $nVersus++;

            // draw pointsrectangle
            $nX = $this->drawCell( null, $nX, $nY, $this->pointsColumnWidth, $nRowHeight, Page::ALIGNLEFT, 'black' );

            // draw rankrectangle
            $this->drawCell( null, $nX, $nY, $this->rankColumnWidth, $nRowHeight, Page::ALIGNLEFT, 'black' );

            /*
            $fontHeight = $nRowHeight - 4;
            $pouleMargin = 20;
            $poules = $round->getPoules()->toArray();
            $nrOfPoules = $round->getPoules()->count();
            $percNumberWidth = 0.1;
            $nameService = new NameService();
            $nYPouleStart = $nY;
            $maxNrOfPlacesPerPoule = null;
            $nrOfLines = $this->getNrOfLines( $nrOfPoules );
            for( $line = 1 ; $line <= $nrOfLines ; $line++ ) {
                $nrOfPoulesForLine = $this->getNrOfPoulesForLine( $nrOfPoules, $line === $nrOfLines );
                $pouleWidth = $this->getPouleWidth( $nrOfPoulesForLine, $pouleMargin );
                $nX = $this->getXLineCentered( $nrOfPoulesForLine, $pouleWidth, $pouleMargin );
                while( $nrOfPoulesForLine > 0 ) {
                    $poule = array_shift( $poules );

                    if( $maxNrOfPlacesPerPoule === null ) {
                        $maxNrOfPlacesPerPoule = $poule->getPlaces()->count();
                    }
                    $numberWidth = $pouleWidth * $percNumberWidth;
                    $this->setFont( $this->getParent()->getFont( true ), $fontHeight );
                    $this->drawCell( $this->getPouleName( $poule ), $nX, $nYPouleStart, $pouleWidth, $nRowHeight, ToernooiPdfPage::ALIGNCENTER, "black" );
                    $this->setFont( $this->getParent()->getFont(), $fontHeight );
                    $nY = $nYPouleStart - $nRowHeight;
                    foreach( $poule->getPlaces() as $poulePlace ) {
                        $this->drawCell( $poulePlace->getNumber(), $nX, $nY, $numberWidth, $nRowHeight, ToernooiPdfPage::ALIGNRIGHT, "black" );
                        $name = $poulePlace->getTeam() !== null ? $nameService->getPoulePlaceNameSimple( $poulePlace, true ) : null;
                        $this->drawCell( $name, $nX + $numberWidth, $nY, $pouleWidth - $numberWidth, $nRowHeight, ToernooiPdfPage::ALIGNLEFT, "black" );
                        $nY -= $nRowHeight;
                    }
                    $nX += $pouleMargin + $pouleWidth;
                    $nrOfPoulesForLine--;
                }
                $nYPouleStart -= ( $maxNrOfPlacesPerPoule + 2 ) * $nRowHeight;
            }*/
            $nY -= $nRowHeight;
        }

        return $nY - $nRowHeight;
    }
/*
    protected function getNrOfLines( $nrOfPoules )
    {
        if( ( $nrOfPoules % 3 ) !== 0 ) {
            $nrOfPoules += ( 3 - ( $nrOfPoules % 3 ) );
        }
        return ( $nrOfPoules / 3 );
    }

    protected function getNrOfPoulesForLine( $nrOfPoules, $lastLine )
    {
        if( $nrOfPoules === 4 ) {
            return 2;
        }
        if( $nrOfPoules <= 3 ) {
            return $nrOfPoules;
        }
        if( !$lastLine ) {
            return 3;
        }
        return ( $nrOfPoules % 3 );
    }

    protected function getPouleWidth( $nrOfPoules, $margin )
    {
        if( $nrOfPoules === 1 ) {
            $nrOfPoules++;
        }
        return ( $this->getDisplayWidth() - ( ( $nrOfPoules - 1 ) * $margin ) ) / $nrOfPoules;
    }


    protected function getXLineCentered( $nrOfPoules, $pouleWidth, $margin )
    {
        if( $nrOfPoules > $this->maxPoulesPerLine  ) {
            $nrOfPoules = $this->maxPoulesPerLine;
        }
        $width = ( $nrOfPoules * $pouleWidth ) + ( ( $nrOfPoules - 1 ) * $margin );
        return $this->getPageMargin() + ( $this->getDisplayWidth() - $width ) / 2;
    }*/
}