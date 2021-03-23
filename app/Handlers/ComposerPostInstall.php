<?php

declare(strict_types=1);

namespace App\Handlers;

use Composer\Script\Event;

class ComposerPostInstall
{
    public static function execute(Event $event): int
    {
        if ($event->isDevMode()) {
            echo "devMode is enabled, no post-install-executed for fctoernooi" . PHP_EOL;
        }
        $pathPrefix = realpath(__DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR. "..") . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR;
        $routerCache = $pathPrefix . 'router';
        if (file_exists($routerCache)) {
            unlink($routerCache);
        }

        $doctrineProxies = $pathPrefix . 'proxies/';
        if (is_dir($doctrineProxies)) {
            static::rrmdir($doctrineProxies);
        }
        mkdir($doctrineProxies);

        $serializer = $pathPrefix . 'serializer';
        if (is_dir($serializer)) {
            static::rrmdir($serializer);
        }
        mkdir($serializer);
        chmod($serializer, 0775);
        chgrp($serializer, 'www-data');

        $serializer .= '/metadata';
        if (is_dir($serializer)) {
            static::rrmdir($serializer);
        }
        mkdir($serializer);
        chmod($serializer, 0775);
        chgrp($serializer, 'www-data');
        return 0;
    }

    public static function rrmdir(string $src): void
    {
        $dir = opendir($src);
        while (false !== ($file = readdir($dir))) {
            if (($file != '.') && ($file != '..')) {
                $full = $src . '/' . $file;
                if (is_dir($full)) {
                    static::rrmdir($full);
                } else {
                    unlink($full);
                }
            }
        }
        closedir($dir);
        rmdir($src);
    }
}
