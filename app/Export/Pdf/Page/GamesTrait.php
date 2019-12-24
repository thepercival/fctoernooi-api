<?php
/**
 * Created by PhpStorm.
 * User: coen
 * Date: 3-12-18
 * Time: 19:15
 */

namespace App\Export\Pdf\Page;

use App\Export\Excel\Worksheet\Planning;
use Voetbal\Game;
use Voetbal\Round\Number as RoundNumber;
use App\Export\Pdf\Page;
use Voetbal\State;
use Voetbal\NameService;

trait GamesTrait
{
    protected $columnWidths;
    protected $hasReferees;
    protected $selfRefereesAssigned;
    protected $refereesAssigned;

    protected function setGamesColumns( RoundNumber $roundNumber )
    {
        $this->columnWidths = [];
        $this->columnWidths["poule"] = 0.05;
        $this->columnWidths["start"] = 0.15;
        $this->columnWidths["field"] = 0.05;
        $this->columnWidths["score"] = 0.11;
        $this->columnWidths["referee"] = $this->selfRefereesAssigned ? 0.22 : 0.08;
        $this->columnWidths["home"] = $this->selfRefereesAssigned ? 0.21 : 0.28;
        $this->columnWidths["away"] = $this->selfRefereesAssigned ? 0.21 : 0.28;
        if( !$roundNumber->getValidPlanningConfig()->getEnableTime() ) {
            $this->columnWidths["home"] += ( $this->columnWidths["start"] / 2 );
            $this->columnWidths["away"] += ( $this->columnWidths["start"] / 2 );
        } else if( $this->getParent()->gamesOnSameDay( $roundNumber ) ) {
            $this->columnWidths["start"] /= 2;
            $this->columnWidths["home"] += ( $this->columnWidths["start"] / 2 );
            $this->columnWidths["away"] += ( $this->columnWidths["start"] / 2 );
        }
        if( $this->refereesAssigned === false ) {
            $this->columnWidths["home"] += ( $this->columnWidths["referee"] / 2 );
            $this->columnWidths["away"] += ( $this->columnWidths["referee"] / 2 );
        }
    }

    public function setSelfRefereesAssigned( bool $selfRefereesAssigned) {
        $this->selfRefereesAssigned = $selfRefereesAssigned;
    }

    public function setRefereesAssigned( bool $refereesAssigned) {
        $this->refereesAssigned = $refereesAssigned;
    }

    public function getGameHeight( Game $game )
    {
        return $this->getRowHeight();
    }

    protected function getGamesWidthHelper( string $key) {
        return $this->columnWidths[$key] * $this->getDisplayWidth();
    }

    protected function getGamesPouleWidth() {
        return $this->getGamesWidthHelper( "poule" );
    }

    protected function getGamesStartWidth() {
        return $this->getGamesWidthHelper( "start" );
    }

    protected function getGamesFieldWidth() {
        return $this->getGamesWidthHelper( "field" );
    }

    protected function getGamesHomeWidth() {
        return $this->getGamesWidthHelper( "home" );
    }

    protected function getGamesScoreWidth() {
        return $this->getGamesWidthHelper( "score" );
    }

    protected function getGamesAwayWidth() {
        return $this->getGamesWidthHelper( "away" );
    }

    protected function getGamesRefereeWidth() {
        return $this->getGamesWidthHelper( "referee" );
    }

    protected function getGameWidth(): int
    {
        $width = $this->getGamesPouleWidth() +
            $this->getGamesStartWidth() +
            $this->getGamesFieldWidth() +
            $this->getGamesHomeWidth() +
            $this->getGamesScoreWidth() +
            $this->getGamesAwayWidth();
        if ($this->refereesAssigned || $this->selfRefereesAssigned) {
            $width += $this->getGamesRefereeWidth();
        }
        return $width;
    }

