<?php
declare(strict_types=1);

namespace App\Export\Pdf\Page;

use App\Export\Pdf\Align;
use App\Export\Pdf\Document;
use App\Export\Pdf\GameLine\Column\Referee as RefereeColumn;
use App\Export\Pdf\GameLine\Column\DateTime as DateTimeColumn;
use App\Export\Pdf\Page as ToernooiPdfPage;
use App\Export\Pdf\GameLine\Against as AgainstGameLine;
use League\Period\Period;
use Sports\Competition;
use Sports\Game;
use Sports\Game\Against as AgainstGame;
use Sports\Game\Together as TogetherGame;
use SportsHelpers\GameMode;
use SportsHelpers\Sport\Variant\Against as AgainstSportVariant;
use Sports\Round\Number as RoundNumber;
use Sports\NameService;
use Zend_Pdf_Exception;

class Planning extends ToernooiPdfPage
{
    protected Period|null $tournamentBreak = null;
    protected AgainstGameLine|null $againstGameLine = null;
    protected mixed $gameFilter = null;
    protected string|null $title = null;

    protected float $rowHeight = 18;

    public function __construct(Document $document, mixed $param1)
    {
        parent::__construct($document, $param1);
        $this->setLineWidth(0.5);
    }

    public function setTournamentBreak(Period|null $break = null): void
    {
        $this->tournamentBreak = $break;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    protected function getGameLine(AgainstGame|TogetherGame $game): AgainstGameLine
    {
        return $this->getGameLineByGameMode($game->getCompetitionSport()->getGameMode());
    }

    protected function getGameLineByGameMode(int $gameMode): AgainstGameLine
    {
        if ($gameMode === GameMode::AGAINST && $this->againstGameLine !== null) {
            return $this->againstGameLine;
        }
        throw new \Exception('togethergame should be implemented', E_ERROR);
    }

    public function getPageMargin(): float
    {
        return 20;
    }

    public function getHeaderHeight(): float
    {
        return 0;
    }

    public function getGameFilter(): callable|null
    {
        return $this->gameFilter;
    }

    public function setGameFilter(callable|null $gameFilter): void
    {
        $this->gameFilter = $gameFilter;
    }

    protected function initGameLines(RoundNumber $roundNumber): void
    {
        $showDateTime = $this->getShowDateTime($roundNumber);
        $showReferee = $this->getShowReferee($roundNumber);
        $competitionSports = $roundNumber->getCompetitionSports();
        foreach ($competitionSports as $competitionSport) {
            $sportVariant = $competitionSport->createVariant();
            if ($sportVariant instanceof AgainstSportVariant) {
                if ($this->againstGameLine === null) {
                    $this->againstGameLine = new AgainstGameLine($this, $showDateTime, $showReferee);
                }
            } else {
                throw new \Exception('togethergame should be implemented', E_ERROR);
            }
        }
    }

    protected function hasOnlyAgainstGameMode(Competition $competition): bool
    {
        foreach ($competition->createSportVariants() as $sportVariant) {
            if (!($sportVariant instanceof AgainstSportVariant)) {
                return false;
            }
        }
        return true;
    }

    protected function getShowReferee(RoundNumber $roundNumber): int
    {
        $planningConfig = $roundNumber->getValidPlanningConfig();
        if ($planningConfig->selfRefereeEnabled()) {
            return RefereeColumn::SelfReferee;
        } elseif ($roundNumber->getCompetition()->getReferees()->count() >= 1) {
            return RefereeColumn::Referee;
        }
        return RefereeColumn::None;
    }

    protected function getShowDateTime(RoundNumber $roundNumber): int
    {
        $planningConfig = $roundNumber->getValidPlanningConfig();
        if (!$planningConfig->getEnableTime()) {
            return DateTimeColumn::None;
        }
        if ($this->getParent()->gamesOnSameDay($roundNumber)) {
            return DateTimeColumn::Time;
        }
        return DateTimeColumn::DateTime;
    }

    public function drawGamesHeader(RoundNumber $roundNumber, float $y): float
    {
        $this->initGameLines($roundNumber);
        if ($this->hasOnlyAgainstGameMode($roundNumber->getCompetition())) {
            $gameLine = $this->getGameLineByGameMode(GameMode::AGAINST);
        } else {
            $gameLine = $this->getGameLineByGameMode(GameMode::SINGLE);
        }
        return $gameLine->drawHeader($roundNumber->needsRanking(), $y);
    }

    public function drawBreakBeforeGame(Game $game, bool $drewbreak): bool
    {
        if ($this->tournamentBreak === null) {
            return false;
        }
        if ($drewbreak === true) {
            return false;
        }
        return $game->getStartDateTime()->getTimestamp() === $this->tournamentBreak->getEndDate()->getTimestamp();
    }

    public function drawGame(AgainstGame|TogetherGame $game, float $y, bool $striped = false): float
    {
        $gameFilter = $this->getGameFilter();
        if ($gameFilter !== null && !$gameFilter($game)) {
            return $y;
        }
        return $this->getGameLine($game)->drawGame($game, $y, $striped);
    }

    public function drawBreak(RoundNumber $roundNumber, AgainstGame|TogetherGame $game, float $y): float
    {
        if ($this->tournamentBreak === null) {
            return $y;
        }
        return $this->getGameLine($game)->drawBreak($roundNumber, $this->tournamentBreak, $y);
    }

    public function getFilteredGames(RoundNumber $roundNumber): array
    {
        $games = [];
        foreach ($roundNumber->getRounds() as $round) {
            foreach ($round->getPoules() as $poule) {
                foreach ($poule->getGames() as $game) {
                    $gameFilter = $this->getGameFilter();
                    if ($gameFilter === null || $gameFilter($game)) {
                        $games[] = $game;
                    }
                }
            }
        }
        return $games;
    }

    public function getRowHeight(): float
    {
        return $this->rowHeight;
    }

    public function drawRoundNumberHeader(RoundNumber $roundNumber, float $y): float
    {
        $this->setFillColor(new \Zend_Pdf_Color_GrayScale(1));
        $fontHeightSubHeader = $this->getParent()->getFontHeightSubHeader();
        $this->setFont($this->getParent()->getFont(true), $this->getParent()->getFontHeightSubHeader());
        $x = $this->getPageMargin();
        $displayWidth = $this->getDisplayWidth();
        $subHeader = $this->getParent()->getNameService()->getRoundNumberName($roundNumber);
        $this->drawCell($subHeader, $x, $y, $displayWidth, $fontHeightSubHeader, Align::Center);
        $this->setFont($this->getParent()->getFont(), $this->getParent()->getFontHeight());
        return $y - (2 * $fontHeightSubHeader);
    }


//    protected function getRoundNameStructure( Round $round, NameService $nameService ): string
//    {
//        $roundName = $nameService->getRoundName( $round );
//        if( $round->getNumber() === 2 and $round->getOpposingRound() !== null ) {
//            $roundName .= ' - ' . $nameService->getWinnersLosersDescription($round->getWinnersOrlosers()) . 's';
//        }
//        return $roundName;
//    }
}
