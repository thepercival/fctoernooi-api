<?php
/**
 * Created by PhpStorm.
 * User: coen
 * Date: 3-12-18
 * Time: 19:15
 */

namespace App\Export\Excel\Worksheet;


use PhpOffice\PhpSpreadsheet\Style\Alignment;
use Voetbal\Game;
use Voetbal\Round\Number as RoundNumber;
use App\Export\Pdf\Page;
use Voetbal\State;

trait GamesTrait
{
    protected $hasReferees;
    protected $selfRefereesAssigned;
    protected $refereesAssigned;

    protected function drawSubHeader( int $rowStart, string $title, int $colStart = null, int $colEnd = null ): int  {
        if( $colStart === null ) {
            $colStart = 1;
        }
        if( $colEnd === null ) {
            $colEnd = Planning::NR_OF_COLUMNS;
        }
        return parent::drawSubHeader( $rowStart, $title, $colStart, $colEnd );
    }

    public function setSelfRefereesAssigned( bool $selfRefereesAssigned) {
        $this->selfRefereesAssigned = $selfRefereesAssigned;
    }

    public function setRefereesAssigned( bool $refereesAssigned) {
        $this->refereesAssigned = $refereesAssigned;
    }

    public function drawGamesHeader( RoundNumber $roundNumber, int $row ): int {
        $range = $this->range( Planning::COLUMN_POULE, $row, Planning::COLUMN_POULE, $row);


        $cell = $this->getCellByColumnAndRow( Planning::COLUMN_POULE, $row);
        $cell->getStyle()->getAlignment()->setHorizontal( Alignment::HORIZONTAL_CENTER );
        $cell->setValue( $roundNumber->needsRanking() ? "p." : "vs" );

        $cell = $this->getCellByColumnAndRow( Planning::COLUMN_START, $row);
        if( $roundNumber->getValidPlanningConfig()->getEnableTime() ) {
            $cell->getStyle()->getAlignment()->setHorizontal( Alignment::HORIZONTAL_CENTER );
            $cell->setValue( $this->getParent()->gamesOnSameDay( $roundNumber ) ? "tijd" : "datum tijd" );
        } else {
            $cell->setValue( "batchNr" );
        }

        $cell = $this->getCellByColumnAndRow( Planning::COLUMN_FIELD, $row);
        $cell->getStyle()->getAlignment()->setHorizontal( Alignment::HORIZONTAL_CENTER );
        $cell->setValue( "v." );

        $cell = $this->getCellByColumnAndRow( Planning::COLUMN_HOME, $row);
        $cell->getStyle()->getAlignment()->setHorizontal( Alignment::HORIZONTAL_RIGHT );
        $cell->setValue( "thuis" );

        $cell = $this->getCellByColumnAndRow( Planning::COLUMN_SCORE, $row);
        $cell->getStyle()->getAlignment()->setHorizontal( Alignment::HORIZONTAL_CENTER );
        $cell->setValue( "score" );

        $cell = $this->getCellByColumnAndRow( Planning::COLUMN_AWAY, $row);
        $cell->getStyle()->getAlignment()->setHorizontal( Alignment::HORIZONTAL_LEFT );
        $cell->setValue( "uit" );

        if( $this->refereesAssigned || $this->selfRefereesAssigned ) {
            $cell = $this->getCellByColumnAndRow( Planning::COLUMN_REFEREE, $row);
            $cell->getStyle()->getAlignment()->setHorizontal( Alignment::HORIZONTAL_CENTER );
            $cell->setValue( $this->selfRefereesAssigned ? 'scheidsrechter' : 'sch.' );
        }
        return $row + 2;
    }

