<?php

declare(strict_types=1);

namespace App;

use FCToernooi\Competitor;
use FCToernooi\Sponsor;
use FCToernooi\Tournament;
use Selective\Config\Configuration;

class ImagePathResolver
{
    private string $wwwApiurlLocalpath;
    private string $localFolder;
    private string $wwwUrl;

    public function __construct(Configuration $config)
    {
        $this->wwwApiurlLocalpath = $config->getString('www.apiurl-localpath');
        $this->localFolder = $config->getString('images.backuppath') . '/';
        $this->wwwUrl = $config->getString('www.wwwurl');
    }

    public function getWwwUrl(): string {
        return $this->wwwUrl;
    }

    public function getPath(Sponsor|Competitor|Tournament $object, ImageProps|null $imageProps, string $logoExtension ): string {
        $fileName = $this->getFileName($object, $imageProps, $logoExtension);

        $localFolder = $this->wwwApiurlLocalpath . 'images/';

        return $localFolder . $this->getPathSuffix($object)  . $fileName;
    }

//    public function getPathWithoutExtension(Sponsor|Competitor $object, ImageSize|null $imageSize ): string|null {
//        $fileNameWithoutExtension = $this->getFileNameWithoutExtension($object, $imageSize);
//        if( $fileNameWithoutExtension === null ) {
//            return null;
//        }
//
//        $localFolder = $this->config->getString('www.apiurl-localpath') . 'images/';
//
//        return $localFolder . $this->getPathSuffix($object)  . $fileNameWithoutExtension;
//    }

    public function getBackupPath(Sponsor|Competitor|Tournament $object, ImageProps|null $imageProps, string $extension ): string {
        $fileName = $this->getFileName($object, $imageProps, $extension);
        return $this->localFolder . $this->getPathSuffix($object)  . $fileName;
    }

    public function getPathSuffix(Sponsor|Competitor|Tournament $object): string {
        if( $object instanceof Sponsor) {
            return Sponsor::IMG_FOLDER . '/';
        } else if( $object instanceof Competitor) {
            return Competitor::IMG_FOLDER . '/';
        }
        return Tournament::IMG_FOLDER . '/';
    }

    protected function getFileName(Sponsor|Competitor|Tournament $object, ImageProps|null $imageProps, string $logoExtension ): string {
        if( $imageProps === null ) {
            return ((string)$object->getId()) .  '.' . $logoExtension;
        }
        return ((string)$object->getId()) . $imageProps->getSuffix() .  '.' . $logoExtension;
    }

//    protected function getFileNameWithoutExtension(Sponsor|Competitor $object, ImageSize|null $imageSize ): string|null {
//        if( $imageSize === null ) {
//            return ((string)$object->getId());
//        }
//        return ((string)$object->getId()) . $imageSize->getSuffix();
//    }
}
