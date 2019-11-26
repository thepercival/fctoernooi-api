<?php

namespace App\Export\Excel;

use App\Export\Document as ExportDocument;
use App\Export\TournamentConfig;
use FCToernooi\Tournament;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpParser\Node\Name;
use Voetbal\NameService;
use Voetbal\Planning\Service as PlanningService;
use Voetbal\Structure;
use PhpOffice\PhpSpreadsheet\Spreadsheet as SpreadsheetBase;
use App\Export\Excel\Worksheet\Structure as StructureSheet;
use App\Export\Excel\Worksheet\Indeling as IndelingSheet;
use App\Export\Excel\Worksheet\Planning as PlanningSheet;

class Spreadsheet extends SpreadsheetBase
{
    use ExportDocument;

    const INDEX_STRUCTURE = 0;
    const INDEX_INDELING = 1;
    const INDEX_PLANNING = 2;

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

        // if( $this->config->getStructure() ) { always do this
            $indelingSheet = new IndelingSheet($this);
            $indelingSheet->draw();

            $structureSheet = new StructureSheet($this);
            $structureSheet->draw();
        // }
        if( $this->config->getPoulePivotTables() ) {
//            list( $page, $nY ) = $this->createPagePoulePivotTables();
//            $this->drawPoulePivotTables( $this->structure->getFirstRoundNumber(), $page, $nY );
        }
        // if( $this->config->getPlanning() ) {
            $planningSheet = new PlanningSheet($this);
            $selfRefereesAssigned = $this->areSelfRefereesAssigned();
            $planningSheet->setSelfRefereesAssigned($selfRefereesAssigned);
            $planningSheet->setRefereesAssigned($this->areRefereesAssigned());
            $planningSheet->draw();
        // }
        if( $this->config->getRules() ) {

        }
        if( $this->config->getGamenotes() ) {
//            $this->drawGamenotes();
        }
        if( $this->config->getGamesperpoule() ) {
//            $this->drawPlanningPerPoule( $this->structure->getFirstRoundNumber() );
        }
        if( $this->config->getGamesperfield() ) {
//            $this->drawPlanningPerField( $this->structure->getFirstRoundNumber() );
        }
        if( $this->config->getQRCode() ) {
//            $page = $this->createPageQRCode();
//            $page->draw();
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

    public function getCellForName( Place $place ): string {

    }
}