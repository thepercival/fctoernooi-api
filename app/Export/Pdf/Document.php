<?php

declare(strict_types=1);

namespace App\Export\Pdf;

use FCToernooi\Tournament;
use FCToernooi\LockerRoom;
use Sports\Game;
use Sports\Competition\Sport as CompetitionSport;
use Sports\Game\Together as TogetherGame;
use Sports\Poule;
use Sports\State;
use Sports\Structure;
use Sports\Game\Against as AgainstGame;
use Sports\Game\Order as GameOrder;
use Sports\Round\Number as RoundNumber;
use App\Export\Pdf\Page\PoulePivotTable\Against as AgainstPoulePivotTablePage;
use App\Export\Pdf\Page\PoulePivotTable\Together as TogetherPoulePivotTablePage;
use App\Export\Pdf\Page\GameNotes\Against as AgainstGameNotesPage;
use App\Export\Pdf\Page\Planning as PagePlanning;
use FCToernooi\Tournament\ExportConfig;
use App\Export\Document as ExportDocument;
use App\Exceptions\PdfOutOfBoundsException;
use SportsHelpers\Sport\Variant\Against as AgainstSportVariant;
use SportsHelpers\Sport\Variant\AllInOneGame as AllInOneGameSportVariant;
use SportsHelpers\Sport\Variant\Single as SingleSportVariant;
use Zend_Pdf;
use Zend_Pdf_Exception;
use Zend_Pdf_Font;
use Zend_Pdf_Page;
use Zend_Pdf_Parser;
use Zend_Pdf_Resource_Font;

/**
 * @psalm-suppress PropertyNotSetInConstructor
 */
class Document extends Zend_Pdf
{
//    protected int $m_nHeaderHeight;
//    protected int $m_nPageMargin;

    /**
     * @var array<string, float>
     */
    protected array $widthText = [];

    use ExportDocument;

    public function __construct(
        Tournament $tournament,
        Structure $structure,
        protected int $subjects,
        string $url
    ) {
        parent::__construct();
        $this->tournament = $tournament;
        $this->structure = $structure;
        $this->url = $url;
    }

    public function getFontHeight(): int
    {
        return 14;
    }

    public function getFontHeightSubHeader(): int
    {
        return 16;
    }

    public function render($newSegmentOnly = false, $outputStream = null): string
    {
        $this->fillContent();
        return parent::render($newSegmentOnly, $outputStream);
    }

    public function getFont(bool $bBold = false, bool $bItalic = false): Zend_Pdf_Resource_Font
    {
        $suffix = 'times.ttf';
        if ($bBold === true and $bItalic === false) {
            $suffix = 'timesbd.ttf';
        } elseif ($bBold === false and $bItalic === true) {
            $suffix = 'timesi.ttf';
        } elseif ($bBold === true and $bItalic === true) {
            $suffix = 'timesbi.ttf';
        }
        $sFontDir = __DIR__ . '/../../../fonts/';
        return Zend_Pdf_Font::fontWithPath($sFontDir . $suffix);
    }

    protected function fillContent(): void
    {
        if (($this->subjects & ExportConfig::Structure) === ExportConfig::Structure) {
            $page = $this->createPageGrouping();
            $page->draw();

            $this->createAndDrawPageStructure(Zend_Pdf_Page::SIZE_A4);
        }
        if (($this->subjects & ExportConfig::PoulePivotTables) === ExportConfig::PoulePivotTables) {
            $this->drawPoulePivotTables($this->structure->getFirstRoundNumber());
        }
        if (($this->subjects & ExportConfig::Planning) === ExportConfig::Planning) {
            $firstRoundNumber = $this->structure->getFirstRoundNumber();
            $title = 'wedstrijden';
            $page = $this->createPagePlanning($firstRoundNumber, $title);
            $y = $page->drawHeader($title);
            $this->drawPlanning($firstRoundNumber, $page, $y);
        }
        if (($this->subjects & ExportConfig::GameNotes) === ExportConfig::GameNotes) {
            $this->drawGameNotes($this->structure->getFirstRoundNumber());
        }
        if (($this->subjects & ExportConfig::GamesPerPoule) === ExportConfig::GamesPerPoule) {
            $this->drawPlanningPerPoule($this->structure->getFirstRoundNumber());
        }
        if (($this->subjects & ExportConfig::GamesPerField) === ExportConfig::GamesPerField) {
            $this->drawPlanningPerField($this->structure->getFirstRoundNumber());
        }
        if (($this->subjects & ExportConfig::QrCode) === ExportConfig::QrCode) {
            $page = $this->createPageQRCode();
            $page->draw();
        }
        if (($this->subjects & ExportConfig::LockerRooms) === ExportConfig::LockerRooms) {
            $page = $this->createPageLockerRooms();
            $page->draw();

            foreach ($this->getTournament()->getLockerRooms() as $lockerRoom) {
                $page = $this->createPageLockerRoom($lockerRoom);
                $page->draw();
            }
        }
    }

