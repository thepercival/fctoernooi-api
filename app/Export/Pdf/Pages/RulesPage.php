<?php

declare(strict_types=1);

namespace App\Export\Pdf\Pages;

use App\Export\Pdf\Configs\HeaderConfig;
use App\Export\Pdf\Documents\IntroDocument;
use App\Export\Pdf\Line\Horizontal as HorizontalLine;
use App\Export\Pdf\Page as ToernooiPdfPage;
use App\Export\Pdf\Point;
use App\Export\Pdf\Rectangle;
use FCToernooi\Tournament\Rule as TournamentRule;
use App\ImageSize;

/**
 * @template-extends ToernooiPdfPage<IntroDocument>
 */
class RulesPage extends ToernooiPdfPage
{
    public function __construct(IntroDocument $document, mixed $param1)
    {
        parent::__construct($document, $param1);
        $this->setLineWidth(0.5);
    }

    public function draw(): void
    {
        $y = $this->drawHeader(
            $this->parent->getTournament()->getName(),
            'huisregels',
            new HeaderConfig()
        );

        $config = $this->parent->getConfig();
        // $rowHeight = ;

        $fieldsetMargin = static::PAGEMARGIN;
        $xStart = ToernooiPdfPage::PAGEMARGIN;
        $y -= $fieldsetMargin;

        $logoPath = $this->parent->getTournamentLogoPath(ImageSize::Small);
        $imgWidth = ImageSize::Normal->value;
        $introWidth = $this->getDisplayWidth() - ( $imgWidth + $fieldsetMargin );
        $marginStartDashedLine = 30;
        // $widthDashedLine = $this->getDisplayWidth() - ($labelWidth + $marginStartDashedLine);

        // $xStartDashedLine = $xStart + $labelWidth + $marginStartDashedLine;

        $theme = $this->parent->getTheme();


        // 1 MAAK LINKSBOVEN EEN FIELDSET, HOOGTE OP BASIS VAN AANTAL REGELS
        $rules = array_map( function(TournamentRule $rule): string {
            return $rule->getText();
        }, $this->parent->getTournament()->getRules()->toArray() );

        $dummy = 1;
        $rectangle = new Rectangle(new HorizontalLine(new Point($xStart, $y), $introWidth), $dummy);
        $this->drawFieldsetList(
            array_values( $rules ),
            $rectangle,
            $theme,
            'huisregels',
            $config->getRulesFieldsetListConfig(),
        );


    }
}
