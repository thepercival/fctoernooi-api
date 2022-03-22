<?php

declare(strict_types=1);

namespace App\Export;

use App\Export\Pdf\DocumentFactory as PdfDocumentFactory;
use App\TmpService;
use FCToernooi\Tournament;
use Memcached;
use Psr\Log\LoggerInterface;
use Selective\Config\Configuration;
use Sports\Structure;
use Zend_Pdf;

final class PdfService
{
    protected string $exportSecret;
    protected string $wwwUrl;

    public function __construct(
        Configuration $config,
        protected TmpService $tmpService,
        protected PdfDocumentFactory $factory,
        protected Memcached $memcached,
        protected LoggerInterface $logger
    ) {
        $this->wwwUrl = $config->getString('www.wwwurl');
        $this->exportSecret = $config->getString('renderer.export_secret');
    }

    /**
     * @param Tournament $tournament
     * @param Structure $structure
     * @param non-empty-list<PdfSubject> $subjects
     * @param bool $await
     * @return string
     * @throws \Zend_Pdf_Exception
     */
    public function createASyncOnDisk(Tournament $tournament, Structure $structure, array $subjects, bool $await): string
    {
        $tournamentId = (string)$tournament->getId();

        $hash = $this->createHash($tournamentId);
        $progressKey = $this->getProgressKey($hash, $tournamentId);
        $this->memcached->set($progressKey, 0);

        $subjectsKey = $this->getSubjectsKey($hash, $tournamentId);
        $this->memcached->set($subjectsKey, PdfSubject::sum($subjects));

        foreach ($subjects as $subject) {
            $pdfDoc = $this->factory->createDocument($tournament, $structure, $subject);
            $tournamentId = (string)$tournament->getId();
            $path = $this->getPath($tournamentId, [$subject]);
            $pdfDoc->save($path);
        }
        return $hash;
    }


    /**
     * @param Tournament $tournament
     * @param list<PdfSubject> $subjects
     * @return Zend_Pdf
     * @throws \Zend_Pdf_Exception
     */
    public function getFromDisk(string $tournamentId, string $hash): Zend_Pdf
    {
        $pdfDoc = new Zend_Pdf();
        $subjects = $this->getSubjectsValue($tournamentId, $hash);
        foreach (PdfSubject::toFilteredArray($subjects) as $subject) {
            $path = $this->getPath($tournamentId, [$subject]);
            $subjectPdfDoc = \Zend_Pdf::load($path);
            foreach ($subjectPdfDoc->pages as $subjectPdf) {
                $pdfDoc->pages[] = $subjectPdf;
            }
        }

        return $pdfDoc;
    }

//    /**
//     * @param Tournament $tournament
//     * @param Structure $structure
//     * @param list<PdfSubject> $subjects
//     * @return void
//     * @throws \Zend_Pdf_Exception
//     */
//    public function createOnDisk(Tournament $tournament, Structure $structure, array $subjects): void
//    {
//        foreach ($subjects as $subject) {
//            $this->factory->createDocument($tournament, $structure, $subject);
//        }
//
    ////        $tournamentId = (string)$tournament->getId();
    ////        $pdf = new PdfDocument($tournament, $structure, $subjects, $this->wwwUrl);
    ////        $path = $this->getPath($tournamentId, $subjects);
    ////        $pdf->save($path);
//    }

    public function isStarted(string $tournamentId, string $hash): bool
    {
        return $this->getProgressValue($tournamentId, $hash) > 0;
    }

    private function getProgressKey(string $tournamentId, string $hash): string
    {
        return 'pdf-progress-' . $tournamentId . '-' . $hash;
    }

    public function getProgressValue(string $tournamentId, string $hash): int
    {
        $key = $this->getProgressKey($tournamentId, $hash);

        /** @var int|false $cacheValue */
        $cacheValue = $this->memcached->get($key);
        if ($cacheValue === false) {
            return -1;
        }
        return $cacheValue;
    }

    private function getSubjectsKey(string $tournamentId, string $hash): string
    {
        return 'pdf-subjects-' . $tournamentId . '-' . $hash;
    }

    public function getSubjectsValue(string $tournamentId, string $hash): int
    {
        $key = $this->getSubjectsKey($tournamentId, $hash);
        /** @var int|false $cacheValue */
        $cacheValue = $this->memcached->get($key);
        if ($cacheValue === false) {
            throw new \Exception('could not get path from cache', E_ERROR);
        }
        return $cacheValue;
    }



    private function createHash(string $tournamentId): string
    {
        $decoded = $tournamentId . $this->exportSecret . (new \DateTimeImmutable())->getTimestamp();
        return hash('sha1', $decoded);
    }

    /**
     * @param string $tournamentId
     * @param list<PdfSubject> $subjects
     * @return string
     */
    public function getPath(string $tournamentId, array $subjects): string
    {
        $fileName =  $this->getFileName($tournamentId, $subjects);
        return $this->tmpService->getPath(['pdf'], $fileName);
    }

    /**
     * @param string $tournamentId
     * @param list<PdfSubject> $subjects
     * @return string
     */
    public function getFileName(string $tournamentId, array $subjects): string
    {
        $suffix = $this->getFileSuffix($subjects);
        return ('fctoernooi-' . $tournamentId . ($suffix !== null ? '-' . $suffix : '')) . '.pdf';
    }

    /**
     * @param list<PdfSubject> $subjects
     * @return string|null
     */
    private function getFileSuffix(array $subjects): string|null
    {
        $subject = reset($subjects);
        if ($subject === false || count($subjects) > 1) {
            return null;
        }
        switch ($subject) {
            case PdfSubject::GameNotes:
                return 'wedstrijdbrieven';
            case PdfSubject::Structure:
                return 'opzet-en-indeling';
            case PdfSubject::GamesPerPoule:
                return 'wedstrijden-per-poule';
            case PdfSubject::GamesPerField:
                return 'wedstrijden-per-veld';
            case PdfSubject::Planning:
                return 'wedstrijdplanning';
            case PdfSubject::PoulePivotTables:
                return 'poule-draaitabellen';
            case PdfSubject::QrCode:
                return 'qrcode-en-link';
            case PdfSubject::LockerRooms:
                return 'kleedkamers';
        }
        return null;
    }

    /**
     * @param string $tournamentId
     * @param string $hash
     * @return string
     */
    public function getFileNameFromCache(string $tournamentId, string $hash): string
    {
        $subjects = $this->getSubjectsValue($tournamentId, $hash);
        return $this->getFileName($tournamentId, PdfSubject::toFilteredArray($subjects));
    }
}
