<?php

declare(strict_types=1);

namespace App;

use App\ImageService\Entity;
use Exception;
use FCToernooi\Competitor;
use FCToernooi\Tournament;
use GdImage;
use Psr\Http\Message\UploadedFileInterface;
use Selective\Config\Configuration;
use App\ImageService\Entity as ImageEntity;

class ImageService
{
    protected const LOGO_ASPECTRATIO_THRESHOLD = 0.34;

    public const COMPETITOR_IMAGE_FOLDER = 'competitor';

    public function __construct(private Configuration $config)
    {
        $this->config = $config;
    }



    public function processImage(string $imageName, UploadedFileInterface $logostream, string $pathPostfix): string
    {
        if ($logostream->getError() === UPLOAD_ERR_INI_SIZE) {
            throw new Exception(
                "het plaatje mag maximaal \"" . ini_get("upload_max_filesize") . "\" groot zijn",
                E_ERROR
            );
        }

        $extension = $this->getExtensionFromStream($logostream);

        $localPath = $this->config->getString('www.apiurl-localpath') . $pathPostfix;

        $newImagePath = $localPath . $imageName . '.' . $extension;

        $logostream->moveTo($newImagePath);

        if( strtolower($extension) !== 'svg') {
            $source_properties = getimagesize($newImagePath);
            if ($source_properties === false) {
                throw new \Exception("could not read img dimensions", E_ERROR);
            }
            $image_type = $source_properties[2];

            if ($image_type == IMAGETYPE_JPEG) {
                $image_resource_id = imagecreatefromjpeg($newImagePath);
                if ($image_resource_id instanceof GdImage) {
                    $target_layer = $this->resize($image_resource_id, $source_properties[0], $source_properties[1]);
                    imagejpeg($target_layer, $newImagePath);
                }
            } elseif ($image_type == IMAGETYPE_GIF) {
                $image_resource_id = imagecreatefromgif($newImagePath);
                if ($image_resource_id instanceof GdImage) {
                    $target_layer = $this->resize($image_resource_id, $source_properties[0], $source_properties[1]);
                    imagegif($target_layer, $newImagePath);
                }
            } elseif ($image_type == IMAGETYPE_PNG) {
                $image_resource_id = imagecreatefrompng($newImagePath);
                if ($image_resource_id instanceof GdImage) {
                    $target_layer = $this->resize($image_resource_id, $source_properties[0], $source_properties[1]);
                    imagepng($target_layer, $newImagePath);
                }
            }
        }

        return $extension;
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

    /**
     * @param GdImage $image_resource_id
     * @param int $width
     * @param int $height
     * @return GdImage
     */
    private function resize(GdImage $image_resource_id, int $width, int $height): GdImage
    {
        $target_height = 200;
        if ($height === $target_height) {
            return $image_resource_id;
        }
        $thressHold = self::LOGO_ASPECTRATIO_THRESHOLD;
        $aspectRatio = $width / $height;

        $target_width = $width - (($height - $target_height) * $aspectRatio);
        if ($target_width < ($target_height * (1 - $thressHold))) {
            $target_width = $target_height * (1 - $thressHold);
        } elseif ($target_width > ($target_height * (1 + $thressHold))) {
            $target_width = $target_height * (1 + $thressHold);
        }
        return $this->resizeHelper($image_resource_id, $width, $height, (int)$target_width, 200);
        /*else if( $height < $target_height ) { // make image larger
            $target_width = $width - (( $height - $target_height ) * $aspectRatio );
            $new_image_resource_id = $this->>resizeHelper($image_resource_id,$width,$height,$target_width,200)
        }*/
    }

    /**
     * @param GdImage $image_resource_id
     * @param int $width
     * @param int $height
     * @param int $target_width
     * @param int $target_height
     * @return GdImage
     */
    private function resizeHelper(
        GdImage $image_resource_id,
        int $width,
        int $height,
        int $target_width,
        int $target_height
    ): GdImage {
        $target_layer = imagecreatetruecolor($target_width, $target_height);
        if (!($target_layer instanceof GdImage)) {
            throw new \Exception('could not create image', E_ERROR);
        }
        /** @psalm-suppress InvalidArgument */
        imagecopyresampled(
            $target_layer,
            $image_resource_id,
            0,
            0,
            0,
            0,
            $target_width,
            $target_height,
            $width,
            $height
        );
        /** @var GdImage $target_layer */
        return $target_layer;
    }



//    public function getLocalPath(ImageEntity $imageEntity): string {
//        return realpath(
//            $this->config->getString('www.apiurl-localpath') . DIRECTORY_SEPARATOR
//            . $imageEntity->value
//        ) . '/';
//    }

    public function copyCompetitorImage(
        Competitor $fromCompetitor, Competitor $newCompetitor): bool {
        // fromPath
        // $extension = $this->getExtensionFromStream($logostream);

        $pathPostfix = $this->config->getString('images.competitors.pathpostfix');
        $localFolder = $this->config->getString('www.apiurl-localpath') . $pathPostfix;
        $localFileName = $imageName . '.' . $extension;
        $imagePath = $localFolder . $localFileName;

      //   $localPath = $this->getLocalPath(ImageEntity::Competitor) . $fromCompetitor->getId();
      //   $this->config->getString('www.apiurl-localpath') . $pathPostfix;
        // toPath
    }
}
