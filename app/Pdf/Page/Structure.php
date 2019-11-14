<?php
/**
 * Created by PhpStorm.
 * User: coen
 * Date: 2-2-18
 * Time: 15:03
 */

namespace App\Pdf\Page;

use App\Pdf\Page as ToernooiPdfPage;
use Voetbal\Round;
use Voetbal\Poule;
use Voetbal\NameService;

class Structure extends ToernooiPdfPage
{
    protected $maxPoulesPerLine;
    protected $placeWidthStructure;
    protected $pouleMarginStructure;
    protected $rowHeight;

    public function __construct( $param1 )
    {
        parent::__construct( $param1 );
        $this->setLineWidth( 0.5 );
        $this->maxPoulesPerLine = 3;
        $this->placeWidthStructure = 30;
        $this->pouleMarginStructure = 10;
    }

    public function getPageMargin(){ return 20; }
    public function getHeaderHeight(){ return 0; }

    protected function getRowHeight() {
        if( $this->rowHeight === null ) {
            $this->rowHeight = 18;
        }
        return $this->rowHeight;
    }

    public function draw()
    {
        $rooRound = $this->getParent()->getStructure()->getRootRound();
        $nY = $this->drawHeader( "indeling & structuur" );
        $nY = $this->drawGrouping( $rooRound, $nY );

        $this->drawRoundStructure( $rooRound, $nY );
    }

    protected function getPouleName( Poule $poule )
    {
        $nameService = new NameService();
        return $nameService->getPouleName( $poule, true );
    }

    /*********************** GROUPING **************************/

