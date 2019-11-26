<?php
/**
 * Created by PhpStorm.
 * User: coen
 * Date: 9-11-18
 * Time: 19:28
 */

namespace App\Export\Excel\Worksheet;

use App\Export\Excel\Spreadsheet;
use App\Export\Excel\Worksheet as FCToernooiWorksheet;
use Voetbal\Round;
use Voetbal\Round\Number as RoundNumber;
use Voetbal\NameService;
use Voetbal\Sport\ScoreConfig\Service as SportScoreConfigService;

class Planning extends FCToernooiWorksheet
{
    /**
     * @var SportScoreConfigService
     */
    protected $sportScoreConfigService;

    use GamesTrait;

    /**
     * @var mixed
     */
    protected $gameFilter;

    const COLUMN_POULE = 1;
    const COLUMN_START = 2;
    const COLUMN_FIELD = 3;
    const COLUMN_HOME = 4;
    const COLUMN_SCORE = 5;
    const COLUMN_AWAY = 6;
    const COLUMN_REFEREE = 7;

    const NR_OF_COLUMNS = 7;

    public function __construct( Spreadsheet $parent = null )
    {
        parent::__construct( $parent, 'planning' );
        $parent->addSheet($this, Spreadsheet::INDEX_PLANNING );


//        $this->setLineWidth( 0.5 );
        $this->sportScoreConfigService = new SportScoreConfigService();
        /*$this->maxPoulesPerLine = 3;
        $this->placeWidthStructure = 30;
        $this->pouleMarginStructure = 10;*/
/*
        <colgroup>
        <col *ngIf="aRoundNeedsRanking(roundsByNumber)">
        <col *ngIf="!aRoundNeedsRanking(roundsByNumber)">
        <col *ngIf="planningService.canCalculateStartDateTime(roundNumber)">
        <col>
        <col class="width-25">
        <col>
        <col class="width-25">
        <col *ngIf="hasReferees()" class="d-none d-sm-table-cell">
      </colgroup>*/
    }

//    public function getTitle(): ?string {
//        return $this->title;
//    }
//    public function setTitle( string $title ) {
//        $this->title = $title;
//    }

//    public function getPageMargin(){ return 20; }
//    public function getHeaderHeight(){ return 0; }

    public function getGameFilter() {
        return $this->gameFilter;
    }
    public function setGameFilter( $gameFilter ) {
        $this->gameFilter = $gameFilter;
    }

    public function getGames( RoundNumber $roundNumber ): array {
        $games = [];
        foreach( $roundNumber->getRounds() as $round ) {
            foreach( $round->getPoules() as $poule ) {
                foreach( $poule->getGames() as $game ) {
                    if( $this->gameFilter === null || $this->getGameFilter()($game) ) {
                        $games[] = $game;
                    }
                }
            }
        }
        return $games;
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

    public function draw() {
        $firstRoundNumber = $this->getParent()->getStructure()->getFirstRoundNumber();
        $row = 1;
        $this->drawRoundNumber( $firstRoundNumber, $row );
    }

    protected function drawRoundNumber( RoundNumber $roundNumber, int $row ) {

        $subHeader = $this->getParent()->getNameService()->getRoundNumberName( $roundNumber );
        $row =  $this->drawSubHeader( $row, $subHeader );
        $games = $this->getGames($roundNumber);
        if( count($games) > 0 ) {
            $row = $this->drawGamesHeader($roundNumber, $row);
        }
//        $games = $roundNumber->getGames( Game::ORDER_BY_BATCH );
//        foreach ($games as $game) {
//            $gameHeight = $page->getGameHeight($game);
//            if ($nY - $gameHeight < $page->getPageMargin() ) {
//                list($page, $nY) = $this->createPagePlanning("wedstrijden");
//                $nY = $page->drawGamesHeader($roundNumber, $nY);
//            }
//            $nY = $page->drawGame($game, $nY);
//        }

        if( $roundNumber->hasNext() ) {
            $this->drawRoundNumber( $roundNumber->getNext(), $row + 2 );
        }
    }
}