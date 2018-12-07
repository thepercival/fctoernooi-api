<?php
/**
 * Created by PhpStorm.
 * User: coen
 * Date: 3-12-18
 * Time: 19:15
 */

namespace FCToernooi\Pdf\Page;

use Voetbal\Game;
use Voetbal\Competition;
use Voetbal\Round\Number as RoundNumber;
use FCToernooi\Pdf\Page;
use Voetbal\Structure\NameService;

trait GamesTrait
{
    protected $columnWidths;

    protected function setGamesColumns( RoundNumber $roundNumber )
    {
        $this->columnWidths = [];
        $this->columnWidths["poule"] = 0.05;
        $this->columnWidths["start"] = 0.15;
        $this->columnWidths["field"] = 0.05;
        $this->columnWidths["home"] = 0.28;
        $this->columnWidths["score"] = 0.11;
        $this->columnWidths["away"] = 0.28;
        $this->columnWidths["referee"] = 0.08;
        $planningService = $this->getParent()->getPlanningService();
        if( !$planningService->canCalculateStartDateTime($roundNumber) ) {
            $this->columnWidths["home"] += ( $this->columnWidths["start"] / 2 );
            $this->columnWidths["away"] += ( $this->columnWidths["start"] / 2 );
        } else if( $planningService->gamesOnSameDay( $roundNumber ) ) {
            $this->columnWidths["start"] /= 2;
            $this->columnWidths["home"] += ( $this->columnWidths["start"] / 2 );
            $this->columnWidths["away"] += ( $this->columnWidths["start"] / 2 );
        }
        if( $roundNumber->getCompetition()->getFields()->count() === 0 || $this->fieldFilter !== null ) {
            $this->columnWidths["home"] += ( $this->columnWidths["field"] / 2 );
            $this->columnWidths["away"] += ( $this->columnWidths["field"] / 2 );
        }
        if( $roundNumber->getCompetition()->getReferees()->count() === 0 ) {
            $this->columnWidths["home"] += ( $this->columnWidths["referee"] / 2 );
            $this->columnWidths["away"] += ( $this->columnWidths["referee"] / 2 );
        }
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

        $planningService = $this->getParent()->getPlanningService();
        if( $planningService->canCalculateStartDateTime($roundNumber) ) {
            $text = null;
            if( $planningService->gamesOnSameDay( $roundNumber ) ) {
                $text = "tijd";
            } else {
                $text = "datum tijd";
            }
            $nX = $this->drawCell( $text, $nX, $nY, $gameStartWidth, $nRowHeight, Page::ALIGNCENTER, "black" );
        }

        if( $roundNumber->getCompetition()->getFields()->count() > 0 && $this->fieldFilter === null ) {
            $nX = $this->drawCell( "v.", $nX, $nY, $gameFieldWidth, $nRowHeight, Page::ALIGNCENTER, "black" );
        }

        $nX = $this->drawCell( "thuis", $nX, $nY, $gameHomeWidth, $nRowHeight, Page::ALIGNCENTER, "black" );

        $nX = $this->drawCell( "score", $nX, $nY, $gameScoreWidth, $nRowHeight, Page::ALIGNCENTER, "black" );

        $nX = $this->drawCell( "uit", $nX, $nY, $gameAwayWidth, $nRowHeight, Page::ALIGNCENTER, "black" );

        if( $roundNumber->getCompetition()->getReferees()->count() > 0 ) {
            $this->drawCell( "sch.", $nX, $nY, $gameRefereeWidth, $nRowHeight, Page::ALIGNCENTER, "black" );
        }
        return $nY - $nRowHeight;
    }

    /**
     * @return int
     */
    public function drawGame( Game $game, $nY )
    {
        if( $this->fieldFilter !== null && $this->fieldFilter !== $game->getField() ) {
            return $nY;
        }

        $nX = $this->getPageMargin();
        $nRowHeight = $this->getRowHeight();
        $roundNumber = $game->getRound()->getNumber();

        $pouleName = (new NameService())->getPouleName($game->getPoule(), false);
        $nX = $this->drawCell($pouleName, $nX, $nY, $this->getGamesPouleWidth(), $nRowHeight, Page::ALIGNCENTER,
            "black");

        $nameService = new NameService();
        $planningService = $this->getParent()->getPlanningService();
        if ($planningService->canCalculateStartDateTime($roundNumber)) {
            $text = "";
            $localDateTime = $game->getStartDateTime()->setTimezone(new \DateTimeZone('Europe/Amsterdam'));
            if (!$planningService->gamesOnSameDay($roundNumber)) {
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

        if ($game->getField() !== null && $this->fieldFilter === null ) {
            $nX = $this->drawCell($game->getField()->getName(), $nX, $nY, $this->getGamesFieldWidth(), $nRowHeight,
                Page::ALIGNCENTER, "black");
        }

        $home = $nameService->getPoulePlaceName($game->getHomePoulePlace(), true);
        $nX = $this->drawCell($home, $nX, $nY, $this->getGamesHomeWidth(), $nRowHeight, Page::ALIGNRIGHT, "black");

        $nX = $this->drawCell($this->getScore($game), $nX, $nY, $this->getGamesScoreWidth(), $nRowHeight, Page::ALIGNCENTER, "black");

        $away = $nameService->getPoulePlaceName($game->getAwayPoulePlace(), true);
        $nX = $this->drawCell($away, $nX, $nY, $this->getGamesAwayWidth(), $nRowHeight, Page::ALIGNLEFT, "black");

        if ($game->getReferee() !== null) {
            $this->drawCell($game->getReferee()->getInitials(), $nX, $nY, $this->getGamesRefereeWidth(), $nRowHeight,
                Page::ALIGNCENTER, "black");
        }

        return $nY - $nRowHeight;

//<tr *ngIf="tournament.hasBreak() && isBreakInBetween(game)" class="table-info">
//            <td></td>
//            <td *ngIf="planningService.canCalculateStartDateTime(roundNumber)"></td>
//            <td *ngIf="hasFields()"></td>
//            <td class="width-25 text-right">PAUZE</td>
//            <td></td>
//            <td class="width-25"></td>
//            <td *ngIf="hasReferees()" class="d-none d-sm-table-cell"></td>
//          </tr>
//        <tr  >
    }

    protected function getScore(Game $game): string {
        $score = ' - ';
        if ($game->getState() !== Game::STATE_PLAYED) {
            return $score;
        }
        $finalScore = $game->getFinalScore();
        if ($finalScore === null) {
            return $score;
        }
        return $finalScore->getHome() . $score + $finalScore->getAway();
    }
}