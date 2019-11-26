<?php
/**
 * Created by PhpStorm.
 * User: coen
 * Date: 2-2-18
 * Time: 15:03
 */

namespace App\Export\Pdf\Page;

use App\Export\Pdf\Page as ToernooiPdfPage;
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
        $subHeader = $this->getParent()->getNameService()->getRoundNumberName( $roundNumber);
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

    public function drawPouleHeader( Poule $poule, $nY )
    {
        $nrOfPlaces = $poule->getPlaces()->count();
        $versusColumnWidth = $this->versusColumnsWidth / $nrOfPlaces;
        $degrees = $this->getDegrees( $nrOfPlaces );
        $height = $this->getVersusHeight( $versusColumnWidth, $degrees );

        $nX = $this->getPageMargin();
        $nX = $this->drawCell( $this->getParent()->getNameService()->getPouleName( $poule, true ), $nX, $nY, $this->nameColumnWidth, $height, ToernooiPdfPage::ALIGNCENTER, 'black' );

        $nVersus = 0;
        foreach( $poule->getPlaces() as $place ) {
            $nX = $this->drawCell($this->getParent()->getNameService()->getPlaceFromName($place, true), $nX, $nY, $versusColumnWidth,
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
        $rankingItems = null;
        if( $pouleState === State::Finished ) {
            $rankingService = new RankingService($poule->getRound(), $competition->getRuleSet() );
            $rankingItems = $rankingService->getItemsForPoule( $poule );
        }

        $nRowHeight = $this->getRowHeight();
        $nrOfPlaces = $poule->getPlaces()->count();
        $versusColumnWidth = $this->versusColumnsWidth / $nrOfPlaces;

        foreach( $poule->getPlaces() as $place ) {
            $nX = $this->getPageMargin();
            $nX = $this->drawCell( $this->getParent()->getNameService()->getPlaceFromName( $place, true ), $nX, $nY, $this->nameColumnWidth, $nRowHeight, ToernooiPdfPage::ALIGNLEFT, 'black' );
            $placeGames = $poule->getGames()->filter( function( Game $game ) use ($place) {
                return $game->isParticipating( $place );
            })->toArray();
            // draw versus
            for( $placeNr = 1 ; $placeNr <= $nrOfPlaces ; $placeNr++ ) {
                if( $poule->getPlace($placeNr) === $place ) {
                    $this->setFillColor( new \Zend_Pdf_Color_Html( "lightgrey" ) );
                }
                $score = '';
                if( $pouleState !== State::Created ) {
                    $score = $this->getScore( $place, $poule->getPlace($placeNr), $placeGames );
                }
                $nX = $this->drawCell( $score, $nX, $nY, $versusColumnWidth, $nRowHeight, ToernooiPdfPage::ALIGNCENTER, 'black' );
                if( $poule->getPlace($placeNr) === $place ) {
                    $this->setFillColor( new \Zend_Pdf_Color_Html( "white" ) );
                }
            }

            $rankingItem = null;
            if( $rankingItems !== null ) {
                $arrFoundRankingItems = array_filter( $rankingItems, function( $rankingItem ) use ($place ) {
                    return $rankingItem->getPlace() === $place;
                });
                $rankingItem = reset( $arrFoundRankingItems );
            }

            // draw pointsrectangle
            $points = '?';
            if( $rankingItem !== null ) {
                $points = '' . $rankingItem->getUnranked()->getPoints();
            }
            $nX = $this->drawCell( $points, $nX, $nY, $this->pointsColumnWidth, $nRowHeight, ToernooiPdfPage::ALIGNRIGHT, 'black' );

            // draw rankrectangle
            $rank = '?';
            if( $rankingItem !== null ) {
                $rank = '' . $rankingItem->getUniqueRank();
            }
            $this->drawCell( $rank, $nX, $nY, $this->rankColumnWidth, $nRowHeight, ToernooiPdfPage::ALIGNRIGHT, 'black' );

            $nY -= $nRowHeight;
        }
        return $nY - $nRowHeight;
    }

    protected function getScore( Place $homePlace, Place $awayPlace, array $placeGames): string {
        $foundHomeGames = array_filter( $placeGames, function( $game ) use ($homePlace, $awayPlace) {
            return $game->isParticipating( $awayPlace, Game::AWAY ) && $game->isParticipating( $homePlace, Game::HOME );
        });
        if( count($foundHomeGames) > 1 ) {
            return '';
        }
        if( count($foundHomeGames) === 1 ) {
            return $this->getGameScore( reset($foundHomeGames), false );
        }
        $foundAwayGames = array_filter( $placeGames, function( $game ) use ($homePlace, $awayPlace) {
            return $game->isParticipating( $homePlace, Game::AWAY ) && $game->isParticipating( $awayPlace, Game::HOME );
        });
        if( count($foundAwayGames) !== 1 ) {
            return '';
        }
        return $this->getGameScore( reset($foundAwayGames), true );
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
        $width = $this->getTextWidth( $this->getParent()->getNameService()->getPlaceFromName($place, true));
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