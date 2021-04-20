<?php
declare(strict_types=1);

namespace App;

use Exception;
use GdImage;
use Psr\Http\Message\UploadedFileInterface;
use Selective\Config\Configuration;

class ImageService
{
    protected const LOGO_ASPECTRATIO_THRESHOLD = 0.34;

    public function __construct(private Configuration $config)
    {
        $this->config = $config;
    }

    public function process(string $imageName, UploadedFileInterface $logostream): string
    {
        if ($logostream->getError() === UPLOAD_ERR_INI_SIZE) {
            throw new Exception(
                "het plaatje mag maximaal \"" . ini_get("upload_max_filesize") . "\" groot zijn",
                E_ERROR
            );
        }

        $extension = $this->getExtensionFromStream($logostream);

        $localPath = $this->config->getString('www.apiurl-localpath') . $this->config->getString(
            'images.sponsors.pathpostfix'
        );

        $newImagePath = $localPath . $imageName . '.' . $extension;

        $logostream->moveTo($newImagePath);

        $source_properties = getimagesize($newImagePath);
        if($source_properties === false ) {
            throw new \Exception("could not read img dimensions", E_ERROR);
        }
        $image_type = $source_properties[2];
        if ($image_type == IMAGETYPE_JPEG) {
            /** @var GdImage|false $image_resource_id */
            $image_resource_id = imagecreatefromjpeg($newImagePath);
            /** @var GdImage|false|resource $target_layer */
            $target_layer = $this->resize($image_resource_id, $source_properties[0], $source_properties[1]);
            imagejpeg($target_layer, $newImagePath);
        } elseif ($image_type == IMAGETYPE_GIF) {
            /** @var GdImage|false $image_resource_id */
            $image_resource_id = imagecreatefromgif($newImagePath);
            /** @var GdImage|false|resource $target_layer */
            $target_layer = $this->resize($image_resource_id, $source_properties[0], $source_properties[1]);
            imagegif($target_layer, $newImagePath);
        } elseif ($image_type == IMAGETYPE_PNG) {
            /** @var GdImage|false $image_resource_id */
            $image_resource_id = imagecreatefrompng($newImagePath);
            /** @var GdImage|false|resource $target_layer */
            $target_layer = $this->resize($image_resource_id, $source_properties[0], $source_properties[1]);
            imagepng($target_layer, $newImagePath);
        }
        $urlPath = $this->config->getString('www.apiurl') . $this->config->getString('images.sponsors.pathpostfix');
        return $urlPath . $imageName . '.' . $extension;
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
        throw new \Exception("alleen jpg, png em gif zijn toegestaan", E_ERROR);
    }

    /**
     * @param false|GdImage $image_resource_id
     * @param int $width
     * @param int $height
     * @return false|GdImage
     */
    private function resize(false|GdImage $image_resource_id, int $width, int $height): false|GdImage
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
    ): GdImage
    {
        $target_layer = imagecreatetruecolor($target_width, $target_height);
        if (!($target_layer instanceof GdImage)) {
            throw new \Exception('could not create image',E_ERROR);
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
}
