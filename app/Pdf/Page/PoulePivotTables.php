<?php
/**
 * Created by PhpStorm.
 * User: coen
 * Date: 2-2-18
 * Time: 15:03
 */

namespace App\Pdf\Page;

use App\Pdf\Page as ToernooiPdfPage;
use Voetbal\NameService;
use Voetbal\Poule;
use Voetbal\Place;
use Voetbal\Game;
use Voetbal\State;
use Voetbal\Round\Number as RoundNumber;
use Voetbal\Ranking\Service as RankingService;
use Voetbal\Sport\ScoreConfig\Service as SportScoreConfigService;

class PoulePivotTables extends ToernooiPdfPage
{
    /**
     * @var SportScoreConfigService
     */
    protected $sportScoreConfigService;
    protected $nameColumnWidth;
    protected $pointsColumnWidth;
    protected $rankColumnWidth;
    protected $versusColumnsWidth;
    /*protected $maxPoulesPerLine;
    protected $placeWidthStructure;
    protected $pouleMarginStructure;
    */protected $rowHeight;

    public function __construct( $param1 )
    {
        parent::__construct( $param1 );
        $this->setLineWidth( 0.5 );
        $this->nameColumnWidth = $this->getDisplayWidth() * 0.25;
        $this->versusColumnsWidth = $this->getDisplayWidth() * 0.60;
        $this->pointsColumnWidth = $this->getDisplayWidth() * 0.08;
        $this->rankColumnWidth = $this->getDisplayWidth() * 0.07;
        $this->sportScoreConfigService = new SportScoreConfigService();
        /*$this->maxPoulesPerLine = 3;
        $this->placeWidthStructure = 30;
        $this->pouleMarginStructure = 10;*/
    }

