<?php

namespace App\Export\Excel;

use App\Export\Document as ExportDocument;
use App\Export\TournamentConfig;
use FCToernooi\Tournament;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpParser\Node\Name;
use Voetbal\Game;
use Voetbal\NameService;
use Voetbal\Planning\Service as PlanningService;
use Voetbal\Structure;
use PhpOffice\PhpSpreadsheet\Spreadsheet as SpreadsheetBase;
use App\Export\Excel\Worksheet\Structure as StructureSheet;
use App\Export\Excel\Worksheet\Indeling as IndelingSheet;
use App\Export\Excel\Worksheet\Planning as PlanningSheet;
use App\Export\Excel\Worksheet\Planning\All as PlanningAllSheet;
use App\Export\Excel\Worksheet\Planning\PerPoule as PlanningPerPouleSheet;
use App\Export\Excel\Worksheet\Planning\PerField as PlanningPerFieldSheet;
use App\Export\Excel\Worksheet\PoulePivotTables as PoulePivotTablesSheet;
use App\Export\Excel\Worksheet\QRCode as QRCodeSheet;

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

        $this->removeSheetByIndex( 0 );

        if( $this->config->getStructure() ) {
            $indelingSheet = new IndelingSheet($this);
            $indelingSheet->draw();

            $structureSheet = new StructureSheet($this);
            $structureSheet->draw();
        }
        if( $this->config->getPoulePivotTables() ) {
            $poulePivotTablesSheet = new PoulePivotTablesSheet($this);
            $poulePivotTablesSheet->draw();
        }
        if( $this->config->getPlanning() ) {
            $planningSheet = new PlanningAllSheet($this);
            $planningSheet->setSelfRefereesAssigned($this->areSelfRefereesAssigned());
            $planningSheet->setRefereesAssigned($this->areRefereesAssigned());
            $planningSheet->draw();
        }
        if( $this->config->getRules() ) {

        }
        if( $this->config->getGamenotes() ) {
//            $this->drawGamenotes();
        }
        if( $this->config->getGamesperpoule() ) {
            $planningSheet = new PlanningPerPouleSheet($this);
            $planningSheet->setSelfRefereesAssigned($this->areSelfRefereesAssigned());
            $planningSheet->setRefereesAssigned($this->areRefereesAssigned());
            $planningSheet->draw();
        }
        if( $this->config->getGamesperfield() ) {
            $planningSheet = new PlanningPerFieldSheet($this);
            $planningSheet->setSelfRefereesAssigned($this->areSelfRefereesAssigned());
            $planningSheet->setRefereesAssigned($this->areRefereesAssigned());
            $planningSheet->draw();
        }
        if( $this->config->getQRCode() ) {
            $qrcodeSheet = new QRCodeSheet($this);
            $qrcodeSheet->draw();
        }
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