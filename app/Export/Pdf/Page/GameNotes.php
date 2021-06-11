<?php
declare(strict_types=1);

namespace App\Export\Pdf\Page;

use App\Export\Pdf\Document;
use App\Export\Pdf\Page as ToernooiPdfPage;
use FCToernooi\QRService;
use Sports\Score\Config as ScoreConfig;
use Sports\Score\Config\Service as ScoreConfigService;
use FCToernooi\TranslationService;

abstract class GameNotes extends ToernooiPdfPage
{
    public const MAXNROFSCORELINES = 5;

    protected ScoreConfigService $scoreConfigService;
    protected TranslationService $translationService;
    protected QRService $qrService;
    protected string|null $qrCodeUrlPrefix = null;

    public function __construct(Document $document, mixed $param1)
    {
        parent::__construct($document, $param1);
        $this->setLineWidth(0.5);
        $this->scoreConfigService = new ScoreConfigService();
        $this->translationService = new TranslationService();
        $this->qrService = new QRService();
    }

    public function getPageMargin(): float
    {
        return 20;
    }

    public function getHeaderHeight(): float
    {
        return 0;
    }

    protected function getQrCodeUrlPrefix(): string
    {
        if ($this->qrCodeUrlPrefix === null) {
            $this->qrCodeUrlPrefix = $this->getParent()->getUrl() . 'admin/game/' .
                (string)$this->getParent()->getTournament()->getId() .
                '/';
        }
        return $this->qrCodeUrlPrefix;
    }

    protected function getInputScoreConfigDescription(ScoreConfig $firstScoreConfig): string
    {
        $scoreNamePlural = $this->translationService->getScoreNamePlural($firstScoreConfig);
        if ($firstScoreConfig->getMaximum() === 0) {
            return $scoreNamePlural;
        }
        $direction = $this->getDirectionName($firstScoreConfig);
        return $direction . ' ' . $firstScoreConfig->getMaximum() . ' ' . $scoreNamePlural;
    }

    protected function getScoreConfigDescription(ScoreConfig $scoreConfig): string
    {
        $text = '';
        $nextScoreConfig = $scoreConfig->getNext();
        if ($nextScoreConfig !== null && $nextScoreConfig->getEnabled()) {
            if ($nextScoreConfig->getMaximum() === 0) {
                $text .= 'zoveel mogelijk ';
                $text .= $this->translationService->getScoreNamePlural($nextScoreConfig);
            } else {
                $text .= 'eerst bij ';
                $text .= $nextScoreConfig->getMaximum() . ' ';
                $text .= $this->translationService->getScoreNamePlural($nextScoreConfig);
            }
            $text .= ', ' . $scoreConfig->getMaximum() . ' ';
            $text .= $this->translationService->getScoreNamePlural($scoreConfig) . ' per ';
            $text .= $this->translationService->getScoreNameSingular($nextScoreConfig);
        } elseif ($scoreConfig->getMaximum() === 0) {
            $text .= 'zoveel mogelijk ';
            $text .= $this->translationService->getScoreNamePlural($scoreConfig);
        } else {
            $text .= 'eerst bij ';
            $text .= $scoreConfig->getMaximum() . ' ';
            $text .= $this->translationService->getScoreNamePlural($scoreConfig);
        }
        return $text;
    }

    protected function getDirectionName(ScoreConfig $scoreConfig): string
    {
        return $this->translationService->getScoreDirection($scoreConfig->getDirection());
    }
}