    public function getPageMargin(){ return 20; }
    public function getHeaderHeight(){ return 0; }

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
        $this->drawCell( $subHeader, $nX, $nY, $displayWidth, $fontHeightSubHeader, ToernooiPdfPage::ALIGNCENTER );
        $this->setFont( $this->getParent()->getFont(), $this->getParent()->getFontHeight() );
        return $nY - ( 2 * $fontHeightSubHeader );
    }

    /**
     * t/m 3 places 0g, t/m 8 places 45g, hoger 90g
     *
     * @param Poule $poule
     */
    public function getPouleHeight( Poule $poule )
    {
        $nrOfPlaces = $poule->getPlaces()->count();

        // header row
        $versusColumnWidth = $this->versusColumnsWidth / $nrOfPlaces;
        $degrees = $this->getDegrees( $nrOfPlaces );
        $height = $this->getVersusHeight( $versusColumnWidth, $degrees );

        // places
        $height += $this->getRowHeight() * $nrOfPlaces;

        return $height;
    }

    /*public function draw()
    {
        $tournament = $this->getParent()->getTournament();
        $firstRound = $this->getParent()->getStructure()->getRootRound();
        $this->setQual( $firstRound );
        $nY = $this->drawHeader( "draaitabel per poule" );
        $nY = $this->draw( $firstRound, $nY );
    }*/

    /*protected function setQual( Round $parentRound )
    {
        foreach ($parentRound->getChildRounds() as $childRound) {
            $qualifyService = new QualifyService($childRound);
            $qualifyService->setQualifyRules();
            $this->setQual( $childRound );
        }
    }*/

    protected function getPouleName( Poule $poule )
    {
        $nameService = new NameService();
        return $nameService->getPouleName( $poule, true );
    }

    public function drawPouleHeader( Poule $poule, $nY )
    {
        $nrOfPlaces = $poule->getPlaces()->count();
        $versusColumnWidth = $this->versusColumnsWidth / $nrOfPlaces;
        $degrees = $this->getDegrees( $nrOfPlaces );
        $height = $this->getVersusHeight( $versusColumnWidth, $degrees );

        $nX = $this->getPageMargin();
        $nX = $this->drawCell( (new NameService())->getPouleName( $poule, true ), $nX, $nY, $this->nameColumnWidth, $height, ToernooiPdfPage::ALIGNCENTER, 'black' );

        $nVersus = 0;
        foreach( $poule->getPlaces() as $place ) {
            $nX = $this->drawCell((new NameService())->getPlaceFromName($place, true), $nX, $nY, $versusColumnWidth,
                $height, ToernooiPdfPage::ALIGNCENTER, 'black', $degrees);
            $nVersus++;
        }
        // draw pointsrectangle
        $nX = $this->drawCell( "punten", $nX, $nY, $this->pointsColumnWidth, $height, ToernooiPdfPage::ALIGNCENTER, 'black' );

        // draw rankrectangle
        $this->drawCell( "plek", $nX, $nY, $this->rankColumnWidth, $height, ToernooiPdfPage::ALIGNCENTER, 'black' );

        return $nY - $height;
    }

    public function draw( Poule $poule, $nY )
    {
        // draw first row
        $nY = $this->drawPouleHeader( $poule, $nY );

        $pouleState = $poule->getState();
        $competition = $this->getParent()->getTournament()->getCompetition();
        $rankingService = new RankingService($poule->getRound(), $competition->getRuleSet() );
        $rankingItems = $rankingService->getItemsForPoule( $poule );

        $nRowHeight = $this->getRowHeight();
        $nrOfPlaces = $poule->getPlaces()->count();
        $versusColumnWidth = $this->versusColumnsWidth / $nrOfPlaces;

        $nVersus = 1;
        foreach( $poule->getPlaces() as $place ) {
            $nX = $this->getPageMargin();
            $nX = $this->drawCell( (new NameService())->getPlaceFromName( $place, true ), $nX, $nY, $this->nameColumnWidth, $nRowHeight, ToernooiPdfPage::ALIGNLEFT, 'black' );
            $placeGames = $poule->getGames()->filter( function( $game ) use ($place) {
                return $game->isParticipating( $place );
            });
            // draw versus
            for( $placeNr = 1 ; $placeNr <= $nrOfPlaces ; $placeNr++ ) {
                if( $nVersus === $placeNr ) {
                    $this->setFillColor( new \Zend_Pdf_Color_Html( "lightgrey" ) );
                }
                $score = '';
                if( $pouleState !== State::Created ) {
                    $score = $this->getScore( $poule->getPlace($placeNr), $placeGames );
                }
                $nX = $this->drawCell( $score, $nX, $nY, $versusColumnWidth, $nRowHeight, ToernooiPdfPage::ALIGNLEFT, 'black' );
                if( $nVersus === $placeNr ) {
                    $this->setFillColor( new \Zend_Pdf_Color_Html( "white" ) );
                }
            }
            $nVersus++;

            // draw pointsrectangle
            $points = null;
            if( $pouleState !== State::Finished ) {
                $points = '?';
            }
            $nX = $this->drawCell( $points, $nX, $nY, $this->pointsColumnWidth, $nRowHeight, ToernooiPdfPage::ALIGNLEFT, 'black' );

            // draw rankrectangle
            $rank = null;
            if( $pouleState !== State::Finished ) {
                $rank = '?';
            }
            $this->drawCell( $rank, $nX, $nY, $this->rankColumnWidth, $nRowHeight, ToernooiPdfPage::ALIGNLEFT, 'black' );

            $nY -= $nRowHeight;
        }
        return $nY - $nRowHeight;
    }

    protected function getScore( Place $awayPlace, array $placeGames): string {
        $foundGames = array_filter( $placeGames, function( $game ) use ($awayPlace) {
            return $game->isParticipating( $awayPlace, Game::AWAY );
        });
        if( count($foundGames) !== 1 ) {
            return '';
        }
        return $this->getGameScore( reset($foundGames), false );
    }

    protected function getGameScore(Game $game, bool $reverse): string {
        $score = ' - ';
        if ($game->getState() !== State::Finished) {
            return $score;
        }
        $finalScore = $this->sportScoreConfigService->getFinal($game);
        if ($finalScore === null) {
            return $score;
        }
        if( $reverse === true ) {
            return $finalScore->getAway() . $score . $finalScore->getHome();
        }
        return $finalScore->getHome() . $score . $finalScore->getAway();
    }

    protected function getPoulePlaceNamesWidth( Poule $poule ) {
        $width = 0;
        foreach( $poule->getPlaces() as $place ) {
            $width += $this->getPlaceWidth( $place );
        }
        return $width;
    }


    public function getPlaceWidth( Place $place, int $nFontSize = null )
    {
        $key = $place->getId();
        if( $this->getParent()->hasTextWidth($key) ) {
            return $this->getParent()->getTextWidth();
        }
        $width = $this->getTextWidth( (new NameService())->getPlaceFromName($place, true));
        return $this->getParent()->setTextWidth($width);
    }

    public function getDegrees( int $nrOfPlaces ): int {
        if( $nrOfPlaces <= 3 ) {
            return 0;
        }
        if( $nrOfPlaces >= 6 ) {
            return 90;
        }
        return 45;
    }

    public function getVersusHeight( $versusColumnWidth, int $degrees ): float {
        if( $degrees === 0 ) {
            return $this->getRowHeight();
        }
        if( $degrees === 90 ) {
            return $versusColumnWidth * 2;
        }
        return (tan(deg2rad($degrees)) * $versusColumnWidth );
    }
}