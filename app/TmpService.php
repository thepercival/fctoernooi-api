<?php

declare(strict_types=1);

namespace App;

use DateTimeImmutable;

final class TmpService
{
    private string $path;

    public function __construct()
    {
        $this->path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . "fctoernooi";
    }

    /**
     * @param list<string> $subDirs
     * @param string|null $file
     * @return string
     */
    public function getPath(array $subDirs, string $file = null): string
    {
        $path = $this->path;
        if (!file_exists($path)) {
            mkdir($path, 0777);
        }

        foreach ($subDirs as $subDir) {
            $path .= DIRECTORY_SEPARATOR . $subDir;
            if (!file_exists($path)) {
                mkdir($path, 0777);
            }
        }
        if ($file === null) {
            return $path . DIRECTORY_SEPARATOR;
        }
        return $path . DIRECTORY_SEPARATOR . $file;
    }

    /**
     * @param list<string> $subDirs
     * @param string $fileName
     * @return bool
     */
    public function removeFile(array $subDirs, string $fileName): bool
    {
        $path = $this->getPath($subDirs, $fileName);
        return file_exists($path) && unlink($path);
    }

    /**
     * @param string $dir
     * @param DateTimeImmutable $expireDateTime
     */
    public function removeOldFiles(string $dir, DateTimeImmutable $expireDateTime): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $dh = opendir($dir);
        if ($dh === false) {
            return;
        }

        while (($file = readdir($dh)) !== false) {
            $file = $dir . '/' . $file;
            if (!is_file($file)) {
                continue;
            }

            if (filemtime($file) < $expireDateTime->getTimestamp()) {
                unlink($file);
            }
        }
        closedir($dh);
    }
}

