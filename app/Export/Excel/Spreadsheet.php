<?php

namespace App\Export\Excel;

use App\Export\Document as ExportDocument;
use App\Export\TournamentConfig;
use FCToernooi\Tournament;
use Voetbal\Planning\Service as PlanningService;
use Voetbal\Structure;
use PhpOffice\PhpSpreadsheet\Spreadsheet as SpreadsheetBase;

class Spreadsheet extends SpreadsheetBase
{
    use ExportDocument;

    public function __construct(
        Tournament $tournament,
        Structure $structure,
        TournamentConfig $config
    )
    {
        parent::__construct();
        $this->tournament = $tournament;
        $this->structure = $structure;
        $this->planningService = new PlanningService();
        $this->config = $config;
    }

    public function fillContents() {
        $sheet = $this->getActiveSheet();
        $sheet->setCellValue('A1', 'Hello World !');
    }

}