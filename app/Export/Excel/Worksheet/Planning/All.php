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

class All extends PlanningWorksheet
{
    public function __construct( Spreadsheet $parent )
    {
        parent::__construct( $parent, 'planning', Spreadsheet::INDEX_PLANNING );
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

        $subHeader = $this->getParent()->getNameService()->getRoundNumberName( $roundNumber );
        $row =  $this->drawSubHeaderHelper( $row, $subHeader );
        $games = $roundNumber->getGames( Game::ORDER_BY_BATCH );
        if( count($games) > 0 ) {
            $row = $this->drawGamesHeader($roundNumber, $row);
        }
        foreach ($games as $game) {
            $row = $this->drawGame($game, $row);
        }

        if( $roundNumber->hasNext() ) {
            $this->drawRoundNumber( $roundNumber->getNext(), $row + 2 );
        }
    }
}