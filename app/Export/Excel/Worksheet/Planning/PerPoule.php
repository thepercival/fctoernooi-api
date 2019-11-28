<?php
/**
 * Created by PhpStorm.
 * User: coen
 * Date: 9-11-18
 * Time: 19:28
 */

namespace App\Export\Excel\Worksheet\Planning;

use App\Export\Excel\Spreadsheet;
use App\Export\Excel\Worksheet\Planning;
use App\Export\Excel\Worksheet\Planning as PlanningWorksheet;
use Voetbal\Round;
use Voetbal\Poule;
use Voetbal\Game;
use Voetbal\Round\Number as RoundNumber;
use Voetbal\NameService;
use Voetbal\Sport\ScoreConfig\Service as SportScoreConfigService;

class PerPoule extends PlanningWorksheet
{
    public function __construct( Spreadsheet $parent )
    {
        parent::__construct( $parent, 'planning per poule', Spreadsheet::INDEX_PLANNING_PER_POULE );
    }

    public function draw() {
        $firstRoundNumber = $this->getParent()->getStructure()->getFirstRoundNumber();
        $row = 1;
        $this->drawRoundNumber( $firstRoundNumber, $row );
        for( $columnNr = 1 ; $columnNr <= Planning::NR_OF_COLUMNS ; $columnNr++ ) {
            $this->getColumnDimensionByColumn($columnNr)->setAutoSize(true);
        }
    }

    protected function drawRoundNumber( RoundNumber $roundNumber, int $row ) {

        foreach( $roundNumber->getRounds() as $round ) {
            foreach( $round->getPoules() as $poule ) {
                $row = $this->drawPoule( $poule, $row );
            }
        }

        if( $roundNumber->hasNext() ) {
            $this->drawRoundNumber( $roundNumber->getNext(), $row );
        }
    }

    public function drawPoule( Poule $poule, int $row ): int {
        $roundNumber = $poule->getRound()->getNumber();
        $subHeader = $this->getParent()->getNameService()->getPouleName( $poule, true );
        $subHeader .= " - " . $this->getParent()->getNameService()->getRoundNumberName( $roundNumber );
        $row =  $this->drawSubHeaderHelper( $row, $subHeader );

        foreach( $poule->getGames() as $game ) {
            $row = $this->drawGame( $game, $row, false );
        }
        return $row + 1;
    }
}