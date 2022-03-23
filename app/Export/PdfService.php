<?php

declare(strict_types=1);

namespace App\Export;

use App\Export\Pdf\DocumentFactory as PdfDocumentFactory;
use App\QueueService\Pdf as PdfQueueService;
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
     * @param PdfQueueService $pdfQueueService
     * @return string
     * @throws \Zend_Pdf_Exception
     */
    public function createASyncOnDisk(
        Tournament $tournament,
        Structure $structure,
        array $subjects,
        PdfQueueService $pdfQueueService,
    ): string {
        $tournamentId = (string)$tournament->getId();

        // hash opslaan en progress opslaan.


        // $subjectsKey = $this->getSubjectsKey($hash, $tournamentId);
        // $this->memcached->set($subjectsKey, PdfSubject::sum($subjects));
        $hash = $this->createHash($tournamentId);
        $this->memcached->set($this->getHashKey($tournamentId), $hash);
        new PdfProgress($this->getProgressKey($tournamentId), $this->memcached, 1);
        foreach ($subjects as $subject) {
            $pdfQueueService->sendCreatePdf($tournament, $subject, count($subjects));
//            $pdfDoc = $this->factory->createDocument($tournament, $structure, $subject);
//            $tournamentId = (string)$tournament->getId();
//            $path = $this->getPath($tournamentId, [$subject]);
//            $pdfDoc->save($path);
        }

        return $hash;
    }


    /**
     * @param Tournament $tournament
     * @return Zend_Pdf
     * @throws \Zend_Pdf_Exception
     */
    public function mergePdfs(string $tournamentId): Zend_Pdf
    {
        $pdfDoc = new Zend_Pdf();
        foreach (PdfSubject::cases() as $subject) {
            $path = $this->getSubjectPath($tournamentId, $subject);
            if (!file_exists($path)) {
                continue;
            }
            $subjectPdfDoc = \Zend_Pdf::load($path);
            foreach ($subjectPdfDoc->pages as $subjectPdf) {
                $pdfDoc->pages[] = $subjectPdf;
            }
        }
        if (count($pdfDoc->pages) === 0) {
            throw new \Exception('pdf has no pages', E_ERROR);
        }
        // @TODO CDK finish progres
        return $pdfDoc;
    }

    /**
     * @param Tournament $tournament
     * @return Zend_Pdf
     * @throws \Zend_Pdf_Exception
     */
    public function getPdf(Tournament $tournament): Zend_Pdf
    {
        $pdfDoc = new Zend_Pdf();
        $tournamentId = (string)$tournament->getId();
        foreach (PdfSubject::cases() as $subject) {
            $path = $this->getSubjectPath($tournamentId, $subject);
            if (!file_exists($path)) {
                continue;
            }
            $subjectPdfDoc = \Zend_Pdf::load($path);
            foreach ($subjectPdfDoc->pages as $subjectPdf) {
                $pdfDoc->pages[] = $subjectPdf;
            }
        }
        if (count($pdfDoc->pages) === 0) {
            throw new \Exception('pdf has no pages', E_ERROR);
        }
        return $pdfDoc;
    }

    public function getPath(Tournament $tournament): string
    {
        return $this->tmpService->getPath(['pdf'], $this->getFileName($tournament));
    }

    public function getFileName(Tournament $tournament): string
    {
        // TODOCDK
        $fileName = 'TODOCDK' . $tournament->getCompetition()->getLeague()->getName();
        return $fileName;
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

    public function isStarted(string $tournamentId): bool
    {
        return $this->memcached->get($this->getProgressKey($tournamentId)) !== false;
    }

    private function getHashKey(string $tournamentId): string
    {
        return 'fct-pdf-hash-' . $tournamentId;
    }

    private function getHash(string $tournamentId): string
    {
        $hash = $this->memcached->get($this->getHashKey($tournamentId));
        if ($hash === false) {
            throw new \Exception('pdf-hash not found in cache', E_ERROR);
        }
        return $hash;
    }

    private function getProgressKey(string $tournamentId): string
    {
        return 'fct-pdf-progress-' . $tournamentId;
    }

    public function getProgress(string $tournamentId): PdfProgress
    {
        return new PdfProgress($this->getProgressKey($tournamentId), $this->memcached);
    }

    public function getProgressValue(string $tournamentId): float
    {
        return (new PdfProgress($tournamentId, $this->memcached))->getProgress();
    }

//    private function getSubjectsKey(string $tournamentId, string $hash): string
//    {
//        return 'pdf-subjects-' . $tournamentId . '-' . $hash;
//    }
//
//    public function getSubjectsValue(string $tournamentId, string $hash): int
//    {
//        $key = $this->getSubjectsKey($tournamentId, $hash);
//        /** @var int|false $cacheValue */
//        $cacheValue = $this->memcached->get($key);
//        if ($cacheValue === false) {
//            throw new \Exception('could not get path from cache', E_ERROR);
//        }
//        return $cacheValue;
//    }


    private function createHash(string $tournamentId): string
    {
        $decoded = $tournamentId . $this->exportSecret . (new \DateTimeImmutable())->getTimestamp();
        return hash('sha1', $decoded);
    }

    public function validateHash(Tournament $tournament, string $hash): void
    {
        if ($this->getHash((string)$tournament->getId()) !== $hash) {
            throw new \Exception('de aanvraag is verlopen, vraag een nieuwe pdf aan ', E_ERROR);
        }
    }

    /**
     * @param string $tournamentId
     * @param PdfSubject $subject
     * @return string
     */
    public function getSubjectPath(string $tournamentId, PdfSubject $subject): string
    {
        $fileName = $this->getSubjectFileName($tournamentId, $subject);
        return $this->tmpService->getPath(['pdf'], $fileName);
    }

    /**
     * @param string $tournamentId
     * @param PdfSubject $subject
     * @return string
     */
    public function getSubjectFileName(string $tournamentId, PdfSubject $subject): string
    {
        return 'fctoernooi-' . $tournamentId . '-' . $this->getSubjectFileSuffix($subject) . '.pdf';
    }

    /**
     * @param list<PdfSubject> $subjects
     * @return string
     */
    private function getSubjectFileSuffix(PdfSubject $subject): string
    {
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
        throw new \Exception('unknown subject', E_ERROR);
    }

//    /**
//     * @param string $tournamentId
//     * @param string $hash
//     * @return string
//     */
//    public function getFileNameFromCache(string $tournamentId, string $hash): string
//    {
//        $subjects = $this->getSubjectsValue($tournamentId, $hash);
//        return $this->getFileName($tournamentId, PdfSubject::toFilteredArray($subjects));
//    }
}
