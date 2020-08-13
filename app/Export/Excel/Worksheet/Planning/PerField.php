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
use Sports\Round;
use Sports\Poule;
use Sports\Game;
use Sports\Round\Number as RoundNumber;
use Sports\Field;
use Sports\Sport\ScoreConfig\Service as SportScoreConfigService;

class PerField extends PlanningWorksheet
{
    public function __construct(Spreadsheet $parent)
    {
        parent::__construct($parent, 'planning per veld', Spreadsheet::INDEX_PLANNING_PER_FIELD);
    }

    public function draw()
    {
        $fields = $this->getParent()->getTournament()->getCompetition()->getFields();
        $row = 1;
        foreach ($fields as $field) {
            $this->drewbreak = false;
            $row = $this->drawField($field, $row);
        }
        for ($columnNr = 1; $columnNr <= Planning::NR_OF_COLUMNS; $columnNr++) {
            $this->getColumnDimensionByColumn($columnNr)->setAutoSize(true);
        }
    }

    protected function drawField(Field $field, int $row): int
    {
        $firstRoundNumber = $this->getParent()->getStructure()->getFirstRoundNumber();
        return $this->drawRoundNumber($field, $firstRoundNumber, $row);
    }

    protected function drawRoundNumber(Field $field, RoundNumber $roundNumber, int $row): int
    {
        $games = $this->getGames($field, $roundNumber);
        if (count($games) > 0) {
            $subHeader = "veld " . $field->getName();
            $subHeader .= " - " . $this->getParent()->getNameService()->getRoundNumberName($roundNumber);
            $row = $this->drawSubHeaderHelper($row, $subHeader);

            foreach ($games as $game) {
                if ($this->drawBreakBeforeGame($game)) {
                    $row = $this->drawBreak($roundNumber, $row);
                }
                $row = $this->drawGame($game, $row, false);
            }
        }

        if ($roundNumber->hasNext()) {
            return $this->drawRoundNumber($field, $roundNumber->getNext(), $row + 1);
        }
        return $row + 1;
    }

    public function getGames(Field $field, RoundNumber $roundNumber): array
    {
        return array_filter(
            $roundNumber->getGames(Game::ORDER_BY_BATCH),
            function (Game $game) use ($field): bool {
                return $game->getField() === $field;
            }
        );
    }

//    public function drawPoule( Poule $poule, int $row ): int {
//        $roundNumber = $poule->getRound()->getNumber();
//        $subHeader = $this->getParent()->getNameService()->getPouleName( $poule, true );
//        $subHeader .= " - " . $this->getParent()->getNameService()->getRoundNumberName( $roundNumber );
//        $row =  $this->drawSubHeader( $row, $subHeader );
//
//        foreach( $poule->getGames() as $game ) {
//            $row = $this->drawGame( $game, $row, false );
//        }
//        return $row + 1;
//    }
}
