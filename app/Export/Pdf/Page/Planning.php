<?php
/**
 * Created by PhpStorm.
 * User: coen
 * Date: 9-11-18
 * Time: 19:28
 */

namespace App\Export\Pdf\Page;

use App\Export\Pdf\Page as ToernooiPdfPage;
use League\Period\Period;
use Voetbal\Game;
use Voetbal\Round;
use Voetbal\Round\Number as RoundNumber;
use Voetbal\NameService;
use Voetbal\Sport\ScoreConfig\Service as SportScoreConfigService;

class Planning extends ToernooiPdfPage
{
    /**
     * @var SportScoreConfigService
     */
    protected $sportScoreConfigService;

    use GamesTrait;
    /**
     * @var bool
     */
    protected $drewbreak;

    /**
     * @var Period
     */
    protected $tournamentBreak;

    /**
     * @var mixed
     */
    protected $gameFilter;
    /**
     * @var string
     */
    protected $title;
    /*protected $maxPoulesPerLine;
    protected $placeWidthStructure;
    protected $pouleMarginStructure;
    */protected $rowHeight;

    public function __construct( $param1 )
    {
        parent::__construct($param1);
        $this->drewbreak = false;
        $this->setLineWidth(0.5);
        $this->sportScoreConfigService = new SportScoreConfigService();
        /*$this->maxPoulesPerLine = 3;
        $this->placeWidthStructure = 30;
        $this->pouleMarginStructure = 10;*/
/*
        <colgroup>
        <col *ngIf="aRoundNeedsRanking(roundsByNumber)">
        <col *ngIf="!aRoundNeedsRanking(roundsByNumber)">
        <col *ngIf="planningService.canCalculateStartDateTime(roundNumber)">
        <col>
        <col class="width-25">
        <col>
        <col class="width-25">
        <col *ngIf="hasReferees()" class="d-none d-sm-table-cell">
      </colgroup>*/
    }

    public function setTournamentBreak(Period $break)
    {
        $this->tournamentBreak = $break;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title)
    {
        $this->title = $title;
    }

    public function getPageMargin()
    {
        return 20;
    }

    public function getHeaderHeight()
    {
        return 0;
    }

    public function getGameFilter()
    {
        return $this->gameFilter;
    }

    public function setGameFilter($gameFilter)
    {
        $this->gameFilter = $gameFilter;
    }

    public function drawBreakBeforeGame(Game $game): bool
    {
        if ($this->tournamentBreak === null) {
            return false;
        }
        if ($this->drewbreak === true) {
            return false;
        }
        return $game->getStartDateTime()->getTimestamp() === $this->tournamentBreak->getEndDate()->getTimestamp();
    }

    public function getGames(RoundNumber $roundNumber): array
    {
        $games = [];
        foreach ($roundNumber->getRounds() as $round) {
            foreach ($round->getPoules() as $poule) {
                foreach ($poule->getGames() as $game) {
                    if ($this->gameFilter === null || $this->getGameFilter()($game)) {
                        $games[] = $game;
                    }
                }
            }
        }
        return $games;
    }

    protected function getRowHeight() {
        if( $this->rowHeight === null ) {
            $this->rowHeight = 18;
        }
        return $this->rowHeight;
    }

    public function drawRoundNumberHeader( RoundNumber $roundNumber, $nY )
    {
        $this->setFillColor(new \Zend_Pdf_Color_GrayScale(1));
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
     * add winnerslosers if roundnumber is 2 and has sibling
     *
     * @param Round $round
     * @param NameService $nameService
     * @return string
     */
//    protected function getRoundNameStructure( Round $round, NameService $nameService ): string
//    {
//        $roundName = $nameService->getRoundName( $round );
//        if( $round->getNumber() === 2 and $round->getOpposingRound() !== null ) {
//            $roundName .= ' - ' . $nameService->getWinnersLosersDescription($round->getWinnersOrlosers()) . 's';
//        }
//        return $roundName;
//    }




}