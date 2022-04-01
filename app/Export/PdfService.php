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

    protected string $exportSecret;
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
        $this->exportSecret = $config->getString('renderer.export_secret');
        $this->tmpSubDir = ['pdf'];
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
    ): string {
        $tournamentId = (string)$tournament->getId();

        if ($this->inProgress($tournamentId)) {
            throw new Exception('er loopt nog een pdf-aanvraag voor dit toernooi', E_ERROR);
        }
        $this->reset($tournament);

        $hash = $this->createHash($tournamentId);
        $this->memcached->set($this->getHashKey($tournamentId), $hash, self::CACHE_EXPIRATION);
        new PdfProgress($this->getProgressKey($tournamentId), $this->memcached, self::CREATE_PERCENTAGE);
        foreach ($subjects as $subject) {
            $pdfQueueService->sendCreatePdf(
                new PdfQueueService\CreateMessage($tournament, $subject, count($subjects))
            );
        }
        return $hash;
    }

    private function reset(Tournament $tournament): void
    {
        $tournamentId = (string)$tournament->getId();

        // remove cache items
        $this->emptyCache($tournamentId);

        // clean files from tmp
        $this->tmpService->removeFile($this->tmpSubDir, $this->getFileName($tournament));
        foreach (PdfSubject::cases() as $subject) {
            $this->tmpService->removeFile($this->tmpSubDir, $this->getSubjectFileName($tournamentId, $subject));
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
     * @return Zend_Pdf
     * @throws \Zend_Pdf_Exception
     */
    public function mergePdfs(Tournament $tournament): Zend_Pdf
    {
        $tournamentId = (string)$tournament->getId();
        $pdfDoc = new Zend_Pdf();
        foreach (PdfSubject::cases() as $subject) {
            $path = $this->getSubjectPath($tournamentId, $subject);
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
        $pdfDoc->save($this->getPath($tournament));
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
        $path = $this->getPath($tournament);
        $pdfDoc = \Zend_Pdf::load($path);
        $this->reset($tournament);
        return $pdfDoc;
    }

    private function emptyCache(string $tournamentId): void
    {
        $this->memcached->delete($this->getHashKey($tournamentId));
        $this->memcached->delete($this->getProgressKey($tournamentId));
    }

    public function getPath(Tournament $tournament): string
    {
        return $this->tmpService->getPath($this->tmpSubDir, $this->getFileName($tournament));
    }

    public function getFileName(Tournament $tournament): string
    {
        $name = $tournament->getCompetition()->getLeague()->getName();
        $fileName = preg_replace("/[^a-zA-Z0-9]+/", '', $name);
        return 'fctoernooi-' . $fileName . '.pdf';
    }

    private function getHashKey(string $tournamentId): string
    {
        return 'fct-pdf-hash-' . $tournamentId;
    }

    private function getHash(string $tournamentId): string
    {
        $hash = $this->memcached->get($this->getHashKey($tournamentId));
        if ($hash === false) {
            throw new \Exception('de pdf is na het aanmaken, eenmalig opvraagbaar', E_ERROR);
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

    public function getProgressPerSubject(int $totalNrOfSubjects): float
    {
        if ($totalNrOfSubjects === 0) {
            throw new \Exception('totalNrOfSubjects must be at least one', E_ERROR);
        }
        $percentage = 100 - (self::CREATE_PERCENTAGE + self::MERGE_PERCENTAGE);
        return $percentage / $totalNrOfSubjects;
    }

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
        return $this->tmpService->getPath($this->tmpSubDir, $fileName);
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
}
