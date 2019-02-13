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
        $this->versusColumnsWidth = $this->getDisplayWidth() * 0.60;
        $this->pointsColumnWidth = $this->getDisplayWidth() * 0.08;
        $this->rankColumnWidth = $this->getDisplayWidth() * 0.07;

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
     * t/m 3 places 0g, t/m 8 places 45g, hoger 90g
     *
     * @param Poule $poule
     */
    public function getPouleHeight( Poule $poule )
    {
        $nrOfPlaces = $poule->getPlaces()->count();

        // header row
        $versusColumnWidth = $this->versusColumnsWidth / $nrOfPlaces;
        $degrees = $this->getDegrees( $nrOfPlaces );
        $height = $this->getVersusHeight( $versusColumnWidth, $degrees );

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
        $nrOfPlaces = $poule->getPlaces()->count();
        $versusColumnWidth = $this->versusColumnsWidth / $nrOfPlaces;
        $degrees = $this->getDegrees( $nrOfPlaces );
        $height = $this->getVersusHeight( $versusColumnWidth, $degrees );

        $nX = $this->getPageMargin();
        $nX = $this->drawCell( (new NameService())->getPouleName( $poule, true ), $nX, $nY, $this->nameColumnWidth, $height, Page::ALIGNCENTER, 'black' );

        $nVersus = 0;
        foreach( $poule->getPlaces() as $poulePlace ) {
            $nX = $this->drawCell((new NameService())->getPoulePlaceFromName($poulePlace, true), $nX, $nY, $versusColumnWidth,
                $height, Page::ALIGNCENTER, 'black', $degrees);
            $nVersus++;
        }
        // draw pointsrectangle
        $nX = $this->drawCell( "punten", $nX, $nY, $this->pointsColumnWidth, $height, Page::ALIGNCENTER, 'black' );

        // draw rankrectangle
        $this->drawCell( "plek", $nX, $nY, $this->rankColumnWidth, $height, Page::ALIGNCENTER, 'black' );

        return $nY - $height;
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
            $nX = $this->drawCell( (new NameService())->getPoulePlaceFromName( $poulePlace, true ), $nX, $nY, $this->nameColumnWidth, $nRowHeight, Page::ALIGNLEFT, 'black' );

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

            $nY -= $nRowHeight;
        }
        return $nY - $nRowHeight;
    }

    protected function getPoulePlaceNamesWidth( Poule $poule ) {
        $width = 0;
        foreach( $poule->getPlaces() as $poulePlace ) {
            $width += $this->getPoulePlaceWidth( $poulePlace );
        }
        return $width;
    }


    public function getPoulePlaceWidth( PoulePlace $poulePlace, int $nFontSize = null )
    {
        $key = $poulePlace->getId();
        if( $this->getParent()->hasTextWidth($key) ) {
            return $this->getParent()->getTextWidth();
        }
        $width = $this->getTextWidth( (new NameService())->getPoulePlaceFromName($poulePlace, true));
        return $this->getParent()->setTextWidth($width);
    }

    public function getDegrees( int $nrOfPlaces ): int {
        if( $nrOfPlaces <= 3 ) {
            return 0;
        }
        if( $nrOfPlaces >= 6 ) {
            return 90;
        }
        return 45;
    }

    public function getVersusHeight( $versusColumnWidth, int $degrees ): int {
        if( $degrees === 0 ) {
            return $this->getRowHeight();
        }
        if( $degrees === 90 ) {
            return $versusColumnWidth * 2;
        }
        return (tan(deg2rad($degrees)) * $versusColumnWidth );
    }
}