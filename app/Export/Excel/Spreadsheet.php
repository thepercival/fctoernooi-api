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
        $this->setMetadata();
        // maak sowieso de structuur sheet
        // maak sowieso het wedstrijdschema sheet
        // daarna kun je met links werken om onder andere namen en standen te vullen!
        $sheet = $this->getActiveSheet();
        $sheet->setCellValue('A1', 'Hello World !');
    }

    protected function setMetadata() {
        $this->getProperties()
            ->setCreator("FCToernooi")
            ->setLastModifiedBy("Coen Dunnink")
            ->setTitle("Office 2007 XLSX Toernooi-document")
            ->setSubject("Office 2007 XLSX Toernooi-")
            ->setDescription("This document is created by fctoernooi.nl")
            ->setKeywords("office 2007 openxml")
            ->setCategory("toernooi");
    }

}