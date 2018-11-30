<?php
/**
 * Created by PhpStorm.
 * User: coen
 * Date: 9-11-18
 * Time: 19:29
 */

namespace FCToernooi\Pdf\Page;

use \FCToernooi\Pdf\Page as ToernooiPdfPage;
use Voetbal\Round;
use Voetbal\Poule;
use Voetbal\PoulePlace;
use Voetbal\Qualify\Service as QualifyService;
use Voetbal\Structure\NameService;

class GamesPerField extends ToernooiPdfPage
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

    public function draw( Round $round, $nY )
    {
        $nY = $this->drawSubHeader( "deze pdf komt binnen een paar weken beschikbaar", $nY );
        return;

        $this->drawRound( $round, $nY );
    }



    protected function drawRoundStructureHelper( Round $round, $nY )
    {
        /*$nRowHeight = $this->getRowHeight();
        $fontHeight = $nRowHeight - 4;
        $this->setFont( $this->getParent()->getFont( true ), $fontHeight );
        $nameService = new NameService();
        $margin = 20;
        $arrLineColors = $round->getNumber() > 1 ? array( "t" => "black" ) : null;
        $roundName = $this->getRoundNameStructure( $round, $nameService);
        $this->drawCell( $roundName, $nX, $nY, $width, $nRowHeight, ToernooiPdfPage::ALIGNCENTER, $arrLineColors );
        $nY -= $nRowHeight;
        $nY -= $this->pouleMarginStructure;

        $nrOfChildren = $round->getChildRounds()->count();
        $widthChild = ( $width / $nrOfChildren ) - ( $nrOfChildren > 1 ? ( $margin / 2 ) : 0 );
        foreach( $round->getChildRounds() as $childRound ) {
            $this->drawRoundStructureHelper( $childRound, $nY, $nX, $widthChild );
            $nX += $widthChild + $margin;
        }*/
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




}