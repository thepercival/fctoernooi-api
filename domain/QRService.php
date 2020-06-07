<?php
/**
 * Created by PhpStorm.
 * User: coen
 * Date: 7-1-19
 * Time: 9:54
 */

namespace FCToernooi;

use App\TmpService;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Voetbal\Game;

class QRService
{
    /**
     * @var TmpService
     */
    protected $tmpService;

    public function __construct()
    {
        $this->tmpService = new TmpService();
    }

    public function writeTournamentToJpg(Tournament $tournament, string $qrCodeText, int $imgWidthPts): string
    {
        $pathWithoutExtension = $this->getPathWihoutExtension($tournament, $imgWidthPts);
        $imgWidthPx = $this->convertPointsToPixels($imgWidthPts);
        return $this->writeToJpg($pathWithoutExtension, $qrCodeText, $imgWidthPx);
    }

    public function writeGameToJpg(Tournament $tournament, Game $game, string $qrCodeText, int $imgWidthPts): string
    {
        $pathWithoutExtension = $this->getPathWihoutExtension($tournament, $imgWidthPts, $game);
        $imgWidthPx = $this->convertPointsToPixels($imgWidthPts);
        return $this->writeToJpg($pathWithoutExtension, $qrCodeText, $imgWidthPx);
    }

    public function convertPointsToPixels(float $pdfPoints): int
    {
        $dpi = 96;
        $inch = $this->convertPdfPointsToInches($pdfPoints);
        $dots = $inch * $dpi;
        return (int)$dots;
    }

    public function convertPdfPointsToInches(float $pdfPoints): float
    {
        // 210 x 297 mm
        // a4 zend 595 x 842
        $mm = $pdfPoints * 210 / 595;
        $mmPerInch = 25.4;
        return $mm / $mmPerInch;
    }

    protected function getPathWihoutExtension(Tournament $tournament, int $imgWidth, Game $game = null): string
    {
        $path = $this->tmpService->getPath(["qrcode"]);
        $path .= "tournament-" . $tournament->getId();
        if ($game !== null) {
            $path .= "-game-" . $game->getId();
        }
        return $path . "-width-" . $imgWidth;
    }

    protected function writeToJpg(string $pathWithoutExtension, string $qrCodeText, int $imgWidthPx): string
    {
        $this->writeToPng($pathWithoutExtension . ".png", $qrCodeText, $imgWidthPx);
        $image = imagecreatefrompng($pathWithoutExtension . ".png");
        imagejpeg($image, $pathWithoutExtension . ".jpg");
        imagedestroy($image);
        return $pathWithoutExtension . ".jpg";
    }

    protected function writeToPng(string $path, string $qrCodeText, int $imgWidthPx)
    {
        if (file_exists($path)) {
            return;
        }
        // Create a basic QR code
        $qrCode = new QrCode($qrCodeText);
        $qrCode->setSize($imgWidthPx);

        // Set advanced options
        $qrCode->setWriterByName('png');
        $qrCode->setMargin(0);
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

        $qrCode->writeFile($path);
    }
}
