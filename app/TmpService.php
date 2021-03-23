<?php

declare(strict_types=1);

namespace App;

use PHPMailer\PHPMailer\PHPMailer;
use Psr\Log\LoggerInterface;

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

        return $path . DIRECTORY_SEPARATOR . $file;
    }
}
