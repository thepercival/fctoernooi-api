<?php
/**
 * Created by PhpStorm.
 * User: coen
 * Date: 2-2-18
 * Time: 15:03
 */

namespace FCToernooi\Pdf\Page;

use \FCToernooi\Pdf\Page as ToernooiPdfPage;
use Voetbal\Round;
use Voetbal\Poule;
use Voetbal\PoulePlace;
use Voetbal\Qualify\Service as QualifyService;
use Voetbal\Structure\NameService;

class Structure extends ToernooiPdfPage
{
    protected $maxPoulesPerLine;
    protected $poulePlaceWidthStructure;
    protected $pouleMarginStructure;
    protected $rowHeight;

    public function __construct( $param1 )
    {
        parent::__construct( $param1 );
        $this->setLineWidth( 0.5 );
        $this->maxPoulesPerLine = 3;
        $this->poulePlaceWidthStructure = 30;
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
        $tournament = $this->getParent()->getTournament();
        $firstRound = $this->getParent()->getStructureService()->getFirstRound( $tournament->getCompetition() );
        $this->setQual( $firstRound );
        $nY = $this->drawHeader( "indeling & structuur" );
        $nY = $this->drawGrouping( $firstRound, $nY );

        $this->drawRoundStructure( $firstRound, $nY );
    }

    protected function setQual( Round $parentRound )
    {
        foreach ($parentRound->getChildRounds() as $childRound) {
            $qualifyService = new QualifyService($childRound);
            $qualifyService->setQualifyRules();
            $this->setQual( $childRound );
        }
    }

    protected function getPouleName( Poule $poule )
    {
        $nameService = $this->getParent()->getStructureService()->getNameService();
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
        $nameService = $this->getParent()->getStructureService()->getNameService();
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
        $nameService = $this->getParent()->getStructureService()->getNameService();
        $margin = 20;
        $arrLineColors = $round->getNumber() > 1 ? array( "t" => "black" ) : null;
        $roundName = $this->getRoundNameStructure( $round, $nameService);
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
            $maxNrOfPoulePlaceRows = 0;
            while( count( $poulesForLine ) > 0 ) {
                $poule = array_shift($poulesForLine);
                $pouleWidth = $this->getPouleWidthStructure( $poule );
                $nXPlaces = $nXPoules;
                $nXPoules = $this->drawCell($nameService->getPouleName($poule, false), $nXPoules, $nY, $pouleWidth, $nRowHeight,
                    ToernooiPdfPage::ALIGNCENTER, "black");
                $nYPlaces = $nY - $nRowHeight;
                $poulePlaces = $poule->getPlaces()->toArray();
                uasort( $poulePlaces, function($poulePlaceA, $poulePlaceB) {
                    return ($poulePlaceA->getNumber() > $poulePlaceB->getNumber()) ? 1 : -1;
                });
                $nrOfPoulePlaceRows = count($poulePlaces) === 3 ? 1 : 2; // bij 3 places, naast elkaar
                foreach( $poulePlaces as $poulePlace ) {
                    $this->drawCell( $nameService->getPoulePlaceName( $poulePlace, false ), $nXPlaces, $nYPlaces, $this->poulePlaceWidthStructure, $nRowHeight, ToernooiPdfPage::ALIGNCENTER, "black" );
                    if( $nrOfPoulePlaceRows === 1 ) {
                        $nXPlaces += $this->poulePlaceWidthStructure;
                    } else if( ( $poulePlace->getNumber() % 2 ) === 0 ) {
                        $nYPlaces += $nRowHeight;
                        $nXPlaces += $this->poulePlaceWidthStructure;
                    } else {
                        $nYPlaces -= $nRowHeight;
                    }
                }
                if( $nrOfPoulePlaceRows > $maxNrOfPoulePlaceRows) {
                    $maxNrOfPoulePlaceRows = $nrOfPoulePlaceRows;
                }
                $nXPoules += $this->pouleMarginStructure;
            }
            $nY -= ( ( 1 + $maxNrOfPoulePlaceRows )  * $nRowHeight ) + $this->pouleMarginStructure;
        }

        $nrOfChildren = $round->getChildRounds()->count();
        if( $nrOfChildren > 0 ) {
            $widthChild = ( $width / $nrOfChildren ) - ( $nrOfChildren > 1 ? ( $margin / 2 ) : 0 );
            foreach( $round->getChildRounds() as $childRound ) {
                $this->drawRoundStructureHelper( $childRound, $nY, $nX, $widthChild );
                $nX += $widthChild + $margin;
            }
        }
    }

    /**
     * add winnerslosers if roundnumber is 2 and has sibling
     *
     * @param Round $round
     * @param NameService $nameService
     * @return string
     */
    protected function getRoundNameStructure( Round $round, NameService $nameService ): string
    {
        $roundName = $nameService->getRoundName( $round );
        if( $round->getNumber() === 2 and $round->getOpposingRound() !== null ) {
            $roundName .= ' - ' . $nameService->getWinnersLosersDescription($round->getWinnersOrlosers()) . 's';
        }
        return $roundName;
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
        return $this->poulePlaceWidthStructure * $nrOfPlaceColumns;
    }


}