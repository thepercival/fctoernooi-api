<?php

declare(strict_types=1);

namespace App;

use App\ImageService\Entity;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use FCToernooi\Competitor;
use FCToernooi\Sponsor;
use FCToernooi\Tournament;
use GdImage;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Log\LoggerInterface;
use Selective\Config\Configuration;
use App\ImageService\Entity as ImageEntity;

class ImageService
{
    public const LOGO_ASPECTRATIO_THRESHOLD = 0.34;
    protected ImageResizer $resizer;
    protected ImagePathResolver $pathResolver;

    public function __construct(Configuration $config, private LoggerInterface $logger)
    {
        $this->resizer = new ImageResizer($config, $logger);
        $this->pathResolver = new ImagePathResolver($config);
    }

    public function removeImages(Sponsor|Competitor $object, string $extension): void {
        $imagePath = $this->pathResolver->getPath($object, null, $extension);
        if (!file_exists($imagePath)) {
            return;
        }
        unlink($imagePath);

        if( $object->getLogoExtension() === 'svg') {
            return;
        }

        foreach( $this->resizer->getImageResizeHeights() as $imageSize ) {
            $resizedImagePath = $this->pathResolver->getPath($object, $imageSize, $extension);
            unlink($resizedImagePath);
        }

    }

    public function processUploadedImage(Sponsor|Competitor $object, UploadedFileInterface $logostream): string|null
    {
        if ($logostream->getError() === UPLOAD_ERR_INI_SIZE) {
            throw new Exception(
                "het plaatje mag maximaal \"" . ini_get("upload_max_filesize") . "\" groot zijn",
                E_ERROR
            );
        }

        $imgPath = $this->saveUploadStream($object, $logostream);
        $this->logger->info( 'het plaatje is opgeslagen onder "' . $imgPath . '"' );

        $extension = $this->getExtensionFromStream($logostream);
        if( strtolower($extension) !== 'svg') {
            $this->resizer->addResizeImagesFromUpload($object, $extension);
        }

        return $extension;
    }

    private function saveUploadStream(Sponsor|Competitor $object, UploadedFileInterface $logostream): string {
        $extension = $this->getExtensionFromStream($logostream);

        $imagePath = $this->pathResolver->getPath($object,null, $extension);
        $logostream->moveTo($imagePath);
        return $imagePath;
    }

    private function getExtensionFromStream(UploadedFileInterface $logostream): string
    {
        if ($logostream->getClientMediaType() === "image/jpeg") {
            return "jpg";
        }
        if ($logostream->getClientMediaType() === "image/png") {
            return "png";
        }
        if ($logostream->getClientMediaType() === "image/gif") {
            return "gif";
        }
        if ($logostream->getClientMediaType() === "image/svg+xml" || $logostream->getClientMediaType() === "image/svg") {
            return "svg";
        }
        throw new \Exception("alleen jpg, png em gif zijn toegestaan", E_ERROR);
    }


//    public function getLocalPath(ImageEntity $imageEntity): string {
//        return realpath(
//            $this->config->getString('www.apiurl-localpath') . DIRECTORY_SEPARATOR
//            . $imageEntity->value
//        ) . '/';
//    }

    public function copyImages(Sponsor|Competitor $fromObject, Sponsor|Competitor $newObject): bool {
        $logoExtension = $fromObject->getLogoExtension();
        if( $logoExtension === null) {
            return false;
        }

        $allCopiesOK = true;
        $imgSizes = array_merge([null], $this->resizer->getImageResizeHeights());
        foreach( $imgSizes as $imageSize) {
            $fromImagePath = $this->pathResolver->getPath($fromObject, $imageSize, $logoExtension);
            $newImagePath = $this->pathResolver->getPath($newObject, $imageSize, $logoExtension);
            if( !copy($fromImagePath, $newImagePath ) ) {
                $allCopiesOK = false;
            }
        }
        return $allCopiesOK;
    }

    public function backupImages(Sponsor|Competitor $object, EntityManagerInterface|null $syncDbWithDisk): bool {
        $logoExtension = $object->getLogoExtension();
        if( $logoExtension === null) {
            return false;
        }
        if( $logoExtension !== 'svg') {
            $this->resizer->addMissingResizeImages($object, $logoExtension);
        }

        $allCopiesOK = true;
        $imgSizes = array_merge([null], $this->resizer->getImageResizeHeights());
        foreach( $imgSizes as $imageSize) {
            $imagePath = $this->pathResolver->getPath($object, $imageSize, $logoExtension);
            if( $logoExtension === 'svg' && $imageSize !== null ) {
                continue;
            }
            if (!is_readable($imagePath)) {
                $this->logger->warning('sponsor-image ' . $imagePath . ' not readable');
                if ($syncDbWithDisk !== null ) {
                    $object->setLogoExtension(null);
                    $syncDbWithDisk->persist($object);
                    $syncDbWithDisk->flush();
                    $this->logger->warning('sponsor-extension updated to NULL');
                }
                $allCopiesOK = false;
                continue;
            }

            $backupImagePath = $this->pathResolver->getBackupPath($object, $imageSize, $logoExtension);
            if (file_exists($backupImagePath)) {
                unlink($backupImagePath);
                $this->logger->info('backup-sponsor-image ' . $backupImagePath . ' removed');
            }

            if( !copy($imagePath, $backupImagePath ) ) {
                $allCopiesOK = false;
            } else {
                $this->logger->info('backup-sponsor-image ' . $backupImagePath . ' backed up');
            }
        }
        return $allCopiesOK;
    }





//    protected function copyImage(Competitor $fromCompetitor, Competitor $newCompetitor): bool {
//
//        $localFolder = $this->config->getString('www.apiurl-localpath') . 'images/' . Competitor::IMG_FOLDER . '/';
//        $logoExtension = $fromCompetitor->getLogoExtension();
//
//        if( $logoExtension === null) {
//            return false;
//        }
//        $localFromFileName = ((string)$fromCompetitor->getId()) . '.' . $logoExtension;
//        $fromImagePath = $localFolder . $localFromFileName;
//
//        $localNewFileName = ((string)$newCompetitor->getId()) . '.' . $logoExtension;
//        $newImagePath = $localFolder . $localNewFileName;
//
//        return copy($fromImagePath, $newImagePath );
//    }


}
