<?php

declare(strict_types=1);

namespace App\Export\Pdf\Documents\Planning;

use App\Export\Pdf\Configs\GameLineConfig;
use App\Export\Pdf\Configs\GamesConfig;
use App\Export\Pdf\Documents\PlanningDocument as PdfPlanningDocument;
use App\Export\Pdf\Line\Horizontal;
use App\Export\Pdf\Page as ToernooiPdfPage;
use App\Export\Pdf\Pages;
use App\Export\Pdf\Point;
use App\Export\PdfProgress;
use App\ImagePathResolver;
use App\ImageSize;
use FCToernooi\Tournament;
use Sports\Game;
use Sports\Poule;
use Sports\Round\Number as RoundNumber;
use Sports\Structure;

/**
 * @psalm-suppress PropertyNotSetInConstructor
 */
class GamesPerPouleDocument extends PdfPlanningDocument
{
    public function __construct(
        Tournament $tournament,
        Structure $structure,
        ImagePathResolver $imagePathResolver,
        PdfProgress $progress,
        float $maxSubjectProgress,
        GamesConfig $gamesConfig,
        GameLineConfig $gameLineConfig
    ) {
        parent::__construct(
            $tournament,
            $structure,
            $imagePathResolver,
            $progress,
            $maxSubjectProgress,
            $gamesConfig,
            $gameLineConfig
        );
    }

    protected function renderCustom(): void
    {
        $this->drawPlanningPerPoule($this->structure->getFirstRoundNumber());
    }

    protected function drawPlanningPerPoule(RoundNumber $roundNumber): void
    {
        $poules = array_filter($roundNumber->getPoules(), function (Poule $poule): bool {
            return $poule->needsRanking();
        });
        foreach ($poules as $poule) {
            $title = $this->getStructureNameService()->getPouleName($poule, true);
            $page = $this->createPagePlanning($roundNumber, $title);
            $y = $page->drawHeader($this->getTournament()->getName(), $title);
            $page->setGameFilter(
                function (Game $game) use ($poule): bool {
                    return $game->getPoule() === $poule;
                }
            );
            $horLine = new Horizontal(new Point(ToernooiPdfPage::PAGEMARGIN, $y), $page->getDisplayWidth());
            $this->drawPlanningPerFieldOrPouleHelper($roundNumber, $page, $horLine, false);
        }

        $nextRoundNumber = $roundNumber->getNext();
        if ($nextRoundNumber !== null) {
            $this->drawPlanningPerPoule($nextRoundNumber);
        }
    }
}
