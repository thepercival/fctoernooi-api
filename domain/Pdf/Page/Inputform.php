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

class Inputform extends ToernooiPdfPage
{
    /*protected $maxPoulesPerLine;
    protected $poulePlaceWidthStructure;
    protected $pouleMarginStructure;
    */protected $rowHeight;

    public function __construct( $param1 )
    {
        parent::__construct( $param1 );
        $this->setLineWidth( 0.5 );
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

    /**
     * t/m 3 teams 0g, t/m 8 teams 45g, hoger 90g
     *
     * @param Poule $poule
     */
    public function getPouleHeight( Poule $poule )
    {
        return 6;
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

    public function draw( Poule $poule, $nY )
    {

        foreach( $poule->getPlaces() as $poulePlace ) {

            /*$nRowHeight = $this->getRowHeight();
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
        }

        return $nY; // - ( 2 * $nRowHeight );
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