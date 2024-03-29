<?php

declare(strict_types=1);

namespace App\Handlers;

use Composer\Script\Event;

class ComposerPostInstall
{
    public static function execute(Event $event): int
    {
//        if ($event->isDevMode()) {
//            echo "devMode is enabled, no post-install-executed for fctoernooi" . PHP_EOL;
//        }
        $pathPrefix = realpath(
                __DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . ".."
            ) . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR;
        $routerCache = $pathPrefix . 'router';
        if (file_exists($routerCache)) {
            echo "router cached emptied" . PHP_EOL;
            unlink($routerCache);
        } else {
            echo "no router cache found" . PHP_EOL;
        }

        $doctrineProxies = $pathPrefix . 'proxies/';
        if (is_dir($doctrineProxies)) {
            static::rrmdir($doctrineProxies);
        }
        mkdir($doctrineProxies);
        chmod($doctrineProxies, 0775);
        chgrp($doctrineProxies, 'www-data');

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
        if ($dir === false) {
            echo "could not open dir : " . $src . PHP_EOL;
            return;
        }
        while ($file = readdir($dir)) {
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
