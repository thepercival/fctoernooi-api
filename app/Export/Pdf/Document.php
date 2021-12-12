<?php

declare(strict_types=1);

namespace App\Export\Pdf;

use App\Exceptions\PdfOutOfBoundsException;
use App\Export\Document as ExportDocument;
use App\Export\Pdf\Page\GameNotes\Against as AgainstGameNotesPage;
use App\Export\Pdf\Page\GameNotes\AllInOneGame as AllInOneGameNotesPage;
use App\Export\Pdf\Page\GameNotes\Single as SingleGameNotesPage;
use App\Export\Pdf\Page\Planning as PagePlanning;
use App\Export\Pdf\Page\PoulePivotTable\Against as AgainstPoulePivotTablePage;
use App\Export\Pdf\Page\PoulePivotTable\Multiple as MultipleSportsPoulePivotTablePage;
use App\Export\Pdf\Page\PoulePivotTable\Together as TogetherPoulePivotTablePage;
use FCToernooi\LockerRoom;
use FCToernooi\Tournament;
use FCToernooi\Tournament\ExportConfig;
use Sports\Competition\Sport as CompetitionSport;
use Sports\Game;
use Sports\Game\Against as AgainstGame;
use Sports\Game\Order as GameOrder;
use Sports\Game\Together as TogetherGame;
use Sports\Poule;
use Sports\Round;
use Sports\Round\Number as RoundNumber;
use Sports\Score\Config as ScoreConfig;
use Sports\State;
use Sports\Structure;
use SportsHelpers\Sport\Variant\Against as AgainstSportVariant;
use SportsHelpers\Sport\Variant\AllInOneGame;
use SportsHelpers\Sport\Variant\AllInOneGame as AllInOneGameSportVariant;
use SportsHelpers\Sport\Variant\Single as SingleSportVariant;
use Zend_Pdf;
use Zend_Pdf_Exception;
use Zend_Pdf_Font;
use Zend_Pdf_Page;
use Zend_Pdf_Resource_Font;

/**
 * @psalm-suppress PropertyNotSetInConstructor
 */
class Document extends Zend_Pdf
{
    use ExportDocument;
//    protected int $m_nHeaderHeight;
//    protected int $m_nPageMargin;

