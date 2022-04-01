<?php

declare(strict_types=1);

namespace App\Export;

use Memcached;

final class PdfProgress
{
    public function __construct(
        protected string $name,
        protected Memcached $memcached,
        float $startProgress = null
    ) {
        if ($startProgress !== null) {
            $this->validate($startProgress);
            $this->setProgress($startProgress);
        } else {
            $this->getProgress();
        }
    }

    protected function validate(float $progress): void
    {
        if ($progress < 0.0 || $progress > 100.0) {
            throw new \Exception('pdf-progress(' . round($progress, 1) . ') out of bounds', E_ERROR);
        }
    }

    public function getProgress(): float
    {
        /** @var string|false $progress */
        $progress = $this->memcached->get($this->name);
        if ($progress === false) {
            throw new \Exception('pdf-progress not found in cache', E_ERROR);
        }
        return (float)$progress;
    }

    private function setProgress(float $progress): void
    {
        $this->memcached->set($this->name, '' . $progress, PdfService::CACHE_EXPIRATION);
    }

    public function addProgression(float $progress): float
    {
        $totalProgress = $this->getProgress() + $progress;
        $this->validate($totalProgress);
        $this->setProgress($totalProgress);
        return $totalProgress;
    }

    public function finish(): void
    {
        $this->setProgress(100.0);
    }

    public function hasFinished(): bool
    {
        return $this->getProgress() === 100.0;
    }
}