    public function drawGrouping( Round $round, $nY )
    {
        $nY = $this->drawSubHeader( "Indeling", $nY );
        $nRowHeight = $this->getRowHeight();
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
                foreach( $poule->getPlaces() as $place ) {
                    $this->drawCell( $place->getNumber(), $nX, $nY, $numberWidth, $nRowHeight, ToernooiPdfPage::ALIGNRIGHT, "black" );
                    $name = $place->getCompetitor() !== null ? $nameService->getPlaceName( $place, true ) : null;
                    $this->drawCell( $name, $nX + $numberWidth, $nY, $pouleWidth - $numberWidth, $nRowHeight, ToernooiPdfPage::ALIGNLEFT, "black" );
                    $nY -= $nRowHeight;
                }
                $nX += $pouleMargin + $pouleWidth;
                $nrOfPoulesForLine--;
            }
            $nYPouleStart -= ( $maxNrOfPlacesPerPoule + 2 /* header + empty line */ ) * $nRowHeight;
        }
        return $nY - ( 2 * $nRowHeight );
    }

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
        if( ( $nrOfPoules % 3 ) === 0 ) {
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

    /**
     * maximaal 4 poules in de breedte
     */
    protected function getXLineCentered( $nrOfPoules, $pouleWidth, $margin )
    {
        if( $nrOfPoules > $this->maxPoulesPerLine  ) {
            $nrOfPoules = $this->maxPoulesPerLine;
        }
        $width = ( $nrOfPoules * $pouleWidth ) + ( ( $nrOfPoules - 1 ) * $margin );
        return $this->getPageMargin() + ( $this->getDisplayWidth() - $width ) / 2;
    }

    /********************** END GROUPING *********************/

    /********************** BEGIN STRUCTURE *********************/

    protected function drawRoundStructure( Round $round, $nY )
    {
        $nY = $this->drawSubHeader( "Structuur", $nY );
        $this->drawRoundStructureHelper( $round, $nY, $this->getPageMargin(), $this->getDisplayWidth() );
    }

    protected function drawRoundStructureHelper( Round $round, $nY, $nX, $width )
    {
        $nRowHeight = $this->getRowHeight();
        $fontHeight = $nRowHeight - 4;
        $this->setFont( $this->getParent()->getFont( true ), $fontHeight );
        $nameService = new NameService();
        $margin = 20;
        $arrLineColors = !$round->isRoot() ? array( "t" => "black" ) : null;
        $roundName = $nameService->getRoundName($round);
        $this->drawCell( $roundName, $nX, $nY, $width, $nRowHeight, ToernooiPdfPage::ALIGNCENTER, $arrLineColors );
        $nY -= $nRowHeight;
        $nY -= $this->pouleMarginStructure;

        if( $round->getPoules()->count() === 1 && $round->getPoules()->first()->getPlaces()->count() < 3 ) {
            return;
        }
        $this->setFont( $this->getParent()->getFont(), $fontHeight );
        $poules = $round->getPoules()->toArray();
        while( count( $poules ) > 0 ) {
            $poulesForLine = $this->getPoulesForLineStructure( $poules, $width );
            $nXPoules = $this->getXLineCenteredStructure( $poulesForLine, $nX, $width );
            $maxNrOfPlaceRows = 0;
            while( count( $poulesForLine ) > 0 ) {
                $poule = array_shift($poulesForLine);
                $pouleWidth = $this->getPouleWidthStructure( $poule );
                $nXPlaces = $nXPoules;
                $nXPoules = $this->drawCell($nameService->getPouleName($poule, false), $nXPoules, $nY, $pouleWidth, $nRowHeight,
                    ToernooiPdfPage::ALIGNCENTER, "black");
                $nYPlaces = $nY - $nRowHeight;
                $places = $poule->getPlaces()->toArray();
                uasort( $places, function($placeA, $placeB) {
                    return ($placeA->getNumber() > $placeB->getNumber()) ? 1 : -1;
                });
                $nrOfPlaceRows = count($places) === 3 ? 1 : 2; // bij 3 places, naast elkaar
                foreach( $places as $place ) {
                    $this->drawCell( $nameService->getPlaceFromName( $place, false ), $nXPlaces, $nYPlaces, $this->placeWidthStructure, $nRowHeight, ToernooiPdfPage::ALIGNCENTER, "black" );
                    if( $nrOfPlaceRows === 1 ) {
                        $nXPlaces += $this->placeWidthStructure;
                    } else if( ( $place->getNumber() % 2 ) === 0 ) {
                        $nYPlaces += $nRowHeight;
                        $nXPlaces += $this->placeWidthStructure;
                    } else {
                        $nYPlaces -= $nRowHeight;
                    }
                }
                if( $nrOfPlaceRows > $maxNrOfPlaceRows) {
                    $maxNrOfPlaceRows = $nrOfPlaceRows;
                }
                $nXPoules += $this->pouleMarginStructure;
            }
            $nY -= ( ( 1 + $maxNrOfPlaceRows )  * $nRowHeight ) + $this->pouleMarginStructure;
        }

        $nrOfChildren = count($round->getChildren());
        if( $nrOfChildren === 0 ) {
            return;
        }
        $widthChild = ( $width / $nrOfChildren ) - ( $nrOfChildren > 1 ? ( $margin / 2 ) : 0 );
        foreach( $round->getChildren() as $childRound ) {
            $this->drawRoundStructureHelper( $childRound, $nY, $nX, $widthChild );
            $nX += $widthChild + $margin;
        }
    }

    protected function getPoulesForLineStructure( &$poules, $width )
    {
        $poulesForLine = [];
        $widthPoules = 0;
        while ( $poule = array_shift($poules) ) {
            if( $widthPoules > 0 ) {
                $widthPoules += $this->pouleMarginStructure;
            }
            $widthPoules += $this->getPouleWidthStructure( $poule );
            if( $widthPoules > $width ) {
                array_unshift($poules, $poule);
                break;
            }
            $poulesForLine[] = $poule;
        }
        return $poulesForLine;
    }

    protected function getXLineCenteredStructure( $poulesForLine, $nX, $width )
    {
        $widthPoules = 0;
        foreach( $poulesForLine as $poule ) {
            $widthPoules += $this->getPouleWidthStructure($poule);
        }
        $widthPoules += ( count( $poulesForLine ) - 1 ) * $this->pouleMarginStructure;
        return $nX + ( ( $width - $widthPoules ) / 2 );
    }

    protected function getPouleWidthStructure( $poule )
    {
        $nrOfPlaces = $poule->getPlaces()->count();
        if( $nrOfPlaces === 3 ) {
            $nrOfPlaceColumns = $nrOfPlaces;
        } else {
            $nrOfPlaceColumns = (($nrOfPlaces % 2) === 0 ? $nrOfPlaces : $nrOfPlaces + 1) / 2;
        }
        return $this->placeWidthStructure * $nrOfPlaceColumns;
    }
}