    /**
     * @param Game $game
     * @param int $row
     * @return int
     */
    public function drawGame( Game $game, int $row ): int
    {
        if( $this->gameFilter !== null && !$this->getGameFilter()($game) ) {
            return $row;
        }

        #DFD7CA

        $pouleName = $this->getParent()->getNameService()->getPouleName($game->getPoule(), false);
        $cell = $this->getCellByColumnAndRow( Planning::COLUMN_POULE, $row);
        $cell->getStyle()->getAlignment()->setHorizontal( Alignment::HORIZONTAL_CENTER );
        $cell->setValue( $pouleName );

//        $nX = $this->getPageMargin();
//        $nRowHeight = $this->getRowHeight();
        $roundNumber = $game->getRound()->getNumber();
//
        $cell = $this->getCellByColumnAndRow( Planning::COLUMN_START, $row);
        if ($roundNumber->getValidPlanningConfig()->getEnableTime()) {
            $text = "";
            $localDateTime = $game->getStartDateTime()->setTimezone(new \DateTimeZone('Europe/Amsterdam'));
            if (!$this->getParent()->gamesOnSameDay($roundNumber)) {
                $text = $localDateTime->format("d-m ");
            }
            $text .= $localDateTime->format("H:i");
            $cell->getStyle()->getAlignment()->setHorizontal( Alignment::HORIZONTAL_CENTER );
            $cell->setValue( $text );
        } else {
            $cell->setValue( "batchNr" );
        }

        $cell = $this->getCellByColumnAndRow( Planning::COLUMN_FIELD, $row);
        $cell->getStyle()->getAlignment()->setHorizontal( Alignment::HORIZONTAL_CENTER );
        $cell->setValue( $game->getField()->getName() );

        $home = $this->getParent()->getNameService()->getPlacesFromName( $game->getPlaces( Game::HOME ), true, true );
        $cell = $this->getCellByColumnAndRow( Planning::COLUMN_HOME, $row);
        $cell->getStyle()->getAlignment()->setHorizontal( Alignment::HORIZONTAL_RIGHT );
        $cell->setValue( $home );

        $cell = $this->getCellByColumnAndRow( Planning::COLUMN_SCORE, $row);
        $cell->getStyle()->getAlignment()->setHorizontal( Alignment::HORIZONTAL_CENTER );
        $cell->setValue( $this->getScore($game) );

        $away = $this->getParent()->getNameService()->getPlacesFromName( $game->getPlaces( Game::AWAY ), true, true );
        $cell = $this->getCellByColumnAndRow( Planning::COLUMN_AWAY, $row);
        $cell->getStyle()->getAlignment()->setHorizontal( Alignment::HORIZONTAL_LEFT );
        $cell->setValue( $away );

        $cell = $this->getCellByColumnAndRow( Planning::COLUMN_REFEREE, $row);
        $cell->getStyle()->getAlignment()->setHorizontal( Alignment::HORIZONTAL_CENTER );
        if ($game->getReferee() !== null) {
            $cell->setValue( $game->getReferee()->getInitials() );
        }  else if( $game->getRefereePlace() !== null ) {
            $placeRef = $this->getParent()->getNameService()->getPlaceName( $game->getRefereePlace(), true, true);
            $cell->setValue( $placeRef );
        }

//<tr *ngIf="tournament.hasBreak() && isBreakInBetween(game)" class="table-info">
//            <td></td>
//            <td *ngIf="planningService.canCalculateStartDateTime(roundNumber)"></td>
//            <td></td>
//            <td class="width-25 text-right">PAUZE</td>
//            <td></td>
//            <td class="width-25"></td>
//            <td *ngIf="hasReferees()" class="d-none d-sm-table-cell"></td>
//          </tr>
//        <tr  >
        return $row + 1;
    }

    protected function getScore(Game $game): string {
        $score = ' - ';
        if ($game->getState() !== State::Finished) {
            return $score;
        }
        $finalScore = $this->sportScoreConfigService->getFinal($game);
        if ($finalScore === null) {
            return $score;
        }
        return $finalScore->getHome() . $score . $finalScore->getAway();
    }
}