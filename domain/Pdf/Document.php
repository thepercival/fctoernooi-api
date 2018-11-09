<?php
/**
 * Created by PhpStorm.
 * User: coen
 * Date: 2-2-18
 * Time: 15:06
 */

namespace FCToernooi\Pdf;

use \FCToernooi\Tournament;
use \FCToernooi\Pdf\TournamentConfig;
use Voetbal\Structure\Service as StructureService;
use Voetbal\Planning\Service as PlanningService;
use Voetbal\Game;
use Voetbal\Round;
use FCToernooi\Pdf\Page\Poules as PagePoules;

class Document extends \Zend_Pdf
{
    /**
     * @var Tournament
     */
    protected $tournament;
    /**
     * @var StructureService
     */
    protected $structureService;
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
    protected $m_nHeaderHeight;					// int
    protected $m_nPageMargin;					// int

    /**
     * Constructs the class
     *
     * @throws Nothing
     * @return An instance of the class
     */
    public function __construct(
        Tournament $tournament,
        StructureService $structureService,
        PlanningService $planningService,
        TournamentConfig $config
    )
    {
        parent::__construct();
        $this->tournament = $tournament;
        $this->structureService = $structureService;
        $this->planningService = $planningService;
        $this->config = $config;
        $this->allRoundByNumber = $this->structureService->getAllRoundsByNumber( $tournament->getCompetition() );
    }

    /**
     * @return StructureService
     */
    public function getStructureService()
    {
        return $this->structureService;
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
        if( $this->config->getPoules() ) {
            $this->drawPoules( $this->tournament->getCompetition()->getFirstRound());
        }
        if( $this->config->getPlanning() ) {
            $page = $this->createPagePlanning();
            $nY = $page->drawHeader( "wedstrijden" );
            $page->draw( $this->tournament->getCompetition()->getFirstRound(), $nY );
        }
        if( $this->config->getRules() ) {

        }
        if( $this->config->getGamenotes() ) {
            $this->drawGamenotes();
        }
        if( $this->config->getGamesperfield() ) {
            $page = $this->createPageGamesPerField();
            $nY = $page->drawHeader( "wedstrijden per veld" );
            $page->draw( $this->tournament->getCompetition()->getFirstRound(), $nY );
        }
    }

    protected function drawGamenotes()
    {
        $games = $this->getScheduledGames( $this->tournament->getCompetition()->getFirstRound() );
        while( count( $games ) > 0 ) {
            $page = $this->createPageGamenotes( array_shift( $games ), array_shift( $games ) );
            $page->draw();
        }
    }

    protected function drawPoules( Round $round, PagePoules $page = null, int $nY = null )
    {
        foreach( $round->getPoules() as $poule ) {
            if (!$poule->needsRanking()) {
                continue;
            }
            if ($poule->getState() === Game::STATE_PLAYED) {
                continue;
            }
            $pageCreated = false;
            if( $page === null ) {
                $page = $this->createPagePoules();
                $pageCreated = true;
                $nY = $page->drawHeader( "draaitabel per poule" );
            }

            $pouleHeight = $page->getPouleHeight( $poule );
            if( !$pageCreated and $nY - $pouleHeight < $page->getPageMargin() ) {
                $page = $this->createPagePoules();
                $nY = $page->drawHeader( "draaitabel per poule" );
            }
            $nY = $page->draw( $poule, $nY );
        }

        foreach( $round->getChildRounds() as $childRound ) {
            $this->drawPoules( $childRound, $page, $nY );
        }
    }

    protected function createPageStructure()
    {
        $page = new \FCToernooi\Pdf\Page\Structure( \Zend_Pdf_Page::SIZE_A4 );
        $page->setFont( $this->getFont(), $this->getFontHeight() );
        $page->putParent( $this );
        $this->pages[] = $page;
        return $page;
    }

    protected function createPagePlanning()
    {
        $page = new \FCToernooi\Pdf\Page\Planning( \Zend_Pdf_Page::SIZE_A4 );
        $page->setFont( $this->getFont(), $this->getFontHeight() );
        $page->putParent( $this );
        $this->pages[] = $page;
        return $page;
    }

    protected function createPageGamesPerField()
    {
        $page = new \FCToernooi\Pdf\Page\GamesPerField( \Zend_Pdf_Page::SIZE_A4 );
        $page->setFont( $this->getFont(), $this->getFontHeight() );
        $page->putParent( $this );
        $this->pages[] = $page;
        return $page;
    }



    protected function createPageGamenotes( Game $gameA = null, Game $gameB = null)
    {
        $page = new \FCToernooi\Pdf\Page\Gamenotes( \Zend_Pdf_Page::SIZE_A4, $gameA, $gameB );
        $page->setFont( $this->getFont(), $this->getFontHeight() );
        $page->putParent( $this );
        $this->pages[] = $page;
        return $page;
    }

    protected function createPagePoules()
    {
        $page = new \FCToernooi\Pdf\Page\Poules( \Zend_Pdf_Page::SIZE_A4 );
        $page->setFont( $this->getFont(), $this->getFontHeight() );
        $page->putParent( $this );
        $this->pages[] = $page;
        return $page;
    }




    /**
     * @param Tournament $tournament
     * @return array
     */
    protected function getScheduledGames( $round, $games = [] )
    {
        $games = array_merge( $games, $round->getGamesWithState(Game::STATE_CREATED));
        foreach( $round->getChildRounds() as $childRound ) {
            $games = $this->getScheduledGames( $childRound, $games);
        }
        return $games;
    }

    /*public function getName( PoulePlace $poulePlace )
    {
        $nameService = $this->getParent()->getStructureService()->getNameService();
        if( $poulePlace->getTeam() !== null ) {
            return $poulePlace->getTeam()->getName();
        }
        return $nameService->getPoulePlaceName( $poulePlace );
    }*/
}