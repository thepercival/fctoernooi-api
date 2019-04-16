<?php
/**
 * Created by PhpStorm.
 * User: coen
 * Date: 2-2-18
 * Time: 15:06
 */

namespace FCToernooi\Pdf;

use \FCToernooi\Tournament;
use Voetbal\Qualify\Service as QualifyService;
use Voetbal\Structure;
use Voetbal\Planning\Service as PlanningService;
use Voetbal\Game;
use Voetbal\Round;
use Voetbal\Round\Number as RoundNumber;
use FCToernooi\Pdf\Page\PoulePivotTables as PagePoules;
use FCToernooi\Pdf\Page\Planning as PagePlanning;

class Document extends \Zend_Pdf
{
    /**
     * @var Tournament
     */
    protected $tournament;
    /**
     * @var Structure
     */
    protected $structure;
    /**
     * @var PlanningService
     */
    protected $planningService;
    /**
     * @var TournamentConfig
     */
    protected $config;
    /**
     * @var array
     */
    protected $allRoundByNumber;
    /**
     * @var int
     */
    protected $m_nHeaderHeight;
    /**
     * @var int
     */
    protected $m_nPageMargin;
    /**
     * @var array
     */
    protected $widthText = [];
    /**
     * @var bool
     */
    protected $areSelfRefereesAssigned;

    public function __construct(
        Tournament $tournament,
        Structure $structure,
        TournamentConfig $config
    )
    {
        parent::__construct();
        $this->tournament = $tournament;
        $this->structure = $structure;
        $this->planningService = new PlanningService($tournament->getCompetition());
        $this->config = $config;
    }

    /**
     * @return Structure
     */
    public function getStructure()
    {
        return $this->structure;
    }

    /**
     * @return PlanningService
     */
    public function getPlanningService()
    {
        return $this->planningService;
    }

    public function getRoundsByRumber( int $roundNr )
    {
        return $this->allRoundByNumber[$roundNr];
    }

    public function getFontHeight()
    {
        return 14;
    }

    public function getFontHeightSubHeader() {
        return 16;
    }

    /**
     * @return Tournament
     */
    public function getTournament()
    {
        return $this->tournament;
    }

    public function render( $newSegmentOnly = false, $outputStream = null )
    {
        $this->fillContent();
        return parent::render( $newSegmentOnly, $outputStream );
    }

//    protected function createNewPage( $nPageWidth, $nPageHeight )
//    {
//        $oPage = new SuperElf_Pdf_Page_PoolTotal( $nPageWidth, $nPageHeight );
//        $oFont = SuperElf_Pdf_Factory::getFont();
//        $oPage->setFont( $oFont, $this->getFontHeightPoolUsers() );
//        $oPage->putParent( $this );
//        $this->pages[] = $oPage;
//        return $oPage;
//    }
//

    public static function getFont( $bBold = false, $bItalic = false )
    {
        $sFontDir = __DIR__ . "/../../fonts/";
        if ( $bBold === false and $bItalic === false )
            return \Zend_Pdf_Font::fontWithPath( $sFontDir . "times.ttf" );
        if ( $bBold === true and $bItalic === false )
            return \Zend_Pdf_Font::fontWithPath( $sFontDir . "timesbd.ttf" );
        else if ( $bBold === false and $bItalic === true )
            return \Zend_Pdf_Font::fontWithPath( $sFontDir . "timesi.ttf" );
        else if ( $bBold === true and $bItalic === true )
            return \Zend_Pdf_Font::fontWithPath( $sFontDir . "timesbi.ttf" );
    }

    protected function fillContent()
    {
        if( $this->config->getStructure() ) {
            $page = $this->createPageStructure();
            $page->draw();
        }
        if( $this->config->getPoulePivotTables() ) {
            list( $page, $nY ) = $this->createPagePoulePivotTables();
            $this->drawPoulePivotTables( $this->structure->getFirstRoundNumber(), $page, $nY );
        }
        if( $this->config->getPlanning() ) {
            list( $page, $nY ) = $this->createPagePlanning("wedstrijden");
            $this->drawPlanning( $this->structure->getFirstRoundNumber(), $page, $nY );
        }
        if( $this->config->getRules() ) {

        }
        if( $this->config->getGamenotes() ) {
            $this->drawGamenotes();
        }
        if( $this->config->getGamesperfield() ) {
            $this->drawPlanningPerField( $this->structure->getFirstRoundNumber() );
        }
        if( $this->config->getQRCode() ) {
            $page = $this->createPageQRCode();
            $page->draw();
        }
    }

