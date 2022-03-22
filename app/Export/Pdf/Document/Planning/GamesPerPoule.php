<?php

declare(strict_types=1);

namespace App\Export\Pdf\Document\Planning;

use App\Export\Pdf\Document\Planning as PdfPlanningDocument;
use Sports\Game;
use Sports\Round\Number as RoundNumber;

/**
 * @psalm-suppress PropertyNotSetInConstructor
 */
class GamesPerPoule extends PdfPlanningDocument
{
    protected function fillContent(): void
    {
        $this->drawPlanningPerPoule($this->structure->getFirstRoundNumber());
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
}