    protected function drawPlanning(RoundNumber $roundNumber, PagePlanning $page, float$y): void
    {
        $games = $page->getFilteredGames($roundNumber);
        if (count($games) > 0) {
            $y = $page->drawRoundNumberHeader($roundNumber, $y);
            $y = $page->drawGamesHeader($roundNumber, $y);
        }

//        $games = $page->getGames($roundNumber);
//        if (count($games) > 0) {
//            $y = $page->drawGamesHeader($roundNumber, $y);
//        }
        $games = $roundNumber->getGames(GameOrder::ByBatch);
        $drewbreak = false;
        foreach ($games as $game) {
            $gameHeight = $page->getRowHeight();
            $drawBreak = $page->drawBreakBeforeGame($game, $drewbreak);
            $gameHeight += $drawBreak ? $gameHeight : 0;
            if ($y - $gameHeight < $page->getPageMargin()) {
                $title = 'wedstrijden';
                $page = $this->createPagePlanning($roundNumber, $title);
                $y = $page->drawHeader($title);
                $y = $page->drawGamesHeader($roundNumber, $y);
            }
            if ($drawBreak && !$drewbreak) {
                $y = $page->drawBreak($roundNumber, $game, $y);
                $drewbreak = true;
            }
            $y = $page->drawGame($game, $y, true);
        }
        $nextRoundNumber = $roundNumber->getNext();
        if ($nextRoundNumber !== null) {
            $y -= 20;
            $this->drawPlanning($nextRoundNumber, $page, $y);
        }
    }

    protected function drawPlanningPerPoule(RoundNumber $roundNumber): void
    {
        $poules = $roundNumber->getPoules();
        foreach ($poules as $poule) {
            $title = $this->getNameService()->getPouleName($poule, true);
            $page = $this->createPagePlanning($roundNumber, $title);
            $y = $page->drawHeader($title);
            $page->setGameFilter(
                function (Game $game) use ($poule): bool {
                    return $game->getPoule() === $poule;
                }
            );
            $this->drawPlanningPerHelper($roundNumber, $page, $y, false);
        }

        $nextRoundNumber = $roundNumber->getNext();
        if ($nextRoundNumber !== null) {
            $this->drawPlanningPerPoule($nextRoundNumber);
        }
    }

    protected function drawPlanningPerField(RoundNumber $roundNumber): void
    {
        $fields = $this->getTournament()->getCompetition()->getFields();
        foreach ($fields as $field) {
            $title = 'veld ' . (string)$field->getName();
            $page = $this->createPagePlanning($roundNumber, $title);
            $y = $page->drawHeader($title);
            $page->setGameFilter(
                function (Game $game) use ($field): bool {
                    return $game->getField() === $field;
                }
            );
            $this->drawPlanningPerHelper($roundNumber, $page, $y, true);
        }
    }

    protected function drawPlanningPerHelper(RoundNumber $roundNumber, PagePlanning $page, float $y, bool $recursive): void
    {
        $y = $page->drawRoundNumberHeader($roundNumber, $y);
        $games = $page->getFilteredGames($roundNumber);
        if (count($games) > 0) {
            $y = $page->drawGamesHeader($roundNumber, $y);
        }
        $games = $roundNumber->getGames(GameOrder::ByBatch);
        $drewbreak = false;
        foreach ($games as $game) {
            $gameHeight = $page->getRowHeight();
            $drawBreak = $page->drawBreakBeforeGame($game, $drewbreak);
            $gameHeight += $drawBreak ? $gameHeight : 0;
            if ($y - $gameHeight < $page->getPageMargin()) {
                // $field = $page->getFieldFilter();
                $page = $this->createPagePlanning($roundNumber, $page->getTitle() ?? '');
                $y = $page->drawHeader($page->getTitle());
                $page->setGameFilter($page->getGameFilter());
                $y = $page->drawGamesHeader($roundNumber, $y);
            }
            if ($drawBreak && !$drewbreak) {
                $y = $page->drawBreak($roundNumber, $game, $y);
                $drewbreak = true;
            }
            $y = $page->drawGame($game, $y);
        }

        $nextRoundNumber = $roundNumber->getNext();
        if ($nextRoundNumber !== null && $recursive) {
            $y -= 20;
            $this->drawPlanningPerHelper($nextRoundNumber, $page, $y, $recursive);
        }
    }