    /**
     * @var array<string, float>
     */
    protected array $widthText = [];

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
        $games = $roundNumber->getGames(GameOrder::ByDate);
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
        $games = $roundNumber->getGames(GameOrder::ByDate);
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
            } elseif ($sportVariant instanceof SingleSportVariant) {
                $this->drawSingleGameNotes($roundNumber, $competitionSport);
            } else {
                $this->drawAllInOneGameNotes($roundNumber, $competitionSport);
            }
        }

        $nextRoundNumber = $roundNumber->getNext();
        if ($nextRoundNumber !== null) {
            $this->drawGameNotes($nextRoundNumber);
        }
    }

    protected function drawAgainstGameNotes(RoundNumber $roundNumber, CompetitionSport $competitionSport): void
    {
        foreach ($roundNumber->getRounds() as $round) {
            $games = $this->getAgainstGames($round, $competitionSport); // per poule
            $oneGamePerPage = $this->getNrOfGameNoteScoreLines($round, $competitionSport) > 5;
            while ($gameOne = array_shift($games)) {
                $gameTwo = $oneGamePerPage ? null : array_shift($games);
                $page = $this->createAgainstGameNotesPage($gameOne, $gameTwo);
                $page->draw($oneGamePerPage);
            }
        }
    }

    protected function drawSingleGameNotes(RoundNumber $roundNumber, CompetitionSport $competitionSport): void
    {
        foreach ($roundNumber->getRounds() as $round) {
            $games = $this->getSingleGames($round, $competitionSport); // per poule
            $oneGamePerPage = $this->getNrOfGameNoteScoreLines($round, $competitionSport) > 5;
            while ($gameOne = array_shift($games)) {
                $gameTwo = $oneGamePerPage ? null : array_shift($games);
                $page = $this->createSingleGameNotesPage($gameOne, $gameTwo);
                $page->draw($oneGamePerPage);
            }
        }
    }

    protected function drawAllInOneGameNotes(RoundNumber $roundNumber, CompetitionSport $competitionSport): void
    {
        foreach ($roundNumber->getPoules() as $poule) {
            $oneGamePerPage = $poule->getPlaces()->count() > 5;
            $games = $this->getAllInOneGames($poule, $competitionSport); // per poule
            while ($gameOne = array_shift($games)) {
                $gameTwo = $oneGamePerPage ? null : array_shift($games);
                $page = $this->createAllInOneGameNotesPage($gameOne, $gameTwo);
                $page->draw($oneGamePerPage);
            }
        }
    }

    public function getNrOfGameNoteScoreLines(Round $round, CompetitionSport $competitionSport): int
    {
        return $this->getNrOfGameNoteScoreLinesHelper(
            $round->getValidScoreConfig($competitionSport),
            $round->getNumber()->getValidPlanningConfig()->getExtension()
        );
    }

    protected function getNrOfGameNoteScoreLinesHelper(ScoreConfig $firstScoreConfig, bool $extension): int
    {
        if ($firstScoreConfig === $firstScoreConfig->getCalculate()) {
            return 1;
        }

        $nrOfScoreLines = (($firstScoreConfig->getCalculate()->getMaximum() * 2) - 1) + ($extension ? 1 : 0);
        if ($nrOfScoreLines < 1) {
            $nrOfScoreLines = 5;
        }
//        $maxNrOfScoreLines = AllInOneGameNotesPage::OnePageMaxNrOfScoreLines - ($extension ? 1 : 0);
//        if ($nrOfScoreLines > $maxNrOfScoreLines) {
//            $nrOfScoreLines = $maxNrOfScoreLines;
//        }
        return $nrOfScoreLines;
    }

    /**
     * @param Round $round
     * @param CompetitionSport $competitionSport
     * @return list<AgainstGame>
     */
    protected function getAgainstGames(Round $round, CompetitionSport $competitionSport): array
    {
        $games = $round->getGamesWithState(State::Created);
        $filtered = array_filter($games, function (AgainstGame|TogetherGame $game) use ($competitionSport): bool {
            return $game->getCompetitionSport() === $competitionSport && $game instanceof AgainstGame;
        });
        return array_values($filtered);
    }

    /**
     * @param Round $round
     * @param CompetitionSport $competitionSport
     * @return list<TogetherGame>
     */
    protected function getSingleGames(Round $round, CompetitionSport $competitionSport): array
    {
        $games = $round->getGamesWithState(State::Created);
        $filtered = array_filter($games, function (AgainstGame|TogetherGame $game) use ($competitionSport): bool {
            return $game->getCompetitionSport() === $competitionSport && $game instanceof TogetherGame;
        });
        return array_values($filtered);
    }

    /**
     * @param Poule $poule
     * @param CompetitionSport $competitionSport
     * @return list<TogetherGame>
     */
    protected function getAllInOneGames(Poule $poule, CompetitionSport $competitionSport): array
    {
        $games = $poule->getGamesWithState(State::Created);
        $games = array_filter($games, function (AgainstGame|TogetherGame $game) use ($competitionSport): bool {
            return $game->getCompetitionSport() === $competitionSport && $game instanceof TogetherGame;
        });
        return array_values($games);
    }

    protected function drawPoulePivotTables(
        RoundNumber $roundNumber,
        AgainstPoulePivotTablePage|TogetherPoulePivotTablePage $page = null,
        float $y = null
    ): void {
        if ($roundNumber->needsRanking()) {
            if ($roundNumber->getCompetition()->hasMultipleSports()) {
                $this->drawPoulePivotTablesMultipleSports($roundNumber);
            }
            $biggestPoule = $roundNumber->createPouleStructure()->getBiggestPoule();
            $gameAmountConfigs = $roundNumber->getValidGameAmountConfigs();
            foreach ($gameAmountConfigs as $gameAmountConfig) {
                $page = $this->createPagePoulePivotTables($gameAmountConfig->createVariant(), $biggestPoule);
                $y = $page->drawHeader('pouledraaitabel');
                $y = $page->drawPageStartHeader($roundNumber, $gameAmountConfig->getCompetitionSport(), $y);
                foreach ($roundNumber->getRounds() as $round) {
                    foreach ($round->getPoules() as $poule) {
                        if (!$poule->needsRanking()) {
                            continue;
                        }
                        $pouleHeight = $page->getPouleHeight($poule);
                        if ($y - $pouleHeight < $page->getPageMargin()) {
                            $nrOfPlaces = $poule->getPlaces()->count();
                            $page = $this->createPagePoulePivotTables($gameAmountConfig->createVariant(), $nrOfPlaces);
                            $y = $page->drawHeader('pouledraaitabel');
                            $y = $page->drawPageStartHeader($roundNumber, $gameAmountConfig->getCompetitionSport(), $y);
                        }
                        $y = $page->draw($poule, $gameAmountConfig, $y);
                    }
                }
            }
        }
        $nextRoundNumber = $roundNumber->getNext();
        if ($nextRoundNumber !== null) {
            $this->drawPoulePivotTables($nextRoundNumber, $page, $y);
        }
    }

    protected function drawPoulePivotTablesMultipleSports(RoundNumber $roundNumber): void
    {
        if (!$roundNumber->needsRanking()) {
            return;
        }
        $biggestPoule = $roundNumber->createPouleStructure()->getBiggestPoule();
        $page = $this->createPagePoulePivotTablesMultipleSports($biggestPoule);
        $y = $page->drawHeader('pouledraaitabel');
        $y = $page->drawPageStartHeader($roundNumber, $y);

        foreach ($roundNumber->getRounds() as $round) {
            foreach ($round->getPoules() as $poule) {
                if (!$poule->needsRanking()) {
                    continue;
                }
                $pouleHeight = $page->getPouleHeight($poule);
                if ($y - $pouleHeight < $page->getPageMargin()) {
                    $nrOfPlaces = $poule->getPlaces()->count();
                    $page = $this->createPagePoulePivotTablesMultipleSports($nrOfPlaces);
                    $y = $page->drawHeader('pouledraaitabel');
                    $y = $page->drawPageStartHeader($roundNumber, $y);
                }
                $y = $page->draw($poule, $y);
            }
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
        $page = new PagePlanning($this, $this->getPlanningPageDimension($roundNumber));
        $page->setTournamentBreak($this->tournament->getBreak());
        $page->setFont($this->getFont(), $this->getFontHeight());
        $page->setTitle($title);
        $this->pages[] = $page;
        return $page;
    }

    protected function getPlanningPageDimension(RoundNumber $roundNumber): string
    {
        $selfRefereeEnabled = $roundNumber->getValidPlanningConfig()->selfRefereeEnabled();
        if ($selfRefereeEnabled) {
            return Zend_Pdf_Page::SIZE_A4_LANDSCAPE;
        }
        foreach ($roundNumber->getCompetitionSports() as $competitionSport) {
            $sportVariant = $competitionSport->createVariant();
            if ($sportVariant instanceof AllInOneGameSportVariant) {
                if ($this->getMaxNrOfPlacesPerPoule($roundNumber) > 3) {
                    return Zend_Pdf_Page::SIZE_A4_LANDSCAPE;
                }
            } elseif ($sportVariant->getNrOfGamePlaces() > 3) {
                return Zend_Pdf_Page::SIZE_A4_LANDSCAPE;
            }
        }
        return Zend_Pdf_Page::SIZE_A4;
    }

    protected function getMaxNrOfPlacesPerPoule(RoundNumber $roundNumber): int
    {
        $nrOfPlacesPerPoule = 0;
        foreach ($roundNumber->getPoules() as $poule) {
            if ($poule->getPlaces()->count() > $nrOfPlacesPerPoule) {
                $nrOfPlacesPerPoule = $poule->getPlaces()->count();
            }
        }
        return $nrOfPlacesPerPoule;
    }

    protected function createAgainstGameNotesPage(AgainstGame $gameA, AgainstGame|null $gameB): AgainstGameNotesPage
    {
        $page = new AgainstGameNotesPage($this, Zend_Pdf_Page::SIZE_A4, $gameA, $gameB);
        $page->setFont($this->getFont(), $this->getFontHeight());
        $this->pages[] = $page;
        return $page;
    }

    protected function createSingleGameNotesPage(TogetherGame $gameA, TogetherGame|null $gameB): SingleGameNotesPage
    {
        $page = new SingleGameNotesPage($this, Zend_Pdf_Page::SIZE_A4, $gameA, $gameB);
        $page->setFont($this->getFont(), $this->getFontHeight());
        $this->pages[] = $page;
        return $page;
    }

    protected function createAllInOneGameNotesPage(TogetherGame $gameA, TogetherGame|null $gameB): AllInOneGameNotesPage
    {
        $page = new AllInOneGameNotesPage($this, Zend_Pdf_Page::SIZE_A4, $gameA, $gameB);
        $page->setFont($this->getFont(), $this->getFontHeight());
        $this->pages[] = $page;
        return $page;
    }

    /**
     * @param int $maxNrOfPlaces
     * @return MultipleSportsPoulePivotTablePage
     */
    protected function createPagePoulePivotTablesMultipleSports(
        int $maxNrOfPlaces
    ): MultipleSportsPoulePivotTablePage {
        $dimension = $this->getPoulePivotPageDimension(null, $maxNrOfPlaces);
        $page = new MultipleSportsPoulePivotTablePage($this, $dimension);
        $page->setFont($this->getFont(), $this->getFontHeight());
        $this->pages[] = $page;
        return $page;
    }

    /**
     * @param SingleSportVariant|AgainstSportVariant|AllInOneGameSportVariant $sportVariant
     * @param int $maxNrOfPlaces
     * @return AgainstPoulePivotTablePage|TogetherPoulePivotTablePage
     */
    protected function createPagePoulePivotTables(
        SingleSportVariant|AgainstSportVariant|AllInOneGameSportVariant $sportVariant,
        int $maxNrOfPlaces
    ): AgainstPoulePivotTablePage | TogetherPoulePivotTablePage {
        $dimension = $this->getPoulePivotPageDimension($sportVariant, $maxNrOfPlaces);
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
        SingleSportVariant|AgainstSportVariant|AllInOneGameSportVariant|null $sportVariant,
        int $maxNrOfPlaces
    ): string {
        if ($sportVariant === null || $sportVariant instanceof AgainstSportVariant) {
            $nrOfColumns = $maxNrOfPlaces;
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
