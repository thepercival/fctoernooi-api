<?php

declare(strict_types=1);

namespace App\Export\Pdf\Page\PoulePivotTable;

use App\Export\Pdf\Align;
use App\Export\Pdf\Line\Horizontal as HorizontalLine;
use App\Export\Pdf\Point;
use App\Export\Pdf\Rectangle;
use Sports\Competition\Sport as CompetitionSport;
use Sports\Ranking\Calculator\Round\Sport as SportRankingCalculator;
use SportsHelpers\Sport\Variant\Against as AgainstSportVariant;

trait Helper
{
    /**
     * @var array<string, int>
     */
    protected array $fontSizeMap = [];

    protected function drawHeaderCustom(string $text, float $x, float $y, float $width, float $height, int $degrees = 0): float
    {
        $rectangle = new Rectangle(new HorizontalLine(new Point($x, $y), $width), $height);
        $this->drawCell($text, $rectangle, Align::Center, 'black', $degrees);
        return $rectangle->getRight()->getX();
    }

    protected function drawCellCustom(string $text, float $x, float $y, float $width, float $height, int $align): float
    {
        $rectangle = new Rectangle(new HorizontalLine(new Point($x, $y), $width), $height);
        $this->drawCell($text, $rectangle, $align, 'black');
        return $rectangle->getRight()->getX();
    }

    protected function getPlaceFontHeight(string $placeName): int
    {
        if (array_key_exists($placeName, $this->fontSizeMap)) {
            return $this->fontSizeMap[$placeName];
        }
        $fontHeight = $this->config->getFontHeight();
        if ($this->helper->getTextWidth($placeName, $this->helper->getTimesFont(), $fontHeight) > $this->nameColumnWidth) {
            $fontHeight -= 2;
        }
        $this->fontSizeMap[$placeName] = $fontHeight;
        return $fontHeight;
    }

    protected function getVersusHeaderDegrees(int $nrOfHeaderItems): int
    {
        if ($nrOfHeaderItems <= 3) {
            return 0;
        }
        if ($nrOfHeaderItems >= 6) {
            return 90;
        }
        return 45;
    }

    public function getVersusHeight(float $versusColumnWidth, int $degrees = 0): float
    {
        if ($degrees === 0) {
            return $this->config->getRowHeight();
        }
        if ($degrees === 90) {
            return $versusColumnWidth * 2;
        }
        return (tan(deg2rad($degrees)) * $versusColumnWidth);
    }

    protected function getSportRankingCalculator(CompetitionSport $competitionSport): SportRankingCalculator
    {
        $sportVariant = $competitionSport->createVariant();
        if ($sportVariant instanceof AgainstSportVariant) {
            return new SportRankingCalculator\Against($competitionSport);
        }
        return new SportRankingCalculator\Together($competitionSport);
    }
}
