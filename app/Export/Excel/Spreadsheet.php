<?php

declare(strict_types=1);

namespace App\Export\Excel;

use App\Export\Document as ExportDocument;
use App\Export\TournamentConfig;
use FCToernooi\Tournament;
use FCToernooi\Tournament\ExportConfig;
use Sports\Structure;
use PhpOffice\PhpSpreadsheet\Spreadsheet as SpreadsheetBase;
use App\Export\Excel\Worksheet\Structure as StructureSheet;
use App\Export\Excel\Worksheet\Indeling as IndelingSheet;
use App\Export\Excel\Worksheet\Planning\All as PlanningAllSheet;
use App\Export\Excel\Worksheet\Planning\PerPoule as PlanningPerPouleSheet;
use App\Export\Excel\Worksheet\Planning\PerField as PlanningPerFieldSheet;
use App\Export\Excel\Worksheet\PoulePivotTables as PoulePivotTablesSheet;
use App\Export\Excel\Worksheet\Gamenotes as GamenotesSheet;
use App\Export\Excel\Worksheet\QRCode as QRCodeSheet;
use App\Export\Excel\Worksheet\LockerRooms as LockerRoomsSheet;

class Spreadsheet extends SpreadsheetBase
{
    use ExportDocument;

    const INDEX_INDELING = 0;
    const INDEX_STRUCTURE = 1;
    const INDEX_PLANNING = 2;
    const INDEX_GAMENOTES = 3;
    const INDEX_PLANNING_PER_POULE = 4;
    const INDEX_PLANNING_PER_FIELD = 5;
    const INDEX_POULEPIVOTTABLES = 6;
    const INDEX_QRCODE = 7;
    const INDEX_LOCKERROOMS = 8;

    public function __construct(
        Tournament $tournament,
        Structure $structure,
        protected int $subjects,
        string $url
    ) {
        parent::__construct();
        $this->tournament = $tournament;
        $this->structure = $structure;
        $this->url = $url;
    }

    public function fillContents()
    {
        $this->setMetadata();

        $this->removeSheetByIndex(0);

        if (($this->subjects & ExportConfig::Structure) === ExportConfig::Structure) {
            $indelingSheet = new IndelingSheet($this);
            $indelingSheet->draw();

            $structureSheet = new StructureSheet($this);
            $structureSheet->draw();
        }

        if (($this->subjects & ExportConfig::Planning) === ExportConfig::Planning) {
            $planningSheet = new PlanningAllSheet($this);
            $planningSheet->setSelfRefereesAssigned($this->areSelfRefereesAssigned());
            $planningSheet->setRefereesAssigned($this->areRefereesAssigned());
            $planningSheet->draw();
        }
        if (($this->subjects & ExportConfig::GameNotes) === ExportConfig::GameNotes) {
            $gamenotesSheet = new GamenotesSheet($this);
            $gamenotesSheet->draw();
        }
        if (($this->subjects & ExportConfig::GamesPerPoule) === ExportConfig::GamesPerPoule) {
            $planningSheet = new PlanningPerPouleSheet($this);
            $planningSheet->setSelfRefereesAssigned($this->areSelfRefereesAssigned());
            $planningSheet->setRefereesAssigned($this->areRefereesAssigned());
            $planningSheet->draw();
        }
        if (($this->subjects & ExportConfig::GamesPerField) === ExportConfig::GamesPerField) {
            $planningSheet = new PlanningPerFieldSheet($this);
            $planningSheet->setSelfRefereesAssigned($this->areSelfRefereesAssigned());
            $planningSheet->setRefereesAssigned($this->areRefereesAssigned());
            $planningSheet->draw();
        }
        if (($this->subjects & ExportConfig::PoulePivotTables) === ExportConfig::PoulePivotTables) {
            $poulePivotTablesSheet = new PoulePivotTablesSheet($this);
            $poulePivotTablesSheet->draw();
        }
        if (($this->subjects & ExportConfig::QrCode) === ExportConfig::QrCode) {
            $qrcodeSheet = new QRCodeSheet($this);
            $qrcodeSheet->draw();
        }
        if (($this->subjects & ExportConfig::LockerRooms) === ExportConfig::LockerRooms) {
            $lockerRoomsSheet = new LockerRoomsSheet($this);
            $lockerRoomsSheet->draw();
        }
        $this->setActiveSheetIndex(0);
    }

    protected function setMetadata()
    {
        $this->getProperties()
            ->setCreator("FCToernooi")
            ->setLastModifiedBy("Coen Dunnink")
            ->setTitle("Office 2007 XLSX Toernooi-document")
            ->setSubject("Office 2007 XLSX Toernooi-")
            ->setDescription("This document is created by FCToernooi")
            ->setKeywords("office 2007 openxml")
            ->setCategory("toernooi");
    }
}
