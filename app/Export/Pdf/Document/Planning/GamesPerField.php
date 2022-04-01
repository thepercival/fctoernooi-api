<?php

declare(strict_types=1);

namespace App\Export\Pdf\Document\Planning;

use App\Export\Pdf\Document\Planning as PdfPlanningDocument;
use Sports\Game;
use Sports\Round\Number as RoundNumber;

/**
 * @psalm-suppress PropertyNotSetInConstructor
 */
class GamesPerField extends PdfPlanningDocument
{
    protected function fillContent(): void
    {
        $this->drawPlanningPerField($this->structure->getFirstRoundNumber());
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
            $this->drawPlanningPerFieldOrPouleHelper($roundNumber, $page, $y, true);
        }
    }
}
