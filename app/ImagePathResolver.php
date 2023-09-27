<?php

declare(strict_types=1);

namespace App;

use FCToernooi\Competitor;
use FCToernooi\Sponsor;
use Selective\Config\Configuration;

class ImagePathResolver
{
    public function __construct(private Configuration $config)
    {

    }



    public function getPath(Sponsor|Competitor $object, ImageSize|null $imageSize, string $logoExtension ): string {
        $fileName = $this->getFileName($object, $imageSize, $logoExtension);

        $localFolder = $this->config->getString('www.apiurl-localpath') . 'images/';

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

    public function getBackupPath(Sponsor|Competitor $object, ImageSize|null $imageSize, string $extension ): string {
        $fileName = $this->getFileName($object, $imageSize, $extension);
        $localFolder = $this->config->getString('images.backuppath') . '/';

        return $localFolder . $this->getPathSuffix($object)  . $fileName;
    }

    public function getPathSuffix(Sponsor|Competitor $object): string {
        return ($object instanceof Sponsor) ? Sponsor::IMG_FOLDER . '/'  : Competitor::IMG_FOLDER . '/';
    }

    protected function getFileName(Sponsor|Competitor $object, ImageSize|null $imageSize, string $logoExtension ): string {
        if( $imageSize === null ) {
            return ((string)$object->getId()) .  '.' . $logoExtension;
        }
        return ((string)$object->getId()) . $imageSize->getSuffix() .  '.' . $logoExtension;
    }

//    protected function getFileNameWithoutExtension(Sponsor|Competitor $object, ImageSize|null $imageSize ): string|null {
//        if( $imageSize === null ) {
//            return ((string)$object->getId());
//        }
//        return ((string)$object->getId()) . $imageSize->getSuffix();
//    }
}
