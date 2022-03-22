<?php

declare(strict_types=1);

namespace App\Export\Pdf\Document;

use App\Export\Pdf\Document as PdfDocument;
use App\Export\Pdf\Page\LockerRoomLabel as LockerRoomLabelPage;
use App\Export\Pdf\Page\LockerRooms as LockerRoomsPage;
use FCToernooi\LockerRoom;
use Zend_Pdf_Page;

/**
 * @psalm-suppress PropertyNotSetInConstructor
 */
class LockerRooms extends PdfDocument
{
    protected function fillContent(): void
    {
        $page = $this->createLockerRoomsPage();
        $page->draw();
        $this->drawLockerRoomLabels(array_values($this->getTournament()->getLockerRooms()->toArray()));
    }

    protected function createLockerRoomsPage(): LockerRoomsPage
    {
        $page = new LockerRoomsPage($this, Zend_Pdf_Page::SIZE_A4);
        $page->setFont($this->getFont(), $this->getFontHeight());
        $this->pages[] = $page;
        return $page;
    }

    protected function createLockerRoomLabelPage(LockerRoom $lockerRoom): LockerRoomLabelPage
    {
        $page = new LockerRoomLabelPage($this, Zend_Pdf_Page::SIZE_A4, $lockerRoom);
        $page->setFont($this->getFont(), $this->getFontHeight());
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
            while (count($competitors) > 0) {
                $page = $this->createLockerRoomLabelPage($lockerRoom);
                $page->draw($competitors);
            }
        }
    }
}
