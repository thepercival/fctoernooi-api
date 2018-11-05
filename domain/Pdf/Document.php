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
        if( $this->config->getGamenotes() ) {
            $games = $this->getScheduledGames( $this->tournament->getCompetition()->getFirstRound() );
            while( count( $games ) > 0 ) {
                $page = $this->createPageGamenotes( array_shift( $games ), array_shift( $games ) );
                $page->draw();
            }
        }
        if( $this->config->getStructure() ) {
            // $page = $this->createPageStructure( array_shift( $games ), array_shift( $games ) );
            // $page->draw();
        }
        if( $this->config->getPoules() ) {

        }
        if( $this->config->getPlanning() ) {

        }
        if( $this->config->getRules() ) {

        }
        if( $this->config->getGamesperfield() ) {

        }
    }

    protected function createPageGamenotes( Game $gameA = null, Game $gameB = null)
    {
        $page = new \FCToernooi\Pdf\Page\Gamenotes( \Zend_Pdf_Page::SIZE_A4, $gameA, $gameB );
        $page->setFont( $this->getFont(), $this->getFontHeight() );
        $page->putParent( $this );
        $this->pages[] = $page;
        return $page;
    }

    protected function createPageStructure( Game $gameA = null, Game $gameB = null)
    {
        $page = new \FCToernooi\Pdf\Page\Structure( \Zend_Pdf_Page::SIZE_A4, $gameA, $gameB );
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
}