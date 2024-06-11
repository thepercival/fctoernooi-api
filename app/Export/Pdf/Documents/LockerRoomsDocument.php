<?php

declare(strict_types=1);

namespace App\Export\Pdf\Documents;

use App\Export\Pdf\Configs\LockerRoomConfig;
use App\Export\Pdf\Configs\LockerRoomLabelConfig;
use App\Export\Pdf\Document as PdfDocument;
use App\Export\Pdf\Pages\LockerRoomLabelPage as LockerRoomLabelPage;
use App\Export\Pdf\Pages\LockerRoomsPage as LockerRoomsPage;
use App\Export\PdfProgress;
use App\ImagePathResolver;
use FCToernooi\LockerRoom;
use FCToernooi\Tournament;
use Sports\Structure;
use Zend_Pdf_Page;

/**
 * @psalm-suppress PropertyNotSetInConstructor
 */
class LockerRoomsDocument extends PdfDocument
{
    public function __construct(
        Tournament $tournament,
        Structure $structure,
        ImagePathResolver $imagePathResolver,
        PdfProgress $progress,
        float $maxSubjectProgress,
        protected LockerRoomConfig $config,
        protected LockerRoomLabelConfig $labelConfig
    ) {
        parent::__construct($tournament, $structure, $imagePathResolver, $progress, $maxSubjectProgress);
    }

    public function getConfig(): LockerRoomConfig
    {
        return $this->config;
    }

    public function getLabelConfig(): LockerRoomLabelConfig
    {
        return $this->labelConfig;
    }


    protected function renderCustom(): void
    {
        $page = $this->createLockerRoomsPage();
        $page->draw();
        $this->drawLockerRoomLabels(array_values($this->getTournament()->getLockerRooms()->toArray()));
    }

    protected function createLockerRoomsPage(): LockerRoomsPage
    {
        $page = new LockerRoomsPage($this, Zend_Pdf_Page::SIZE_A4);
        $page->setFont($this->helper->getTimesFont(), $this->getConfig()->getFontHeight());
        $this->pages[] = $page;
        return $page;
    }

    protected function createLockerRoomLabelPage(LockerRoom $lockerRoom): LockerRoomLabelPage
    {
        $page = new LockerRoomLabelPage($this, Zend_Pdf_Page::SIZE_A4, $lockerRoom);
        $this->pages[] = $page;
        return $page;
    }

    /**
     * @param list<LockerRoom> $lockerRooms
     */
    protected function drawLockerRoomLabels(array $lockerRooms): void
    {
        while ($lockerRoom = array_shift($lockerRooms)) {
            $competitors = array_values($lockerRoom->getCompetitors()->toArray());
            $headerDrawn = false;
            while (count($competitors) > 0 || !$headerDrawn) {
                $page = $this->createLockerRoomLabelPage($lockerRoom);
                $page->draw($competitors);
                $headerDrawn = true;
            }
        }
    }
}
