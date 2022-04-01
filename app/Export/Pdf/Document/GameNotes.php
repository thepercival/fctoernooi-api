<?php

declare(strict_types=1);

namespace App\Export\Pdf\Document;

use App\Export\Pdf\Document as PdfDocument;
use App\Export\Pdf\Page\GameNotes\Against as AgainstGameNotesPage;
use App\Export\Pdf\Page\GameNotes\AllInOneGame as AllInOneGameNotesPage;
use App\Export\Pdf\Page\GameNotes\Single as SingleGameNotesPage;
use Sports\Competition\Sport as CompetitionSport;
use Sports\Game\Against as AgainstGame;
use Sports\Game\Together as TogetherGame;
use Sports\Round;
use Sports\Round\Number as RoundNumber;
use Sports\Score\Config as ScoreConfig;
use SportsHelpers\Sport\Variant\Against as AgainstSportVariant;
use SportsHelpers\Sport\Variant\Single as SingleSportVariant;
use Zend_Pdf_Page;

/**
 * @psalm-suppress PropertyNotSetInConstructor
 */
class GameNotes extends PdfDocument
{
    protected function fillContent(): void
    {
        $nrOfGameNotes = $this->getNrOfGameNotes($this->structure->getFirstRoundNumber());
        $nrOfProgressPerGameNote = $this->maxSubjectProgress / $nrOfGameNotes;
        $this->drawGameNotes($this->structure->getFirstRoundNumber(), $nrOfProgressPerGameNote);
    }

    /**
     * do not remove, progress is done while drawing, when remove, progress will be out of bounds
     */
    protected function updateProgress(): void
    {
    }

    protected function getNrOfGameNotes(RoundNumber $roundNumber): int
    {
        $nrOfGameNotes = 0;
        foreach ($roundNumber->getCompetitionSports() as $competitionSport) {
            $sportVariant = $competitionSport->createVariant();
            if ($sportVariant instanceof AgainstSportVariant) {
                $nrOfGameNotes += $this->getNrOfAgainstGameNotes($roundNumber, $competitionSport);
            } elseif ($sportVariant instanceof SingleSportVariant) {
                $nrOfGameNotes += $this->getNrOfSingleGameNotes($roundNumber, $competitionSport);
            } else {
                $nrOfGameNotes += $this->getNrOfAllInOneGameGameNotes($roundNumber, $competitionSport);
            }
        }

        $nextRoundNumber = $roundNumber->getNext();
        if ($nextRoundNumber !== null) {
            return $nrOfGameNotes + $this->getNrOfGameNotes($nextRoundNumber);
        }
        return $nrOfGameNotes;
    }

    protected function getNrOfAgainstGameNotes(RoundNumber $roundNumber, CompetitionSport $competitionSport): int
    {
        $nrOfGameNotes = 0;
        foreach ($roundNumber->getRounds() as $round) {
            $games = $this->getAgainstGames($round, $competitionSport); // per poule
            $nrOfGameNotes += (int)ceil(count($games) / 2);
        }
        return $nrOfGameNotes;
    }

    protected function getNrOfSingleGameNotes(RoundNumber $roundNumber, CompetitionSport $competitionSport): int
    {
        $nrOfGameNotes = 0;
        foreach ($roundNumber->getRounds() as $round) {
            $games = $this->getSingleGames($round, $competitionSport); // per poule
            $nrOfGameNotes += (int)ceil(count($games) / 2);
        }
        return $nrOfGameNotes;
    }

    protected function getNrOfAllInOneGameGameNotes(RoundNumber $roundNumber, CompetitionSport $competitionSport): int
    {
        $nrOfGameNotes = 0;
        foreach ($roundNumber->getPoules() as $poule) {
            $games = $this->getAllInOneGames($poule, $competitionSport); // per poule
            $nrOfGameNotes += (int)ceil(count($games) / 2);
        }
        return $nrOfGameNotes;
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

    protected function drawGameNotes(RoundNumber $roundNumber, float $nrOfProgressPerGameNote): void
    {
        foreach ($roundNumber->getCompetitionSports() as $competitionSport) {
            $sportVariant = $competitionSport->createVariant();
            if ($sportVariant instanceof AgainstSportVariant) {
                $this->drawAgainstGameNotes($roundNumber, $competitionSport, $nrOfProgressPerGameNote);
            } elseif ($sportVariant instanceof SingleSportVariant) {
                $this->drawSingleGameNotes($roundNumber, $competitionSport, $nrOfProgressPerGameNote);
            } else {
                $this->drawAllInOneGameNotes($roundNumber, $competitionSport, $nrOfProgressPerGameNote);
            }
        }

        $nextRoundNumber = $roundNumber->getNext();
        if ($nextRoundNumber !== null) {
            $this->drawGameNotes($nextRoundNumber, $nrOfProgressPerGameNote);
        }
    }

    protected function drawAgainstGameNotes(
        RoundNumber $roundNumber,
        CompetitionSport $competitionSport,
        float $nrOfProgressPerGameNote
    ): void {
        foreach ($roundNumber->getRounds() as $round) {
            $games = $this->getAgainstGames($round, $competitionSport); // per poule
            $oneGamePerPage = $this->getNrOfGameNoteScoreLines($round, $competitionSport) > 5;
            while ($gameOne = array_shift($games)) {
                $gameTwo = $oneGamePerPage ? null : array_shift($games);
                $page = $this->createAgainstGameNotesPage($gameOne, $gameTwo);
                $page->draw($oneGamePerPage);
                $this->progress->addProgression($nrOfProgressPerGameNote);
            }
        }
    }

    protected function drawSingleGameNotes(
        RoundNumber $roundNumber,
        CompetitionSport $competitionSport,
        float $nrOfProgressPerGameNote
    ): void {
        foreach ($roundNumber->getRounds() as $round) {
            $games = $this->getSingleGames($round, $competitionSport); // per poule
            $oneGamePerPage = $this->getNrOfGameNoteScoreLines($round, $competitionSport) > 5;
            while ($gameOne = array_shift($games)) {
                $gameTwo = $oneGamePerPage ? null : array_shift($games);
                $page = $this->createSingleGameNotesPage($gameOne, $gameTwo);
                $page->draw($oneGamePerPage);
                $this->progress->addProgression($nrOfProgressPerGameNote);
            }
        }
    }

    protected function drawAllInOneGameNotes(
        RoundNumber $roundNumber,
        CompetitionSport $competitionSport,
        float $nrOfProgressPerGameNote
    ): void {
        foreach ($roundNumber->getPoules() as $poule) {
            $oneGamePerPage = $poule->getPlaces()->count() > 5;
            $games = $this->getAllInOneGames($poule, $competitionSport); // per poule
            while ($gameOne = array_shift($games)) {
                $gameTwo = $oneGamePerPage ? null : array_shift($games);
                $page = $this->createAllInOneGameNotesPage($gameOne, $gameTwo);
                $page->draw($oneGamePerPage);
                $this->progress->addProgression($nrOfProgressPerGameNote);
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
}
