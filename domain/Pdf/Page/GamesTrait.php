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

    protected function setGamesColumns( Competition $competition )
    {
        $this->columnWidths = [];
        $this->columnWidths["poule"] = 0.1;
        $this->columnWidths["start"] = 0.1;
        $this->columnWidths["field"] = 0.1;
        $this->columnWidths["home"] = 0.25;
        $this->columnWidths["score"] = 0.1;
        $this->columnWidths["away"] = 0.25;
        $this->columnWidths["referee"] = 0.1;
        if( $competition->getFields()->count() === 0 ) {
            $this->columnWidths["home"] += ( $this->columnWidths["field"] / 2 );
            $this->columnWidths["away"] += ( $this->columnWidths["field"] / 2 );
        }
        if( $competition->getReferees()->count() === 0 ) {
            $this->columnWidths["home"] += ( $this->columnWidths["referee"] / 2 );
            $this->columnWidths["away"] += ( $this->columnWidths["referee"] / 2 );
        }
    }

    public function getGameHeight( Game $game )
    {
        return $this->getRowHeight();
    }

    protected function getGamesWidthHelper( string $key) {
        if( $this->columnWidths === null ) {
            $this->setGamesColumns( $this->getParent()->getTournament()->getCompetition() );
        }
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
        $text = null;
        if( $planningService->canCalculateStartDateTime($roundNumber) ) {
            if( $planningService->gamesOnSameDay( $roundNumber ) ) {
                $text = "start";
            } else {
                $startDate = $planningService->calculateStartDateTime($roundNumber);
                $text = $startDate->format("d-m");
            }
        }
        $nX = $this->drawCell( $text, $nX, $nY, $gameStartWidth, $nRowHeight, Page::ALIGNCENTER, "black" );

        if( $this->getParent()->getTournament()->getCompetition()->getFields()->count() > 0 ) {
            $nX = $this->drawCell( "v.", $nX, $nY, $gameFieldWidth, $nRowHeight, Page::ALIGNCENTER, "black" );
        }

        $nX = $this->drawCell( "thuis", $nX, $nY, $gameHomeWidth, $nRowHeight, Page::ALIGNCENTER, "black" );

        $nX = $this->drawCell( "score", $nX, $nY, $gameScoreWidth, $nRowHeight, Page::ALIGNCENTER, "black" );

        $nX = $this->drawCell( "uit", $nX, $nY, $gameAwayWidth, $nRowHeight, Page::ALIGNCENTER, "black" );

        if( $this->getParent()->getTournament()->getCompetition()->getReferees()->count() > 0 ) {
            $this->drawCell( "sch.", $nX, $nY, $gameRefereeWidth, $nRowHeight, Page::ALIGNCENTER, "black" );
        }
        return $nY - $nRowHeight;
    }

    /**
     * @return int
     */
    public function drawGame( Game $game, $nY )
    {
        $nX = $this->getPageMargin();
        $nRowHeight = $this->getRowHeight();
        $roundNumber = $game->getRound()->getNumber();

        $pouleName = (new NameService())->getPouleName($game->getPoule(), false);
        $nX = $this->drawCell( $pouleName, $nX, $nY, $this->getGamesPouleWidth(), $nRowHeight, Page::ALIGNCENTER, "black" );

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
//            <td  class="text-center pl-1">
//              <span>{{nameService.getPouleName(game.getPoule(), false)}}</span>
//            </td>
//            <td *ngIf="pouleHasPopover(game) && game.getPoule().needsRanking()" class="text-center pl-1 pointer"
//              container="body" placement="right" [ngbPopover]="popRanking" [autoClose]="false" triggers="manual" #p="ngbPopover"
//              (click)="showRanking( p, game.getPoule() )">
//              <span class="text-info">{{nameService.getPouleName(game.getPoule(), false)}}</span>
//            </td>
//            <td *ngIf="pouleHasPopover(game) && !game.getPoule().needsRanking()" class="text-center pl-1 pointer" title="wedstrijd"
//              container="body" placement="right" [ngbPopover]="getGameQualificationDescription(game)">
//              <span class="text-info">{{nameService.getPouleName(game.getPoule(), false)}}</span>
//            </td>
//            <td *ngIf="planningService.canCalculateStartDateTime(roundNumber)">
//              <span *ngIf="!onSameDay()">{{game.getStartDateTime().toLocaleString("nl",
//                {"month":"short","day":"2-digit"})}} </span>
//              <span>{{game.getStartDateTime().toLocaleString("nl", {"hour12":
//                false,"hour":"2-digit","minute":"2-digit"})}}</span>
//            </td>
//            <td *ngIf="hasFields()" class="text-right">{{game.getField()?.getName()}}</td>
//            <td class="pouleplace text-right" [ngClass]="{'text-primary': isTeamFav(game.getHomePoulePlace().getTeam()) }">
//              <span class="{{getPoulePlaceClass(game.getHomePoulePlace())}}">{{nameService.getPoulePlaceName(game.getHomePoulePlace(),
//        true )}}</span>
//              <span *ngIf="game.getHomePoulePlace().getTeam() && game.getHomePoulePlace().getTeam().getAbbreviation()"
//                class="d-inline d-sm-none">{{game.getHomePoulePlace().getTeam().getAbbreviation()}}</span>
//              <span *ngIf="game.getHomePoulePlace().getTeam() && game.getHomePoulePlace().getTeam().getAbbreviation()"
//                class="flag-16 flag-{{game.getHomePoulePlace().getTeam().getAbbreviation()}}-16"></span>
//            </td>
//            <td class="score text-center" [ngClass]="{ 'toGameEdit': userIsGameResultAdmin }">
//              <button *ngIf="hasEditPermissions(game)" class="btn btn-sm btn-outline-primary" (click)="linkToGameEdit(game)">
//                <fa-icon *ngIf="!isPlayed(game)" [icon]="['fas', 'pencil-alt']"></fa-icon>
//                <span *ngIf="isPlayed(game)">{{getScore(game)}}</span>
//              </button>
//              <span *ngIf="!hasEditPermissions(game)">{{getScore(game)}}</span>
//            </td>
//            <td class="pouleplace" [ngClass]="{ 'text-primary': isTeamFav(game.getAwayPoulePlace().getTeam()) }">
//              <span *ngIf="game.getAwayPoulePlace().getTeam() && game.getAwayPoulePlace().getTeam().getAbbreviation()"
//                class="flag-16 flag-{{game.getAwayPoulePlace().getTeam().getAbbreviation()}}-16"></span>
//              <span class="{{getPoulePlaceClass(game.getAwayPoulePlace())}}">{{nameService.getPoulePlaceName(game.getAwayPoulePlace(),
//        true )}}</span>
//              <span *ngIf="game.getAwayPoulePlace().getTeam() && game.getAwayPoulePlace().getTeam().getAbbreviation()"
//                class="d-inline d-sm-none">{{game.getAwayPoulePlace().getTeam().getAbbreviation()}}</span>
//            </td>
//            <td *ngIf="hasReferees()" class="d-none d-sm-table-cell" [ngClass]="{'text-primary': isRefereeFav(game.getReferee()) }">{{game.getReferee()?.getInitials()}}</td>
//          </tr>
    }
}