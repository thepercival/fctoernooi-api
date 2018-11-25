<?php
/**
 * Created by PhpStorm.
 * User: coen
 * Date: 9-11-18
 * Time: 19:28
 */

namespace FCToernooi\Pdf\Page;

use \FCToernooi\Pdf\Page as ToernooiPdfPage;
use Voetbal\Round;
use Voetbal\Structure\NameService;
use Voetbal\Poule;
use Voetbal\PoulePlace;
use Voetbal\Qualify\Service as QualifyService;

class Planning extends ToernooiPdfPage
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
/*
        <colgroup>
        <col *ngIf="aRoundNeedsRanking(roundsByNumber)">
        <col *ngIf="!aRoundNeedsRanking(roundsByNumber)">
        <col *ngIf="planningService.canCalculateStartDateTime(roundNumber)">
        <col *ngIf="hasFields()">
        <col class="width-25">
        <col>
        <col class="width-25">
        <col *ngIf="hasReferees()" class="d-none d-sm-table-cell">
      </colgroup>*/
    }

    public function getPageMargin(){ return 20; }
    public function getHeaderHeight(){ return 0; }

    protected function showPouleName(){
        // <span>{{nameService.getPouleName(game.getPoule(), false)}}</span>
    }

    protected function getRowHeight() {
        if( $this->rowHeight === null ) {
            $this->rowHeight = 18;
        }
        return $this->rowHeight;
    }

    public function draw( Round $round, $nY )
    {
        $structureService = $this->getParent()->getStructureService();
        $allRoundsByNumber = $structureService->getAllRoundsByNumber( $this->getParent()->getTournament()->getCompetition() );
        $roundsByNumber = $allRoundsByNumber[$round->getNumber()];
        $roundsName = $structureService->getNameService()->getRoundsName( $round->getNumber(), $roundsByNumber );
        $nY = $this->drawSubHeader( $roundsName, $nY );
        return;

        // $this->drawRound( $round, $nY );
    }



    protected function drawRoundStructureHelper( Round $round, $nY )
    {
        /*$nRowHeight = $this->getRowHeight();
        $fontHeight = $nRowHeight - 4;
        $this->setFont( $this->getParent()->getFont( true ), $fontHeight );
        $nameService = $this->getParent()->getStructureService()->getNameService();
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