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
            $this->memcached->set($this->name, $startProgress);
        } else {
            $this->getProgress();
        }
    }

    protected function validate(float $progress): void
    {
        if ($progress < 0.0 || $progress > 100.0) {
            throw new \Exception('pdf-progress out of bounds', E_ERROR);
        }
    }

    public function getProgress(): float
    {
        /** @var float|false $progress */
        $progress = $this->memcached->get($this->name);
        if ($progress === false) {
            throw new \Exception('pdf-progress not found in cache', E_ERROR);
        }
        return $progress;
    }

    public function addProgression(float $progress): float
    {
        $totalProgress = $this->getProgress() + $progress;
        $this->validate($totalProgress);
        $this->memcached->set($this->name, $totalProgress);
        return $totalProgress;
    }

    public function finish(): void
    {
        $this->memcached->set($this->name, 100.0);
    }

    public function isFinished(): bool
    {
        return $this->getProgress() === 100.0;
    }
}