    protected function drawPlanning( RoundNumber $roundNumber, PagePlanning $page = null, int $nY = null )
    {
        $nY = $page->drawRoundNumberHeader($roundNumber, $nY);
        $games = $page->getGames($roundNumber);
        if( count($games) > 0 ) {
            $nY = $page->drawGamesHeader($roundNumber, $nY);
        }
        $games = $this->getPlanningService()->getGamesForRoundNumber($roundNumber, Game::ORDER_RESOURCEBATCH);
        foreach ($games as $game) {
            $gameHeight = $page->getGameHeight($game);
            if ($nY - $gameHeight < $page->getPageMargin() ) {
                list($page, $nY) = $this->createPagePlanning("wedstrijden");
                $nY = $page->drawGamesHeader($roundNumber, $nY);
            }
            $nY = $page->drawGame($game, $nY);
        }

        if( $roundNumber->hasNext() ) {
            $nY -= 20;
            $this->drawPlanning( $roundNumber->getNext(), $page, $nY );
        }
    }

    protected function drawPlanningPerField( RoundNumber $roundNumber, PagePlanning $page = null, int $nY = null )
    {
        $fields = $this->getTournament()->getCompetition()->getFields();
        if( $fields->count() === 0 ) {
            return;
        }
        foreach( $fields as $field ) {
            list( $page, $nY ) = $this->createPagePlanning("veld " . $field->getName() );
            $page->setFieldFilter( $field );
            $this->drawPlanningPerFieldHelper( $roundNumber, $page , $nY );
        }
    }

    protected function drawPlanningPerFieldHelper( RoundNumber $roundNumber, PagePlanning $page = null, int $nY = null )
    {
        $nY = $page->drawRoundNumberHeader($roundNumber, $nY);
        $games = $page->getGames($roundNumber);
        if( count($games) > 0 ) {
            $nY = $page->drawGamesHeader($roundNumber, $nY);
        }
        $games = $this->getPlanningService()->getGamesForRoundNumber($roundNumber, Game::ORDER_RESOURCEBATCH);
        foreach ($games as $game) {
            $gameHeight = $page->getGameHeight($game);
            if ($nY - $gameHeight < $page->getPageMargin() ) {
                $field = $page->getFieldFilter();
                list($page, $nY) = $this->createPagePlanning("veld " . $field->getName());
                $page->setFieldFilter( $field );
                $nY = $page->drawGamesHeader($roundNumber, $nY);
            }
            $nY = $page->drawGame($game, $nY);
        }

        if( $roundNumber->hasNext() ) {
            $nY -= 20;
            $this->drawPlanningPerFieldHelper( $roundNumber->getNext(), $page, $nY );
        }
    }

    protected function drawGamenotes()
    {
        $games = $this->getScheduledGames( $this->structure->getRootRound() );
        while( count( $games ) > 0 ) {
            $page = $this->createPageGamenotes( array_shift( $games ), array_shift( $games ) );
            $page->draw();
        }
    }

    protected function drawPoulePivotTables( RoundNumber $roundNumber, PagePoules $page = null, int $nY = null )
    {
        if( $roundNumber->needsRanking() ) {
            $nY = $page->drawRoundNumberHeader($roundNumber, $nY);
            foreach ($roundNumber->getRounds() as $round) {
                foreach ($round->getPoules() as $poule) {
                    if( !$poule->needsRanking() ) {
                        continue;
                    }
                    $pouleHeight = $page->getPouleHeight($poule);
                    if ($nY - $pouleHeight < $page->getPageMargin() ) {
                        list($page, $nY) = $this->createPagePoulePivotTables();
                    }
                    $nY = $page->draw($poule, $nY);
                }
            }
        }
        if( $roundNumber->hasNext() ) {
            $this->drawPoulePivotTables( $roundNumber->getNext(), $page, $nY );
        }
    }

