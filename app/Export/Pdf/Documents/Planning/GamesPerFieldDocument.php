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
use App\ImageProps;
use App\ImageSize;
use FCToernooi\Tournament;
use Sports\Game;
use Sports\Round\Number as RoundNumber;
use Sports\Structure;

/**
 * @psalm-suppress PropertyNotSetInConstructor
 */
class GamesPerFieldDocument extends PdfPlanningDocument
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
        $this->drawPlanningPerField($this->structure->getFirstRoundNumber());
    }

    protected function drawPlanningPerField(RoundNumber $roundNumber): void
    {
        $fields = $this->getTournament()->getCompetition()->getFields();
        foreach ($fields as $field) {
            $title = 'veld ' . (string)$field->getName();
            $page = $this->createPagePlanning($roundNumber, $title);
            $y = $page->drawHeader($this->getTournament()->getName(),  $title);
            $page->setGameFilter(
                function (Game $game) use ($field): bool {
                    return $game->getField() === $field;
                }
            );
            $horLine = new Horizontal(new Point(ToernooiPdfPage::PAGEMARGIN, $y), $page->getWidth());
            $this->drawPlanningPerFieldOrPouleHelper($roundNumber, $page, $horLine, true);
        }
    }
}
