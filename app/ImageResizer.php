<?php

declare(strict_types=1);

namespace App;

use App\ImageService\Entity;
use FCToernooi\Competitor;
use FCToernooi\Sponsor;
use FCToernooi\Tournament;
use GdImage;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Log\LoggerInterface;
use Selective\Config\Configuration;
use App\ImageService\Entity as ImageEntity;

class ImageResizer
{
    protected ImagePathResolver $pathResolver;

    public function __construct(Configuration $config, private LoggerInterface $logger)
    {
        $this->pathResolver = new ImagePathResolver($config);
    }

    public function addMissingResizeImages( Sponsor|Competitor|Tournament $object, string $extension ): void {
        $this->resizeAndSaveImages($object, false, $extension );
    }

    public function addResizeImagesFromUpload(Sponsor|Competitor|Tournament $object, string $extension ): void {
        $this->resizeAndSaveImages($object, true, $extension );
    }

    private function resizeAndSaveImages(Sponsor|Competitor|Tournament $object, bool $removeResizeImages, string $extension ): void
    {
        $imagePath = $this->pathResolver->getPath($object, null, $extension);
        if (!is_readable($imagePath)) {
            $this->logger->warning('image ' . $imagePath . ' not readable');
            return;
        }

        $source_properties = getimagesize($imagePath);
        if ($source_properties === false) {
            throw new \Exception("could not read img dimensions", E_ERROR);
        }
        $image_width = $source_properties[0];
        $image_height = $source_properties[1];
        $image_type = $source_properties[2];

        foreach( $this->getImageResizeProps() as $imageProps ) {

            $targetHeight = $imageProps->getHeight();

            $resizedImagePath = $this->pathResolver->getPath($object, $imageProps, $extension);

            if (is_readable($resizedImagePath) && $removeResizeImages) {
                unlink($resizedImagePath);
            }

            if ($image_type == IMAGETYPE_JPEG) {
                $image_resource_id = imagecreatefromjpeg($imagePath);
                if ($image_resource_id instanceof GdImage) {
                    $target_layer = $this->resize($image_resource_id, $image_width, $image_height, $targetHeight);
                    imagejpeg($target_layer, $resizedImagePath);
                    chgrp($resizedImagePath, 'www-data');
                }
            } elseif ($image_type == IMAGETYPE_GIF) {
                $image_resource_id = imagecreatefromgif($imagePath);
                if ($image_resource_id instanceof GdImage) {
                    $target_layer = $this->resize($image_resource_id, $image_width, $image_height, $targetHeight);
                    imagegif($target_layer, $resizedImagePath);
                    chgrp($resizedImagePath, 'www-data');
                }
            } elseif ($image_type == IMAGETYPE_PNG) {
                $image_resource_id = imagecreatefrompng($imagePath);
                if ($image_resource_id instanceof GdImage) {
                    $target_layer = $this->resize($image_resource_id, $image_width, $image_height, $targetHeight);
                    imagepng($target_layer, $resizedImagePath);
                    chgrp($resizedImagePath, 'www-data');
                }
            }
        }
    }

    /**
     * @param GdImage $image_resource_id
     * @param int $width
     * @param int $height
     * @return GdImage
     */
    private function resize(GdImage $image_resource_id, int $width, int $height, int $target_height): GdImage
    {
        if ($height === $target_height) {
            return $image_resource_id;
        }
        $thressHold = ImageService::LOGO_ASPECTRATIO_THRESHOLD;
        $aspectRatio = $width / $height;

        $target_width = $width - (($height - $target_height) * $aspectRatio);
        if ($target_width < ($target_height * (1 - $thressHold))) {
            $target_width = $target_height * (1 - $thressHold);
        } elseif ($target_width > ($target_height * (1 + $thressHold))) {
            $target_width = $target_height * (1 + $thressHold);
        }
        return $this->resizeHelper($image_resource_id, $width, $height, (int)$target_width, $target_height);
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
        $gdImage = imagecreatetruecolor($target_width, $target_height);
        if (!($gdImage instanceof GdImage)) {
            throw new \Exception('could not create image', E_ERROR);
        }

        imagesavealpha($gdImage, true);
        $trans_colour = imagecolorallocatealpha($gdImage, 0, 0, 0, 127);
        imagefill($gdImage, 0, 0, $trans_colour);

        /** @psalm-suppress InvalidArgument */
        imagecopyresampled(
            $gdImage,
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
        return $gdImage;
    }



//    public function getLocalPath(ImageEntity $imageEntity): string {
//        return realpath(
//            $this->config->getString('www.apiurl-localpath') . DIRECTORY_SEPARATOR
//            . $imageEntity->value
//        ) . '/';
//    }

    /**
     * @return list<ImageProps>
     */
    public function getImageResizeProps(): array {
        return [
            $this->getImageProps(ImageSize::Small),
            $this->getImageProps(ImageSize::Normal)
        ];
    }

    public function getImageProps(ImageSize $imageSize): ImageProps {
        return new ImageProps( ImageProps::Suffix . $imageSize->value, $imageSize);
    }
}