    protected function createPageStructure()
    {
        $page = new Page\Structure( \Zend_Pdf_Page::SIZE_A4 );
        $page->setFont( $this->getFont(), $this->getFontHeight() );
        $page->putParent( $this );
        $this->pages[] = $page;
        return $page;
    }

    protected function createPagePlanning( string $title )
    {
        $selfRefereesAssigned = $this->areSelfRefereesAssigned();
        $page = new PagePlanning( $selfRefereesAssigned ? \Zend_Pdf_Page::SIZE_A4_LANDSCAPE : \Zend_Pdf_Page::SIZE_A4 );
        $page->setSelfRefereesAssigned($selfRefereesAssigned);
        $page->setRefereesAssigned($this->areRefereesAssigned());
        $page->setFont( $this->getFont(), $this->getFontHeight() );
        $page->putParent( $this );
        $this->pages[] = $page;
        $nY = $page->drawHeader( $title );
        return array( $page, $nY );
    }

    protected function createPageGamenotes( Game $gameA = null, Game $gameB = null)
    {
        $page = new Page\Gamenotes( \Zend_Pdf_Page::SIZE_A4, $gameA, $gameB );
        $page->setFont( $this->getFont(), $this->getFontHeight() );
        $page->putParent( $this );
        $this->pages[] = $page;
        return $page;
    }

    protected function createPagePoulePivotTables()
    {
        $page = new PagePoules( \Zend_Pdf_Page::SIZE_A4 );
        $page->setFont( $this->getFont(), $this->getFontHeight() );
        $page->putParent( $this );
        $this->pages[] = $page;
        $nY = $page->drawHeader( "pouledraaitabel" );
        return array( $page, $nY );
    }

    protected function createPageQRCode()
    {
        $page = new Page\QRCode( \Zend_Pdf_Page::SIZE_A4 );
        $page->setFont( $this->getFont(), $this->getFontHeight() );
        $page->putParent( $this );
        $this->pages[] = $page;
        return $page;
    }

    protected function areSelfRefereesAssigned(): bool {
        if( $this->areSelfRefereesAssigned !== null ){
            return $this->areSelfRefereesAssigned;
        };
        $hasSelfRefereeHelper = function( RoundNumber $roundNumber ) use ( &$hasSelfRefereeHelper ): bool {
            if( $roundNumber->getConfig()->getSelfReferee() ) {
                $games = $this->planningService->getGamesForRoundNumber($roundNumber, Game::ORDER_RESOURCEBATCH );
                if( count( array_filter( $games, function( $game ) {
                        return $game->getRefereePoulePlace() !== null;
                    } ) ) > 0 ) {
                    return true;
                }
            }
            if( $roundNumber->hasNext() === false ) {
                return false;
            }
            return $hasSelfRefereeHelper( $roundNumber->getNext() );
        };
        $this->areSelfRefereesAssigned = $hasSelfRefereeHelper( $this->structure->getFirstRoundNumber() );
        return $this->areSelfRefereesAssigned;
    }

    protected function areRefereesAssigned() {
        return $this->tournament->getCompetition()->getReferees()->count() > 0 || $this->areSelfRefereesAssigned();
    }

    /**
     * @param Round $round
     * @param array $games
     * @return array
     */
    protected function getScheduledGames( Round $round, $games = [] ): array
    {
        $games = array_merge( $games, $round->getGamesWithState(Game::STATE_CREATED));
        foreach( $round->getChildRounds() as $childRound ) {
            $games = $this->getScheduledGames( $childRound, $games);
        }
        return $games;
    }

    public function hasTextWidth( string $key ) {
        return array_key_exists( $key, $this->widthText );
    }

    public function getTextWidth( string $key) {
        return $this->widthText[$key];
    }

    public function setTextWidth( string $key, $value ) {
        $this->widthText[$key] = $value;
        return $value;
    }
}