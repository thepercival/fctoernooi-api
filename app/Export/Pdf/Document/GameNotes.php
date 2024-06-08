<?php

declare(strict_types=1);

namespace App\Export\Pdf\Document;

use App\Export\Pdf\Configs\GameNotesConfig;
use App\Export\Pdf\Document as PdfDocument;
use App\Export\Pdf\Drawers\GameNote\Against as AgainstDrawer;
use App\Export\Pdf\Drawers\GameNote\AllInOneGame as AllInOneGameDrawer;
use App\Export\Pdf\Drawers\GameNote\Single as SingleDrawer;
use App\Export\Pdf\Page\GameNotes as GameNotesPage;
use App\Export\PdfProgress;
use App\ImagePathResolver;
use FCToernooi\Tournament;
use Sports\Competition\Referee;
use Sports\Competition\Sport as CompetitionSport;
use Sports\Game\Against as AgainstGame;
use Sports\Game\Order;
use Sports\Game\Together as TogetherGame;
use Sports\Round\Number as RoundNumber;
use Sports\Structure;
use SportsHelpers\SelfReferee;
use SportsHelpers\Sport\Variant\Single;
use Zend_Pdf_Page;

/**
 * @psalm-suppress PropertyNotSetInConstructor
 */
class GameNotes extends PdfDocument
{
    public function __construct(
        Tournament $tournament,
        Structure $structure,
        ImagePathResolver $imagePathResolver,
        PdfProgress $progress,
        float $maxSubjectProgress,
        protected GameNotesConfig $config
    ) {
        parent::__construct($tournament, $structure, $imagePathResolver, $progress, $maxSubjectProgress);
    }

    public function getConfig(): GameNotesConfig
    {
        return $this->config;
    }

    protected function renderCustom(): void
    {
        $nrOfGameNotes = $this->getTotalNrOfGameNotes($this->structure->getFirstRoundNumber());
        if ($nrOfGameNotes > 0) {
            $nrOfProgressPerGameNote = $this->maxSubjectProgress / $nrOfGameNotes;
        } else {
            $nrOfProgressPerGameNote = 0;
        }

        $this->renderGameNotes($this->structure->getFirstRoundNumber(), $nrOfProgressPerGameNote);
    }


    /**
     * do not remove, progress is done while drawing, when remove, progress will be out of bounds
     */
    protected function updateProgress(): void
    {
    }

    protected function getTotalNrOfGameNotes(RoundNumber $roundNumber): int
    {
        $nrOfGameNotes = 0;
        if ($this->orderedGamesByReferee($roundNumber)) {
            foreach ($roundNumber->getCompetition()->getReferees() as $referee) {
                $gamesPerPage = $this->getGamesPerPage($roundNumber, $referee);
                foreach ($gamesPerPage as $games) {
                    $nrOfGameNotes += count($games);
                }
            }
        } else {
            $gamesPerPage = $this->getGamesPerPage($roundNumber, null);
            foreach ($gamesPerPage as $games) {
                $nrOfGameNotes += count($games);
            }
        }

        $nextRoundNumber = $roundNumber->getNext();
        if ($nextRoundNumber !== null) {
            return $nrOfGameNotes + $this->getTotalNrOfGameNotes($nextRoundNumber);
        }
        return $nrOfGameNotes;
    }


    protected function createPage(): GameNotesPage
    {
        $page = new GameNotesPage($this, Zend_Pdf_Page::SIZE_A4);
        $this->pages[] = $page;
        return $page;
    }

    protected function renderGameNotes(RoundNumber $roundNumber, float $nrOfProgressPerGameNote): void
    {
        if ($this->orderedGamesByReferee($roundNumber)) {
            foreach ($roundNumber->getCompetition()->getReferees() as $referee) {
                $gamesPerPage = $this->getGamesPerPage($roundNumber, $referee);
                foreach ($gamesPerPage as $games) {
                    $progression = $nrOfProgressPerGameNote * count($games);
                    $this->createPage()->renderGames(array_shift($games), array_shift($games));
                    $this->progress->addProgression($progression);
                }
//                    $sportVariant = $competitionSport->createVariant();
//                    if ($sportVariant instanceof Against) {
//                        $this->drawAgainstGameNotesPerReferee($roundNumber, $competitionSport, $referee, $nrOfProgressPerGameNote);
//                    } elseif ($sportVariant instanceof Single) {
//                        $this->drawSingleGameNotes($roundNumber, $competitionSport, $referee, $nrOfProgressPerGameNote);
//                    } else {
//                        $this->drawAllInOneGameNotes($roundNumber, $competitionSport, $referee, $nrOfProgressPerGameNote);
//                    }
//                }
            }
        } else {
            $gamesPerPage = $this->getGamesPerPage($roundNumber, null);
            foreach ($gamesPerPage as $games) {
                $progression = $nrOfProgressPerGameNote * count($games);
                $this->createPage()->renderGames(array_shift($games), array_shift($games));
                $this->progress->addProgression($progression);
            }


//            foreach ($roundNumber->getCompetitionSports() as $competitionSport) {
//                $sportVariant = $competitionSport->createVariant();
//                //       $games = $this->getOrderedGames($roundNumber)
//                if ($sportVariant instanceof Against) {
//                    $this->drawAgainstGameNotesPerPoule($roundNumber, $competitionSport, $nrOfProgressPerGameNote);
//                } elseif ($sportVariant instanceof Single) {
//                    $this->drawSingleGameNotes($roundNumber, $competitionSport, $nrOfProgressPerGameNote);
//                } else {
//                    $this->drawAllInOneGameNotes($roundNumber, $competitionSport, $nrOfProgressPerGameNote);
//                }
//            }
        }

        $nextRoundNumber = $roundNumber->getNext();
        if ($nextRoundNumber !== null) {
            $this->renderGameNotes($nextRoundNumber, $nrOfProgressPerGameNote);
        }
    }

