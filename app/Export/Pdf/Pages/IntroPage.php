<?php

declare(strict_types=1);

namespace App\Export\Pdf\Pages;

use App\Export\Pdf\Align;
use App\Export\Pdf\Configs\HeaderConfig;
use App\Export\Pdf\Documents\IntroDocument;
use App\Export\Pdf\Documents\RegistrationFormDocument as RegistrationFormDocument;
use App\Export\Pdf\Line\Horizontal as HorizontalLine;
use App\Export\Pdf\Pages;
use App\Export\Pdf\Page as ToernooiPdfPage;
use App\Export\Pdf\Point;
use App\Export\Pdf\Rectangle;
use App\ImageProps;
use App\ImageSize;
use FCToernooi\QRService;
use FCToernooi\Tournament\Rule as TournamentRule;
use Zend_Pdf_Exception;
use Zend_Pdf_Page;
use Zend_Pdf_Resource_Image;
use Zend_Pdf_Resource_ImageFactory;

/**
 * @template-extends ToernooiPdfPage<IntroDocument>
 */
class IntroPage extends ToernooiPdfPage
{
    protected QRService $qrService;
    public const int FieldsetHeaderPadding = 4;
    public const int FieldsetTextMargin = 2;

    public function __construct(IntroDocument $document, mixed $param1)
    {
        parent::__construct($document, $param1);
        $this->setLineWidth(0.5);
        $this->qrService = new QRService();
    }

    public function draw(): bool
    {
        $y = $this->drawHeader(
            $this->parent->getTournament()->getName(),
            'introductie',
            new HeaderConfig()
        );

        $config = $this->parent->getConfig();
        // $rowHeight = ;

        $fieldsetMargin = static::PAGEMARGIN;
        $xStart = ToernooiPdfPage::PAGEMARGIN;
        $y -= $fieldsetMargin;

        $logoPath = $this->parent->getTournamentLogoPath(ImageSize::Small);
        $rightWidth = 200;
        $leftWidth = $this->getDisplayWidth() - ( $rightWidth + $fieldsetMargin );

        $theme = $this->parent->getTheme();

        $rules = array_map( function(TournamentRule $rule): string {
            return $rule->getText();
        }, $this->parent->getTournament()->getRules()->toArray() );

        // 1 MAAK LINKSBOVEN EEN FIELDSET, HOOGTE OP BASIS VAN AANTAL REGELS
        $dummy = 1;
        $rectangle = new Rectangle(new HorizontalLine(new Point($xStart, $y), $leftWidth), $dummy);
        $yFieldsetBottom = $this->drawFieldset(
            $this->parent->getTournament()->getIntro(),
            $rectangle,
            $theme,
            'welkom',
            $config->getIntroFieldsetTextConfig()
        );

        $xImage = $rectangle->getRight()->getX() + $fieldsetMargin;
        $yRight = $y + 1;
        $locationStartY = $yRight;
        // 2 MAAK RECHTSBOVEN EEN LOGO
        try {
            /** @var Zend_Pdf_Resource_Image $img */
            $img = Zend_Pdf_Resource_ImageFactory::factory($logoPath);
            $logoSize = ImageSize::Normal->value;
            $imgRectangle = new Rectangle(new HorizontalLine(new Point($xImage, $yRight), $logoSize), -$logoSize);
            $this->drawImageExt($img, $imgRectangle);
            $locationStartY = $yRight - $logoSize;
        } catch ( Zend_Pdf_Exception $e ) {
            // $this->logger->warning($e->getMessage());
        }

        // 3 LOCATIE - GEGEVENS (ALS QR-CODE? KAN LATER)
        $location = $this->parent->getTournament()->getLocation();
        if( $location !== null ) {
            if ( !$this->locationIsCoordinate($location)) {
                // draw location text
            }
            $url = $this->getMapsUrl($location);
            $qrImgWidth = $rightWidth;
            $qrPath = $this->qrService->writeLocationToJpg($this->parent->getTournament(), $url, $qrImgWidth);
            /** @var Zend_Pdf_Resource_Image $img */
            $img = \Zend_Pdf_Resource_ImageFactory::factory($qrPath);
//            $xLeft = self::PAGEMARGIN + ($this->getDisplayWidth() / 2) - ($imgWidth / 2);
//            $this->drawImage($img, $xLeft, $y - $imgWidth, $xLeft + $imgWidth, $y);
            $imgRectangle = new Rectangle(new HorizontalLine(new Point($xImage, $locationStartY), $qrImgWidth), -$qrImgWidth);
            $this->drawImageExt($img, $imgRectangle);
        }

        // 4 MAAK HUISREGELS OP EEN RIJ,
        if( count($rules) > 0 ) {
            $fieldsetListHeight = $this->getFieldsetListHeight(
                array_values($rules),
                $leftWidth,
                $config->getRulesFieldsetListConfig()
            );
            $fitsOnPage = $yFieldsetBottom - ($fieldsetMargin + $fieldsetListHeight) > self::PAGEMARGIN;
            if( $fitsOnPage )
            {
                $rectangle = new Rectangle($rectangle->getBottom()->moveY(-$fieldsetMargin), $dummy);
                $this->drawFieldsetList(
                    array_values($rules),
                    $rectangle,
                    $theme,
                    'huisregels',
                    $config->getRulesFieldsetListConfig());
            } else {
                return false;
            }

        }
        return true;
    }


    private function getMapsUrl(string $location): string {

        if ($this->locationIsCoordinate($location) ) {
            return 'https://www.google.com/maps/place/' . $location;
        }
        return 'https://maps.google.com/?q=' . $location;
    }

    private function locationIsCoordinate(string $location): bool {
        $parts = explode(',', $location);
        if( count($parts) !== 2 ) {
            return false;
        }
        foreach( $parts as $part) {
            if( !is_numeric($part)) {
                return false;
            }
        }
        return true;
    }

}
