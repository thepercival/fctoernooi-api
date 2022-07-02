<?php

declare(strict_types=1);

namespace App\Export\Pdf\Document\Planning;

use App\Export\Pdf\Configs\GameLineConfig;
use App\Export\Pdf\Configs\GamesConfig;
use App\Export\Pdf\Document\Planning as PdfPlanningDocument;
use App\Export\Pdf\Line\Horizontal;
use App\Export\Pdf\Page;
use App\Export\Pdf\Point;
use App\Export\PdfProgress;
use FCToernooi\Tournament;
use Sports\Game;
use Sports\Round\Number as RoundNumber;
use Sports\Structure;

/**
 * @psalm-suppress PropertyNotSetInConstructor
 */
class GamesPerPoule extends PdfPlanningDocument
{
    public function __construct(
        protected Tournament $tournament,
        protected Structure $structure,
        protected string $url,
        protected PdfProgress $progress,
        protected float $maxSubjectProgress,
        GamesConfig $gamesConfig,
        GameLineConfig $gameLineConfig
    ) {
        parent::__construct(
            $tournament,
            $structure,
            $url,
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
        $poules = $roundNumber->getPoules();
        foreach ($poules as $poule) {
            $title = $this->getStructureNameService()->getPouleName($poule, true);
            $page = $this->createPagePlanning($roundNumber, $title);
            $y = $page->drawHeader($this->getTournament()->getName(), $title);
            $page->setGameFilter(
                function (Game $game) use ($poule): bool {
                    return $game->getPoule() === $poule;
                }
            );
            $horLine = new Horizontal(new Point(Page::PAGEMARGIN, $y), $page->getDisplayWidth());
            $this->drawPlanningPerFieldOrPouleHelper($roundNumber, $page, $horLine, false);
        }

        $nextRoundNumber = $roundNumber->getNext();
        if ($nextRoundNumber !== null) {
            $this->drawPlanningPerPoule($nextRoundNumber);
        }
    }
}
