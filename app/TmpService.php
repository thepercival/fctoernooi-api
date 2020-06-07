<?php
/**
 * Created by PhpStorm.
 * User: coen
 * Date: 30-1-17
 * Time: 12:48
 */

namespace App;

use PHPMailer\PHPMailer\PHPMailer;
use Psr\Log\LoggerInterface;

final class TmpService
{
    /**
     * @var string
     */
    private $path;

    public function __construct()
    {
        $this->path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . "fctoernooi";
    }

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