    protected function orderedGamesByReferee(RoundNumber $roundNumber): bool
    {
        return $roundNumber->getValidPlanningConfig()->getSelfReferee() === SelfReferee::Disabled
            && count($roundNumber->getCompetition()->getReferees()) > 0;
    }

//    protected function drawAgainstGameNotesPerPoule(
//        RoundNumber $roundNumber,
//        CompetitionSport $competitionSport,
//        float $nrOfProgressPerGameNote
//    ): void {
//        foreach ($roundNumber->getPoules() as $poule) {
//            $games = $this->getAgainstGames($poule, $competitionSport); // per poule
//            $oneGamePerPage = $this->getNrOfGameNoteScoreLines($poule->getRound(), $competitionSport) > 5;
//            while ($gameOne = array_shift($games)) {
//                $gameTwo = $oneGamePerPage ? null : array_shift($games);
//                $page = $this->createAgainstGameNotesPage($gameOne, $gameTwo);
//                $page->draw($oneGamePerPage);
//                $this->progress->addProgression($nrOfProgressPerGameNote);
//            }
//        }
//    }

    /**
     * @param RoundNumber $roundNumber
     * @param Referee|null $referee
     * @return list<list<AgainstGame|TogetherGame>>
     */
    protected function getGamesPerPage(RoundNumber $roundNumber, Referee|null $referee): array
    {
        $gamesPerPage = [];
        $gamesTmp = $roundNumber->getGames(Order::ByBatch);
        if ($referee === null) {
            $games = [];
            foreach ($roundNumber->getPoules() as $poule) {
                $games = array_merge($games, $poule->getGames());
            }
        } else {
            $games = array_filter($gamesTmp, function ($game) use ($referee): bool {
                return $game->getReferee() === $referee;
            });
        }

        while ($gameOne = array_shift($games)) {
            $oneGamePerPageGameOne = $this->getNrOfScoreLines($gameOne, $gameOne->getCompetitionSport()) > 5;

            if ($oneGamePerPageGameOne) {
                $gamesPerPage[] = [$gameOne];
                continue;
            }

            $gameTwo = array_shift($games);

            $oneGamePerPageGameTwo = false;
            if( $gameTwo !== null ) {
                $oneGamePerPageGameTwo = $this->getNrOfScoreLines($gameTwo, $gameTwo->getCompetitionSport()) > 5;
            }

            if ($oneGamePerPageGameTwo) {
                $gamesPerPage[] = [$gameOne];
                $gamesPerPage[] = [$gameTwo];
            } else {
                $gamesPerPage[] = [$gameOne, $gameTwo];
            }
        }
        return $gamesPerPage;
    }

//    /**
//     * @param list<non-empty-list<AgainstGame>> $gamesPerPage
//     * @param float $nrOfProgressPerGameNote
//     */
//    protected function drawAgainstGameNotes(array $gamesPerPage, float $nrOfProgressPerGameNote): void
//    {
//        foreach ($gamesPerPage as $games) {
//            $gameOne = array_shift($games);
//            $gameTwo = array_shift($games);
//            $page = $this->createAgainstGameNotesPage($gameOne, $gameTwo);
//            $page->draw($gameTwo === null);
//            $this->progress->addProgression($nrOfProgressPerGameNote);
//        }
//    }
//
//    protected function drawSingleGameNotes(
//        RoundNumber $roundNumber,
//        CompetitionSport $competitionSport,
//        Referee|null $referee,
//        float $nrOfProgressPerGameNote
//    ): void {
//        foreach ($roundNumber->getRounds() as $round) {
//            $games = $this->getSingleGames($round, $competitionSport, $referee); // per poule
//            $oneGamePerPage = $this->getNrOfGameNoteScoreLines($round, $competitionSport) > 5;
//            while ($gameOne = array_shift($games)) {
//                $gameTwo = $oneGamePerPage ? null : array_shift($games);
//                $page = $this->createSingleGameNotesPage($gameOne, $gameTwo);
//                $page->draw($oneGamePerPage);
//                $this->progress->addProgression($nrOfProgressPerGameNote);
//            }
//        }
//    }
//
//    protected function drawAllInOneGameNotes(
//        RoundNumber $roundNumber,
//        CompetitionSport $competitionSport,
//        Referee|null $referee,
//        float $nrOfProgressPerGameNote
//    ): void {
//        foreach ($roundNumber->getPoules() as $poule) {
//            $oneGamePerPage = $poule->getPlaces()->count() > 5;
//            $games = $this->getAllInOneGames($poule, $competitionSport, $referee); // per poule
//            while ($gameOne = array_shift($games)) {
//                $gameTwo = $oneGamePerPage ? null : array_shift($games);
//                $page = $this->createAllInOneGameNotesPage($gameOne, $gameTwo);
//                $page->draw($oneGamePerPage);
//                $this->progress->addProgression($nrOfProgressPerGameNote);
//            }
//        }
//    }

    public function getNrOfScoreLines(TogetherGame|AgainstGame $game, CompetitionSport $competitionSport): int
    {
        return $this->createDrawer($game)->getNrOfScoreLines($game->getRound(), $competitionSport);
    }

    public function createDrawer(AgainstGame|TogetherGame $game): AgainstDrawer|SingleDrawer|AllInOneGameDrawer
    {
        if ($game instanceof AgainstGame) {
            return new AgainstDrawer($this->config);
        }
        $sportVariant = $game->getCompetitionSport()->createVariant();
        if ($sportVariant instanceof Single) {
            return new SingleDrawer($this->config);
        }
        return new AllInOneGameDrawer($this->config);
    }
}
