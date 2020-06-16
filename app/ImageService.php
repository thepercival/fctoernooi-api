<?php
/**
 * Created by PhpStorm.
 * User: coen
 * Date: 30-1-17
 * Time: 12:48
 */

namespace App;

use Exception;
use Psr\Http\Message\UploadedFileInterface;
use Selective\Config\Configuration;

class ImageService
{
    /**
     * @var Configuration
     */
    protected $config;

    protected const LOGO_ASPECTRATIO_THRESHOLD = 0.34;

    public function __construct(Configuration $config)
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
        $image_type = $source_properties[2];
        if ($image_type == IMAGETYPE_JPEG) {
            $image_resource_id = imagecreatefromjpeg($newImagePath);
            $target_layer = $this->fn_resize($image_resource_id, $source_properties[0], $source_properties[1]);
            imagejpeg($target_layer, $newImagePath);
        } elseif ($image_type == IMAGETYPE_GIF) {
            $image_resource_id = imagecreatefromgif($newImagePath);
            $target_layer = $this->fn_resize($image_resource_id, $source_properties[0], $source_properties[1]);
            imagegif($target_layer, $newImagePath);
        } elseif ($image_type == IMAGETYPE_PNG) {
            $image_resource_id = imagecreatefrompng($newImagePath);
            $target_layer = $this->fn_resize($image_resource_id, $source_properties[0], $source_properties[1]);
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

    private function fn_resize($image_resource_id, $width, $height)
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
        return $this->fn_resize_helper($image_resource_id, $width, $height, $target_width, 200);
        /*else if( $height < $target_height ) { // make image larger
            $target_width = $width - (( $height - $target_height ) * $aspectRatio );
            $new_image_resource_id = fn_resize_helper($image_resource_id,$width,$height,$target_width,200)
        }*/
    }

    private function fn_resize_helper($image_resource_id, $width, $height, $target_width, $target_height)
    {
        $target_layer = imagecreatetruecolor($target_width, $target_height);
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
        return $target_layer;
    }
}