    protected function drawGameNotes(RoundNumber $roundNumber): void
    {
        foreach ($roundNumber->getCompetitionSports() as $competitionSport) {
            $sportVariant = $competitionSport->createVariant();
            if ($sportVariant instanceof AgainstSportVariant) {
                $this->drawAgainstGameNotes($roundNumber, $competitionSport);
            } else {
                throw new \Exception('implement together', E_ERROR);
            }
        }

        $nextRoundNumber = $roundNumber->getNext();
        if ($nextRoundNumber !== null) {
            $this->drawGameNotes($nextRoundNumber);
        }
    }

    protected function drawAgainstGameNotes(RoundNumber $roundNumber, CompetitionSport $competitionSport): void
    {
        $games = $this->getAgainstGames($roundNumber, $competitionSport); // per poule
        while ($gameOne = array_shift($games)) {
            $page = $this->createAgainstGameNotesPage($gameOne, array_shift($games));
            $page->draw();
        }
    }

    /**
     * @param RoundNumber $roundNumber
     * @param CompetitionSport $competitionSport
     * @return list<AgainstGame>
     */
    protected function getAgainstGames(RoundNumber $roundNumber, CompetitionSport $competitionSport): array
    {
        $games = [];
        foreach ($roundNumber->getRounds() as $round) {
            $roundGames = $round->getGamesWithState(State::Created);
            $againstRoundGames = array_filter($roundGames, function (AgainstGame|TogetherGame $game) use ($competitionSport): bool {
                return $game->getCompetitionSport() === $competitionSport && $game instanceof AgainstGame;
            });
            $games = array_merge($games, $againstRoundGames);
        }
        return array_values($games);


        $games = $this->getGames($roundNumber, $competitionSport); // per poule
        while ($gameOne = array_shift($games)) {
            $page = $this->createAgainstGameNotesPage($gameOne, array_shift($games));
            $page->draw();
        }
    }

    protected function drawPoulePivotTables(
        RoundNumber $roundNumber,
        AgainstPoulePivotTablePage|TogetherPoulePivotTablePage $page = null,
        float $y = null
    ): void
    {
        if ($roundNumber->needsRanking()) {
            $gameAmountConfigs = $roundNumber->getValidGameAmountConfigs();
            $drawRoundNumberHeader = true;
            foreach ($roundNumber->getRounds() as $round) {
                foreach ($round->getPoules() as $poule) {
                    if (!$poule->needsRanking()) {
                        continue;
                    }
                    foreach ($gameAmountConfigs as $gameAmountConfig) {
                        if ($page === null) {
                            $page = $this->createPagePoulePivotTables($gameAmountConfig->createVariant(), $poule);
                            $y = $page->drawHeader('pouledraaitabel');
                            if ($drawRoundNumberHeader) {
                                $y = $page->drawRoundNumberHeader($roundNumber, $y);
                            }
                            $drawRoundNumberHeader = false;
                        }
                        $pouleHeight = $page->getPouleHeight($poule);
                        if ($y !== null && $y - $pouleHeight < $page->getPageMargin()) {
                            $page = $this->createPagePoulePivotTables($gameAmountConfig->createVariant(), $poule);
                            $y = $page->drawHeader('pouledraaitabel');
                            if ($drawRoundNumberHeader) {
                                $y = $page->drawRoundNumberHeader($roundNumber, $y);
                            }
                            $drawRoundNumberHeader = false;
                        }
                        if ($y !== null) {
                            $y = $page->draw($poule, $gameAmountConfig, $y);
                        }
                    }
                }
            }
        }
        $nextRoundNumber = $roundNumber->getNext();
        if ($nextRoundNumber !== null) {
            $this->drawPoulePivotTables($nextRoundNumber, $page, $y);
        }
    }

    protected function createPageGrouping(): Page\Grouping
    {
        $page = new Page\Grouping($this, Zend_Pdf_Page::SIZE_A4);
        $page->setFont($this->getFont(), $this->getFontHeight());
        $this->pages[] = $page;
        return $page;
    }

    protected function createAndDrawPageStructure(string $pageLayout, bool $enableOutOfBoundsException = true): void
    {
        $page = new Page\Structure($this, $pageLayout, $enableOutOfBoundsException);
        $page->setFont($this->getFont(), $this->getFontHeight());
        try {
            $page->draw();
            $this->pages[] = $page;
        } catch (PdfOutOfBoundsException $exception) {
            if ($pageLayout === Zend_Pdf_Page::SIZE_A4) {
                $this->createAndDrawPageStructure(Zend_Pdf_Page::SIZE_A4_LANDSCAPE, false);
            }
        }
    }

