<?php
/**
 * Created by PhpStorm.
 * User: coen
 * Date: 7-1-19
 * Time: 9:54
 */

namespace FCToernooi;

use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;

class QRService
{
    public function __construct()
    {
    }

    public function writeToPng(Tournament $tournament, string $qrCodeText, int $imgWidth): string
    {
        $path = $this->getDirectory() . DIRECTORY_SEPARATOR . $tournament->getId() . '-' . $imgWidth . '.png';
        if (file_exists($path)) {
            return $path;
        }
        // Create a basic QR code
        $qrCode = new QrCode($qrCodeText);
        $qrCode->setSize($imgWidth);

        // Set advanced options
        $qrCode->setWriterByName('png');
        // $qrCode->setMargin(10);
        $qrCode->setEncoding('UTF-8');
        $qrCode->setErrorCorrectionLevel(new ErrorCorrectionLevel(ErrorCorrectionLevel::HIGH));
        //$qrCode->setForegroundColor(['r' => 0, 'g' => 0, 'b' => 0, 'a' => 0]);
        //$qrCode->setBackgroundColor(['r' => 255, 'g' => 255, 'b' => 255, 'a' => 0]);
        //$qrCode->setLogoSize(150, 200);
        // $qrCode->setRoundBlockSize(true);
        // $qrCode->setValidateResult(false);
        // $qrCode->setWriterOptions(['exclude_xml_declaration' => true]);

        // Directly output the QR code
        // header('Content-Type: '.$qrCode->getContentType());
        // echo $qrCode->writeString();

        // Save it to a file

        $qrCode->writeFile($path);
        return $path;
    }

    public function writeToJpg(Tournament $tournament, string $qrCodeText, int $imgWidth): string
    {
        $path = $this->getDirectory() . DIRECTORY_SEPARATOR . $tournament->getId() . '-' . $imgWidth . '.jpg';
        if (file_exists($path)) {
            return $path;
        }

        $pngPath = $this->writeToPng($tournament, $qrCodeText, $imgWidth);

        $image = imagecreatefrompng($pngPath);
        imagejpeg($image, $path);
        imagedestroy($image);
        return $path;
    }

    protected function getDirectory()
    {
        $dirname = sys_get_temp_dir() . DIRECTORY_SEPARATOR . "fctoernooiqrcode";
        if (!file_exists($dirname)) {
            mkdir($dirname, 0777);
        }
        return $dirname;
    }
}
