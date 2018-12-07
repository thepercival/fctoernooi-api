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
use Voetbal\Field;
use Voetbal\Round\Number as RoundNumber;
use Voetbal\Structure\NameService;
use FCToernooi\Pdf\Page;

class Planning extends ToernooiPdfPage
{
    use GamesTrait;

    /**
     * @var Field
     */
    protected $fieldFilter;

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

    public function getFieldFilter(){ return $this->fieldFilter; }
    public function setFieldFilter( Field $field )
    {
        $this->fieldFilter = $field;
    }

    public function getGames( RoundNumber $roundNumber ): array {
        $games = [];
        foreach( $roundNumber->getRounds() as $round ) {
            foreach( $round->getPoules() as $poule ) {
                foreach( $poule->getGames() as $game ) {
                    if( $this->fieldFilter === null || $this->fieldFilter === $game->getField() ) {
                        $games[] = $game;
                    }
                }
            }
        }
        return $games;
    }

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
     * add winnerslosers if roundnumber is 2 and has sibling
     *
     * @param Round $round
     * @param NameService $nameService
     * @return string
     */
//    protected function getRoundNameStructure( Round $round, NameService $nameService ): string
//    {
//        $roundName = $nameService->getRoundName( $round );
//        if( $round->getNumber() === 2 and $round->getOpposingRound() !== null ) {
//            $roundName .= ' - ' . $nameService->getWinnersLosersDescription($round->getWinnersOrlosers()) . 's';
//        }
//        return $roundName;
//    }




}