<?php

declare(strict_types=1);

namespace App\Export;

use App\Export\Pdf\DocumentFactory as PdfDocumentFactory;
use App\QueueService\Pdf as PdfQueueService;
use App\TmpService;
use Exception;
use FCToernooi\Tournament;
use Memcached;
use Psr\Log\LoggerInterface;
use Selective\Config\Configuration;
use Zend_Pdf;

final class PdfService
{
    public const CACHE_EXPIRATION = 300;
    public const CREATE_PERCENTAGE = 1;
    public const MERGE_PERCENTAGE = 10;

    protected string $pdfLocalDir;
    protected string $wwwUrl;
    /**
     * @var list<string>
     */
    protected array $tmpSubDir;

    public function __construct(
        Configuration $config,
        protected TmpService $tmpService,
        protected PdfDocumentFactory $factory,
        protected Memcached $memcached,
        protected LoggerInterface $logger
    ) {
        $this->wwwUrl = $config->getString('www.wwwurl');
        $this->tmpSubDir = ['pdf'];
        $this->pdfLocalDir = $config->getString('www.apiurl-localpath') . 'pdf' . DIRECTORY_SEPARATOR;
    }

    /**
     * @param Tournament $tournament
     * @param non-empty-list<PdfSubject> $subjects
     * @param PdfQueueService $pdfQueueService
     * @return string
     * @throws \Zend_Pdf_Exception
     */
    public function createASyncOnDisk(
        Tournament $tournament,
        array $subjects,
        PdfQueueService $pdfQueueService,
    ): string
    {
        $tournamentId = (string)$tournament->getId();
//        $this->reset($tournament);
        if ($this->inProgress($tournamentId)) {
            throw new Exception('er loopt nog een pdf-aanvraag voor dit toernooi', E_ERROR);
        }
        $this->reset($tournament);

//        $hash = $this->createHash($tournamentId);
        $fileName = $this->createFileName($tournament);
        $this->memcached->set($this->getFileNameKey($tournamentId), $fileName, self::CACHE_EXPIRATION);
        new PdfProgress($this->getProgressKey($tournamentId), $this->memcached, self::CREATE_PERCENTAGE);
        foreach ($subjects as $subject) {
            $pdfQueueService->sendCreatePdf(
                new PdfQueueService\CreateMessage($tournament, $fileName, $subject, count($subjects))
            );
        }
        return $fileName;
    }

    private function reset(Tournament $tournament): void
    {
        $tournamentId = (string)$tournament->getId();

        // remove cache items
        $this->emptyCache($tournamentId);

        // clean files from tmp
        $this->tmpService->removeFile($this->tmpSubDir, $this->getTmpPath($tournamentId));
        foreach (PdfSubject::cases() as $subject) {
            $this->tmpService->removeFile($this->tmpSubDir, $this->getTmpSubjectPath($tournamentId, $subject));
        }
    }

    private function inProgress(string $tournamentId): bool
    {
        try {
            return !$this->getProgress($tournamentId)->hasFinished();
        } catch (\Exception $e) {
            return false;
        }
    }

    public function creationCompleted(float $progrssValue): bool
    {
        return round($progrssValue, 6) >= (100 - self::MERGE_PERCENTAGE);
    }

    /**
     * @param Tournament $tournament
     * @param string $fileName
     * @return Zend_Pdf
     * @throws \Zend_Pdf_Exception
     */
    public function mergePdfs(Tournament $tournament, string $fileName): Zend_Pdf
    {
        $tournamentId = (string)$tournament->getId();
        $pdfDoc = new Zend_Pdf();
        foreach (PdfSubject::cases() as $subject) {
            $path = $this->getTmpSubjectPath($tournamentId, $subject);
            if (!file_exists($path)) {
                continue;
            }
            $subjectPdfDoc = \Zend_Pdf::load($path);
            /** @var \Zend_Pdf_Page $subjectPdf */
            foreach ($subjectPdfDoc->pages as $subjectPdf) {
                $pdfDoc->pages[] = clone $subjectPdf;
            }
        }
        if (count($pdfDoc->pages) === 0) {
            throw new \Exception('pdf has no pages', E_ERROR);
        }
        $pdfDoc->save($this->getPublicPath($fileName));
        $this->getProgress($tournamentId)->finish();
        return $pdfDoc;
    }

    /**
     * @param Tournament $tournament
     * @return Zend_Pdf
     * @throws \Zend_Pdf_Exception
     */
    public function getPdfOnce(Tournament $tournament): Zend_Pdf
    {
        $path = $this->getTmpPath((string)$tournament->getId());
        $pdfDoc = \Zend_Pdf::load($path);
        $this->reset($tournament);
        return $pdfDoc;
    }

    private function emptyCache(string $tournamentId): void
    {
        $this->memcached->delete($this->getFileNameKey($tournamentId));
        $this->memcached->delete($this->getProgressKey($tournamentId));
    }

    public function getPublicPath(string $fileName): string
    {
        return $this->pdfLocalDir . $fileName . '.pdf';
    }

    /**
     * @return string
     */
    public function getPublicDir(): string
    {
        return $this->pdfLocalDir;
    }


    public function createFileName(Tournament $tournament): string
    {
        $name = $tournament->getCompetition()->getLeague()->getName();
        $tournamentName = strtolower(preg_replace("/[^a-zA-Z0-9]+/", '', $name));
        $timestamp = (new \DateTimeImmutable())->format('YmdHis');
        return $tournamentName . '-' . $timestamp;
    }

    private function getFileNameKey(string $tournamentId): string
    {
        return 'fct-pdf-filename-' . $tournamentId;
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

    public function getProgressPerSubject(int $totalNrOfSubjects): float
    {
        if ($totalNrOfSubjects === 0) {
            throw new \Exception('totalNrOfSubjects must be at least one', E_ERROR);
        }
        $percentage = 100 - (self::CREATE_PERCENTAGE + self::MERGE_PERCENTAGE);
        return $percentage / $totalNrOfSubjects;
    }

//    /**
//     * @param string $tournamentId
//     * @param PdfSubject $subject
//     * @return string
//     */
//    public function getSubjectPath(string $tournamentId, PdfSubject $subject): string
//    {
//        $fileName = $this->getSubjectFileName($tournamentId, $subject);
//        return $this->tmpService->getPath($this->tmpSubDir, $fileName);
//    }

    /**
     * @param PdfSubject $subject
     * @return string
     */
    public function getTmpDir(): string
    {
        return $this->tmpService->getPath($this->tmpSubDir);
    }

    /**
     * @param string $tournamentId
     * @param PdfSubject $subject
     * @return string
     */
    public function getTmpPath(string $tournamentId): string
    {
        $fileName = $tournamentId;
        return $this->tmpService->getPath($this->tmpSubDir, $fileName . '.pdf');
    }

    /**
     * @param string $tournamentId
     * @param PdfSubject $subject
     * @return string
     */
    public function getTmpSubjectPath(string $tournamentId, PdfSubject $subject): string
    {
        $fileName = $tournamentId . '-' . $this->getTmpSubjectFileSuffix($subject);
        return $this->tmpService->getPath($this->tmpSubDir, $fileName . '.pdf');
    }

    /**
     * @param list<PdfSubject> $subjects
     * @return string
     */
    private function getTmpSubjectFileSuffix(PdfSubject $subject): string
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
}
