<?php
/**
 * Created by PhpStorm.
 * User: coen
 * Date: 2-2-18
 * Time: 15:06
 */

namespace FCToernooi\Pdf\Document;

use \FCToernooi\Tournament;
use Voetbal\Structure\Service as StructureService;
use Voetbal\Planning\Service as PlanningService;
use Voetbal\Game;

class ScheduledGames extends \Zend_Pdf
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
     * @var array
     */
    protected $allRoundByNumber;
//    protected $m_nPageHeight;					// int
//    protected $m_nPageWidth;					// int
//    protected $m_nPoolUserHeight;				// int
//    protected $m_nPoolUserWidth;				// int
    protected $m_nHeaderHeight;					// int
    protected $m_nPageMargin;					// int
//    protected $m_nPoolUserMargin;				// int
//    protected $m_nGameNumberWidth;              // int
//    protected $m_nGameNumberYMargin;			// int
//    protected $m_nGameNumberXMargin;			// int
//    protected $m_nMinimalGameNumberXMargin;		// int
//    protected $m_oNow;							// int

    /**
     * Constructs the class
     *
     * @throws Nothing
     * @return An instance of the class
     */
    public function __construct(
        Tournament $tournament,
        StructureService $structureService,
        PlanningService $planningService
    )
    {
        parent::__construct();
        $this->tournament = $tournament;
        $this->structureService = $structureService;
        $this->planningService = $planningService;
        $this->allRoundByNumber = $this->structureService->getAllRoundsByNumber( $tournament->getCompetition() );

//        $this->m_nPoolUserRowHeight = 20;
//        $this->m_nPoolUserWidth = 600;
//        $this->m_nPoolUserHeight = $this->m_nPoolUserRowHeight * 9;
//        $this->m_nPoolUserMarginVertical = 25;
//        $this->m_nPoolUserMarginHorizontal = 40;
//        $this->m_nGameNumberRowHeight = 23;
//        $this->m_nGameNumberYMargin = 14;
//        $this->m_nMinimalGameNumberXMargin = 100;
//        $this->m_oNow = Agenda_Factory::createDateTime();
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

//    public function getFontHeightPoolUsers()
//    {
//        return 14;
//    }
//
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
//
//    public function getPoolUserRowHeight()
//    {
//        return $this->m_nPoolUserRowHeight;
//    }
//
//    public function getPoolUserHeight()
//    {
//        return $this->m_nPoolUserHeight;
//    }
//
//    public function getPoolUserWidth()
//    {
//        return $this->m_nPoolUserWidth;
//    }
//
//    public function getPoolUserMarginHorizontal()
//    {
//        return $this->m_nPoolUserMarginHorizontal;
//    }
//
//    public function getPoolUserMarginVertical()
//    {
//        return $this->m_nPoolUserMarginVertical;
//    }
//
//    public function getGameNumberRowHeight()
//    {
//        return $this->m_nGameNumberRowHeight;
//    }
//
//    public function getGameNumberYMargin()
//    {
//        return $this->m_nGameNumberYMargin;
//    }
//
//    public function getGameNumberXMargin()
//    {
//        return $this->m_nMinimalGameNumberXMargin;
//    }
//
//    public function putGameNumberXMargin( $nPageWidth )
//    {
//        /*$nNettoPageWidth = ( $nPageWidth - ( 2 * $this->getPageMargin() ) );
//        $nNrOfGameNumbersPerLine = (int) floor( $nNettoPageWidth / ( $this->getGameNumberWidth() + $this->m_nMinimalGameNumberXMargin ) );
//        $nTotalXMargin = $nNettoPageWidth % $this->getGameNumberWidth();
//        $this->m_nGameNumberXMargin = (int) floor( $nTotalXMargin / ( $nNrOfGameNumbersPerLine - 1 ) );*/
//    }
//
//    public function getGameNumberHeight( $oGames )
//    {
//        return ( $this->getGameNumberRowHeight() + ( $oGames->count() * $this->getGameNumberRowHeight() ) );
//    }
//
//    public function getGameNumberWidth()
//    {
//        if ( $this->m_nGameNumberWidth === null ) {
//            throw new Exception("wedstrijdnummerbreedte moet eerst gezet zijn, alvorens deze kan worden opgehaald");
//        }
//        return $this->m_nGameNumberWidth;
//    }
//
//    public function putGameNumberWidth( $nGameNumberWidth )
//    {
//        $this->m_nGameNumberWidth = $nGameNumberWidth;
//    }
//
//
//    // 25 + 41 + 35 + 102 + 10 + 102 = 315
//    public function getGameNumberDayWidth()
//    {
//        return 25 / 315 * $this->m_nGameNumberWidth;
//    }
//
//    public function getGameNumberDateWidth()
//    {
//        return 41 / 315 * $this->m_nGameNumberWidth;
//    }
//
//    public function getGameNumberTimeWidth()
//    {
//        return 35 / 315 * $this->m_nGameNumberWidth;
//    }
//
//    public function getGameNumberTeamWidth()
//    {
//        return 102 / 315 * $this->m_nGameNumberWidth;
//    }
//
//    public function getGameNumberVSWidth()
//    {
//        return 10 / 315 * $this->m_nGameNumberWidth;
//    }

//    public function getPageMargin()
//    {
//        if ( $this->m_nPageMargin === null )
//            $this->putPageProperties();
//        return $this->m_nPageMargin;
//    }
//
//    public function getHeaderHeight()
//    {
//        if ( $this->m_nHeaderHeight === null )
//            $this->putPageProperties();
//        return $this->m_nHeaderHeight;
//    }
//
//    private function putPageProperties()
//    {
//        if ( $this->m_nPageMargin === null and $this->m_nHeaderHeight === null ) {
//            $oPage = new FCToernooi\Pdf\Page\ScheduledGames( \Zend\Pdf\Page::SIZE_A4 );
//            $this->m_nPageMargin = $oPage->getPageMargin();
//            $this->m_nHeaderHeight = $oPage->getHeaderHeight();
//        }
//    }

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
        $sFontDir = __DIR__ . "/../../../fonts/";
        if ( $bBold === false and $bItalic === false )
            return \Zend_Pdf_Font::fontWithPath( $sFontDir . "times.ttf" );
        if ( $bBold === true and $bItalic === false )
            return \Zend_Pdf_Font::fontWithPath( $sFontDir . "timesbd.ttf" );
        else if ( $bBold === false and $bItalic === true )
            return \Zend_Pdf_Font::fontWithPath( $sFontDir . "timesi.ttf" );
        else if ( $bBold === true and $bItalic === true )
            return \Zend_Pdf_Font::fontWithPath( $sFontDir . "timesbi.ttf" );
    }

    protected function createSchedulePage( Game $gameA = null, Game $gameB = null)
    {
        $page = new \FCToernooi\Pdf\Page\ScheduledGames( \Zend_Pdf_Page::SIZE_A4, $gameA, $gameB );
        $page->setFont( $this->getFont(), $this->getFontHeight() );
        $page->putParent( $this );
        $this->pages[] = $page;
        return $page;
    }

    protected function fillContent()
    {
        $games = $this->getScheduledGames( $this->tournament->getCompetition()->getFirstRound() );
        if( count( $games ) === 0 ) {
            $page = $this->createSchedulePage( null, null );
            $page->setFillColor( new \Zend_Pdf_Color_Html( 'black' ) );
            $page->setLineColor( new \Zend_Pdf_Color_Html( 'black' ) );
            $page->drawText('er zijn geen geplande wedstrijden waarbij de deelnemers al bekend zijn', $page->getPageMargin(), $page->getHeight() - ( $page->getPageMargin() + $this->getFontHeight() ) );
            return;
        }

        while( count( $games ) > 0 ) {
            $page = $this->createSchedulePage( array_shift( $games ), array_shift( $games ) );
            $page->draw();
        }



//        $oPoolUsers = $this->m_oPool->getUsers();
//        {
//            $oPoolUsersWithoutBetFormation = SuperElf_Pool_User_Factory::createObjects();
//            foreach ( $oPoolUsers as $oPoolUser )
//            {
//                $oBetFormation = $oPoolUser->getBetFormation();
//                if ( $oBetFormation === null )
//                    $oPoolUsersWithoutBetFormation->add( $oPoolUser );
//            }
//        }
//        $oPoolUsers->removeCollection( $oPoolUsersWithoutBetFormation );
//
//        $nHorizontalPoolUsers = 1; $nWidth = null; $nHeight = null;
//        foreach ( $oPoolUsers as $oPoolUser )
//        {
//            $nWidth = 2 * $this->getPageMargin() + ( $nHorizontalPoolUsers * $this->getPoolUserWidth() ) + ( ( $nHorizontalPoolUsers - 1 ) * $this->getPoolUserMarginHorizontal() );
//            $nRows = (int) ceil( $oPoolUsers->count() / $nHorizontalPoolUsers );
//            $nHeight = $this->getHeaderHeight() + $this->getPoolUserMarginVertical() + ( 2 * $this->getPageMargin() ) + ( $nRows * $this->getPoolUserHeight() ) + ( ( $nRows - 1 ) * $this->getPoolUserMarginVertical() );
//
//            $nMaxHeight = $nWidth * 210/297;
//            if( $nHeight < $nMaxHeight )
//                break;
//            $nHorizontalPoolUsers++;
//        }
//
//        $oPage = $this->createNewPage( $nWidth, $nHeight );
//        $nY = $oPage->drawHeader( $this->m_oPool->getCompetition(), $this->m_oPool );
//        $nY -= $this->getPoolUserMarginVertical();
//        $nX = $this->getPageMargin();
//        foreach( $oPoolUsers as $oPoolUser )
//        {
//            $nX = $oPage->draw( $oPoolUser, $nX, $nY );
//            if ( $nX > ( ( $nWidth - $this->getPageMargin() ) - $this->getPoolUserWidth() ) )
//            {
//                $nX = $this->getPageMargin();
//                $nY -= ( $this->getPoolUserHeight() + $this->getPoolUserMarginVertical() );
//            }
//        }

        /***************************** START SCHEDULE PAGE ****************************/

//        $oPoolTransferPeriods = $this->m_oPool->getPeriods( SuperElf_Pool_Period::TRANSFER );
//        $oEndDateTime = $this->m_oPool->getEndDateTime();
//        foreach( $oPoolTransferPeriods as $oPoolTransferPeriod ) {
//            if (  $this->getNow() < $oPoolTransferPeriod->getStartDateTime() ) {
//                $oEndDateTime = $oPoolTransferPeriod->getStartDateTime();
//                break;
//            }
//        }
//        if ( $this->getNow() > $oEndDateTime ) // pool ended
//            return;
//
//        $oPoule = $this->m_oPool->getCompetition()->getRounds()->first()->getPoules()->first();
//        $oGameNrRange = Voetbal_Game_Factory::getNumberRange( $oPoule, Voetbal_Factory::STATE_SCHEDULED, Agenda_Factory::createDateTime(), $oEndDateTime );
//        if ( $oGameNrRange->Start === null )
//            return;
//
//        $this->putGameNumberXMargin( $nWidth );
//        $oPage = $this->createSchedulePage( $nWidth, $nHeight );
//
//        $nY = $oPage->drawHeader( $this->m_oPool->getCompetition(), $this->m_oPool );
//
//        $nY -= $this->getGameNumberYMargin();
//        $nX = $this->getPageMargin();
//
//        // $nWidth, $nHeight
//        // kijk hoeveel gamenumbers horizontaal kunnen!!!
//        // verhouding van pagina is
//        // $nAspectRatio = $nWidth / $nHeight;
//
//        $nNrOfGameNumbers = $oGameNrRange->End - $oGameNrRange->Start;
//        $nNrOfGamesPerGameNumber = (int) floor( $oPoule->getPlaces()->count() / 2 );
//        // 315 zou bepaald moeten worden samen met de hoggte obv grootte pagina en aantal gamenumbers!!
//        $this->putGameNumberWidth( 500 );
//
//        for( $nGameNr = $oGameNrRange->Start ; $nGameNr <= $oGameNrRange->End ; $nGameNr++ )
//        {
//            $oGames = $this->getScheduledGames( $nGameNr, $oEndDateTime );
//
//            // while( $oGames->count() > 0 and $nY >= ( $this->getGameNumberHeight( $oGames ) + $this->getPageMargin() ) )
//            // {
//            $nX = $oPage->draw( $nGameNr, $oGames, $nX, $nY );
//            if ( $nX > ( ( $nWidth - $this->getPageMargin() ) - $this->getGameNumberWidth() ) )
//            {
//                $nX = $this->getPageMargin();
//                $nY -= ( $this->getGameNumberHeight( $oGames ) + $this->getGameNumberYMargin() );
//            }
//
//            // $oGames = $this->getScheduledGames( ++$nCurrentGameNumber, $oEndDateTime );
//            // }
//        }
// die();
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
        return array_filter( $games, function( $game ) {
            return $game->getHomePoulePlace()->getTeam() !== null && $game->getAwayPoulePlace()->getTeam() !== null;
        });
    }
}