    public function drawGamesHeader( RoundNumber $roundNumber, $nY ) {

        $this->setGamesColumns( $roundNumber );

        $nX = $this->getPageMargin();
        $nRowHeight = $this->getRowHeight();
        $gamePouleWidth = $this->getGamesPouleWidth();
        $gameStartWidth = $this->getGamesStartWidth();
        $gameHomeWidth = $this->getGamesHomeWidth();
        $gameScoreWidth = $this->getGamesScoreWidth();
        $gameAwayWidth = $this->getGamesAwayWidth();
        $gameRefereeWidth = $this->getGamesRefereeWidth();
        $gameFieldWidth = $this->getGamesFieldWidth();

        $text = null;
        if( $roundNumber->needsRanking() ) {
            $text = "p.";
        } else {
            $text = "vs";
        }
        $nX = $this->drawCell( $text, $nX, $nY, $gamePouleWidth, $nRowHeight, Page::ALIGNCENTER, "black" );

        if( $roundNumber->getValidPlanningConfig()->getEnableTime() ) {
            $text = null;
            if( $this->getParent()->gamesOnSameDay( $roundNumber ) ) {
                $text = "tijd";
            } else {
                $text = "datum tijd";
            }
            $nX = $this->drawCell( $text, $nX, $nY, $gameStartWidth, $nRowHeight, Page::ALIGNCENTER, "black" );
        }

        // if( $this->fieldFilter === null ) {
            $nX = $this->drawCell( "v.", $nX, $nY, $gameFieldWidth, $nRowHeight, Page::ALIGNCENTER, "black" );
        // }

        $nX = $this->drawCell( "thuis", $nX, $nY, $gameHomeWidth, $nRowHeight, Page::ALIGNRIGHT, "black" );

        $nX = $this->drawCell( "score", $nX, $nY, $gameScoreWidth, $nRowHeight, Page::ALIGNCENTER, "black" );

        $nX = $this->drawCell( "uit", $nX, $nY, $gameAwayWidth, $nRowHeight, Page::ALIGNLEFT, "black" );

        if( $this->refereesAssigned || $this->selfRefereesAssigned ) {
            $title = $this->selfRefereesAssigned ? 'scheidsrechter' : 'sch.';
            $this->drawCell( $title, $nX, $nY, $gameRefereeWidth, $nRowHeight, Page::ALIGNCENTER, "black" );
        }

        return $nY - $nRowHeight;
    }

    /**
     * @return int
     */
    public function drawGame( Game $game, $nY, bool $striped = false )
    {
        if( $this->gameFilter !== null && !$this->getGameFilter()($game) ) {
            return $nY;
        }

        $nX = $this->getPageMargin();
        $nRowHeight = $this->getRowHeight();
        $roundNumber = $game->getRound()->getNumber();

        $grayScale = ( ($game->getBatchNr() % 2) === 0 && $striped === true ) ? 0.9 : 1;
        $this->setFillColor( new \Zend_Pdf_Color_GrayScale( $grayScale ) );

        $pouleName = $this->getParent()->getNameService()->getPouleName($game->getPoule(), false);
        $nX = $this->drawCell($pouleName, $nX, $nY, $this->getGamesPouleWidth(), $nRowHeight, Page::ALIGNCENTER,
            "black");

        $nameService = $this->getParent()->getNameService();
        if ($roundNumber->getValidPlanningConfig()->getEnableTime()) {
            $text = "";
            $localDateTime = $game->getStartDateTime()->setTimezone(new \DateTimeZone('Europe/Amsterdam'));
            if (!$this->getParent()->gamesOnSameDay($roundNumber)) {
//                $df = new \IntlDateFormatter('nl_NL',\IntlDateFormatter::LONG, \IntlDateFormatter::NONE,'Europe/Oslo');
//                $dateElements = explode(" ", $df->format($game->getStartDateTime()));
//                $month = strtolower( substr( $dateElements[1], 0, 3 ) );
//                $text = $game->getStartDateTime()->format("d") . " " . $month . " ";
                $text = $localDateTime->format("d-m ");
            }
            $text .= $localDateTime->format("H:i");
            $nX = $this->drawCell($text, $nX, $nY, $this->getGamesStartWidth(), $nRowHeight, Page::ALIGNCENTER,
                "black");
        }

        // if ( $this->fieldFilter === null ) {
            $nX = $this->drawCell($game->getField()->getName(), $nX, $nY, $this->getGamesFieldWidth(), $nRowHeight,
                Page::ALIGNCENTER, "black");
        // }

        $home = $nameService->getPlacesFromName( $game->getPlaces( Game::HOME ), true, true );
        $nX = $this->drawCell($home, $nX, $nY, $this->getGamesHomeWidth(), $nRowHeight, Page::ALIGNRIGHT, "black");

        $nX = $this->drawCell($this->getScore($game), $nX, $nY, $this->getGamesScoreWidth(), $nRowHeight, Page::ALIGNCENTER, "black");

        $away = $nameService->getPlacesFromName( $game->getPlaces( Game::AWAY ), true, true );
        $nX = $this->drawCell($away, $nX, $nY, $this->getGamesAwayWidth(), $nRowHeight, Page::ALIGNLEFT, "black");

        if ($game->getReferee() !== null) {
            $this->drawCell($game->getReferee()->getInitials(),
                $nX, $nY, $this->getGamesRefereeWidth(), $nRowHeight, Page::ALIGNCENTER, "black");
        }  else if( $game->getRefereePlace() !== null ) {
            $this->drawCell($nameService->getPlaceName( $game->getRefereePlace(), true, true),
                $nX, $nY, $this->getGamesRefereeWidth(), $nRowHeight, Page::ALIGNCENTER, "black");
        }

        return $nY - $nRowHeight;

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