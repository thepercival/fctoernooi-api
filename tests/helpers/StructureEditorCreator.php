<?php

declare(strict_types=1);

namespace FCToernooiTest\TestHelper;

use Sports\Planning\Config\Service as PlanningConfigService;
use Sports\Structure\Editor as StructureEditor;
use Sports\Competition\Sport\Editor as CompetitionSportEditor;
use SportsHelpers\PlaceRanges;

trait StructureEditorCreator
{
    protected function createStructureEditor(PlaceRanges|null $placeRanges = null): StructureEditor
    {
        $editor = new StructureEditor(new CompetitionSportEditor(), new PlanningConfigService());
        if ($placeRanges !== null) {
            $editor->setPlaceRanges($placeRanges);
        }
        return $editor;
    }
}