    /**
     * @param RoundNumber $roundNumber
     * @param string $title
     * @return PagePlanning
     * @throws Zend_Pdf_Exception
     */
    protected function createPagePlanning(RoundNumber $roundNumber, string $title): PagePlanning
    {
        $selfRefereeEnabled = $roundNumber->getValidPlanningConfig()->selfRefereeEnabled();
        $page = new PagePlanning($this, $selfRefereeEnabled ? Zend_Pdf_Page::SIZE_A4_LANDSCAPE : Zend_Pdf_Page::SIZE_A4);
        $page->setTournamentBreak($this->tournament->getBreak());
        $page->setFont($this->getFont(), $this->getFontHeight());
        $page->setTitle($title);
        $this->pages[] = $page;
        return $page;
    }

    protected function createAgainstGameNotesPage(AgainstGame $gameA, AgainstGame $gameB = null): AgainstGameNotesPage
    {
        $page = new AgainstGameNotesPage($this, Zend_Pdf_Page::SIZE_A4, $gameA, $gameB);
        $page->setFont($this->getFont(), $this->getFontHeight());
        $this->pages[] = $page;
        return $page;
    }

//    protected function createTogetherGameNotesPage(AgainstGame $gameA = null, AgainstGame $gameB = null): AgainstGameNotesPage
//    {
//        $page = new AgainstGameNotesPage($this, Zend_Pdf_Page::SIZE_A4, $gameA, $gameB);
//        $page->setFont($this->getFont(), $this->getFontHeight());
//        $this->pages[] = $page;
//        return $page;
//    }

    /**
     * @param SingleSportVariant|AgainstSportVariant|AllInOneGameSportVariant $sportVariant
     * @param Poule $poule
     * @return AgainstPoulePivotTablePage|TogetherPoulePivotTablePage
     */
    protected function createPagePoulePivotTables(
        SingleSportVariant|AgainstSportVariant|AllInOneGameSportVariant $sportVariant,
        Poule $poule
    ): AgainstPoulePivotTablePage | TogetherPoulePivotTablePage {
        $dimension = $this->getPoulePivotPageDimension($sportVariant, $poule);
        if ($sportVariant instanceof AgainstSportVariant) {
            $page = new AgainstPoulePivotTablePage($this, $dimension);
        } else {
            $page = new TogetherPoulePivotTablePage($this, $dimension);
        }
        $page->setFont($this->getFont(), $this->getFontHeight());
        $this->pages[] = $page;
        return $page;
    }

    protected function getPoulePivotPageDimension(
        SingleSportVariant|AgainstSportVariant|AllInOneGameSportVariant $sportVariant,
        Poule $poule
    ): string
    {
        if ($sportVariant instanceof AgainstSportVariant) {
            $nrOfColumns = $poule->getPlaces()->count();
        } else {
            $nrOfColumns = $sportVariant->getNrOfGamesPerPlace();
        }
        return $nrOfColumns <= 12 ? Zend_Pdf_Page::SIZE_A4 : Zend_Pdf_Page::SIZE_A4_LANDSCAPE;
    }

    protected function createPageQRCode(): Page\QRCode
    {
        $page = new Page\QRCode($this, Zend_Pdf_Page::SIZE_A4);
        $page->setFont($this->getFont(), $this->getFontHeight());
        $this->pages[] = $page;
        return $page;
    }

    protected function createPageLockerRooms(): Page\LockerRooms
    {
        $page = new Page\LockerRooms($this, Zend_Pdf_Page::SIZE_A4);
        $page->setFont($this->getFont(), $this->getFontHeight());
        $this->pages[] = $page;
        return $page;
    }

    protected function createPageLockerRoom(LockerRoom $lockerRoom): Page\LockerRoom
    {
        $page = new Page\LockerRoom($this, Zend_Pdf_Page::SIZE_A4, $lockerRoom);
        $page->setFont($this->getFont(), $this->getFontHeight());
        $this->pages[] = $page;
        return $page;
    }

    public function hasTextWidth(string $key): bool
    {
        return array_key_exists($key, $this->widthText);
    }

    public function getTextWidth(string $key): float
    {
        return $this->widthText[$key];
    }

    public function setTextWidth(string $key, float $value): float
    {
        $this->widthText[$key] = $value;
        return $value;
    }
}
