<?php

declare(strict_types=1);

namespace App\Export\Pdf\Drawers;

use App\Export\Pdf\Align;
use App\Export\Pdf\Configs\GameLineConfig;
use App\Export\Pdf\Drawers\GameLine\Column;
use App\Export\Pdf\Drawers\GameLine\Column\Against as AgainstColumn;
use App\Export\Pdf\Drawers\GameLine\Column\DateTime as DateTimeColumn;
use App\Export\Pdf\Drawers\GameLine\Column\Referee as RefereeColumn;
use App\Export\Pdf\Line\Horizontal as HorizontalLine;
use App\Export\Pdf\Line\Vertical as VerticalLine;
use App\Export\Pdf\Page as PdfPage;
use App\Export\Pdf\Rectangle;
use DateTimeImmutable;
use DateTimeZone;
use FCToernooi\Recess;
use Sports\Game\Against as AgainstGame;
use Sports\Game\Together as TogetherGame;
use Sports\Round\Number as RoundNumber;
use Sports\Score\Config\Service as ScoreConfigService;
use Zend_Pdf_Color_GrayScale;
use Zend_Pdf_Exception;

abstract class GameLine
{
    /**
     * @var array<int, float>
     */
    protected array $columnWidths = [];
    protected float $totalWidth;
    protected ScoreConfigService $scoreConfigService;
    protected DateTimeColumn $dateTimeColumn;
    protected RefereeColumn $refereeColumn;
    protected Helper $helper;

    public function __construct(
        protected PdfPage $page,
        protected GameLineConfig $config,
        RoundNumber $roundNumber,
        float $totalWidth = null
    ) {
        $this->helper = new Helper();
        $this->scoreConfigService = new ScoreConfigService();
        $this->totalWidth = $totalWidth !== null ? $totalWidth : $this->page->getDisplayWidth();
        $this->initColumnWidths($roundNumber);
    }

    private function initColumnWidths(RoundNumber $roundNumber): void
    {
        $this->columnWidths[Column::Poule->value] = 0.05;
        $this->columnWidths[Column::Field->value] = 0.05;
        $this->columnWidths[Column::PlacesAndScore->value] = 0.90;

        $this->dateTimeColumn = DateTimeColumn::getValue($roundNumber);
        if ($this->dateTimeColumn !== DateTimeColumn::None) {
            if ($this->dateTimeColumn === DateTimeColumn::DateTime) {
                $this->columnWidths[Column::Start->value] = 0.15;
            } else /*if ($this->dateTimeColumn === DateTimeColumn::Time)*/ {
                $this->columnWidths[Column::Start->value] = 0.075;
            }
            $this->columnWidths[Column::PlacesAndScore->value] -= $this->columnWidths[Column::Start->value];
        }

        $this->refereeColumn = RefereeColumn::getValue($roundNumber);
        if ($this->refereeColumn !== RefereeColumn::None) {
            if ($this->refereeColumn === RefereeColumn::Referee) {
                $this->columnWidths[Column::Referee->value] = 0.08;
            } else /* if ($this->refereeColumn === RefereeColumn::SelfReferee)*/ {
                $this->columnWidths[Column::Referee->value] = 0.22;
            }
            $this->columnWidths[Column::PlacesAndScore->value] -= $this->columnWidths[Column::Referee->value];
        }
    }

    public function getGameHeight(TogetherGame|AgainstGame $game): float
    {
        $nrOfLines = ceil($game->getPlaces()->count() / $this->config->getMaxNrOfPlacesPerLine());
        return $this->config->getRowHeight() * $nrOfLines;
    }

    protected function getColumnWidth(Column|AgainstColumn|DateTimeColumn|RefereeColumn $column): float
    {
        if (!isset($this->columnWidths[$column->value])) {
            return 0;
        }
        return $this->columnWidths[$column->value] * $this->totalWidth;
    }

//    protected function getGameWidth(): float
//    {
//        return $this->getColumnWidth(Column::Poule) +
//            $this->getColumnWidth(Column::Start) +
//            $this->getColumnWidth(Column::Field) +
//            $this->getColumnWidth(Column::PlacesAndScore) +
//            $this->getColumnWidth(Column::Referee);
//    }

    public function drawTableHeader(bool $needsRanking, Rectangle $rectangle): void
    {
        $pouleWidth = $this->getColumnWidth(Column::Poule);
        $startWidth = $this->getColumnWidth(Column::Start);
        $refereeWidth = $this->getColumnWidth(Column::Referee);
        $fieldWidth = $this->getColumnWidth(Column::Field);

        $rankingLeft = $rectangle->getLeft();
        $rankingCell = new Rectangle($rankingLeft, $pouleWidth);
        $this->drawHeaderCell($needsRanking ? 'p.' : 'vs', $rankingCell);

        $fieldLft = $rankingCell->getRight();
        if ($this->dateTimeColumn !== DateTimeColumn::None) {
            $text = 'tijd';
            if ($this->dateTimeColumn === DateTimeColumn::DateTime) {
                $text = 'datum tijd';
            }
            $dateCell = new Rectangle($rankingCell->getRight(), $startWidth);
            $this->drawHeaderCell($text, $dateCell);
            $fieldLft = $dateCell->getRight();
        }
        $fieldCell = new Rectangle($fieldLft, $fieldWidth);
        $this->drawHeaderCell('v.', $fieldCell);

        $refereeLeft = $this->drawPlacesAndScoreHeader($fieldCell->getRight());
        if ($this->refereeColumn !== RefereeColumn::None) {
            $title = 'scheidsrechter';
            if ($this->refereeColumn === RefereeColumn::Referee) {
                $title = 'sch.';
            }
            $this->drawHeaderCell($title, new Rectangle($refereeLeft, $refereeWidth));
        }
    }



//    /**
//     * @param string $text
//     * @param Rectangle $rectangle
//     * @param array<string, string>|string $vtLineColors
//     * @throws \Zend_Pdf_Exception
//     */
//    protected function drawCell(
//        string $text,
//        Rectangle $rectangle,
//        array|string $vtLineColors = 'black'
//    ): void {
//        $this->page->drawCell($text, $rectangle, Align::Center, $vtLineColors);
//    }

    abstract protected function drawPlacesAndScoreHeader(VerticalLine $left): VerticalLine;

    public function drawRecess(Recess $recess, Rectangle $rectangle, DateTimeColumn $dateTimeColumn): void
    {
        $startVertLine = $rectangle->getLeft();
        $vertLine = $startVertLine->addX($this->getColumnWidth(Column::Poule));
        $this->page->setFillColor(new Zend_Pdf_Color_GrayScale(1));
        if ($dateTimeColumn !== DateTimeColumn::None) {
            $text = $this->getDateTimeAsLocalString($recess->getStartDateTime(), $dateTimeColumn);
            $rectangle = new Rectangle($vertLine, $this->getColumnWidth(Column::Start));
            $this->drawHeaderCell($text, $rectangle);
            $vertLine = $rectangle->getRight();
        }
        $vertLine = $vertLine->addX($this->getColumnWidth(Column::Field));

        $rectangle = new Rectangle($vertLine, $this->getColumnWidth(Column::PlacesAndScore));
        $this->page->setFont($this->helper->getTimesFont(true), $this->config->getFontHeight());
        $this->drawTableCell($recess->getName(), $rectangle);
        $this->page->setFont($this->helper->getTimesFont(), $this->config->getFontHeight());
    }

    public function drawGame(
        AgainstGame|TogetherGame $game,
        HorizontalLine $horStartLine,
        bool $striped = false
    ): HorizontalLine {
        $gameHeight = $this->getGameHeight($game);
        $left = new VerticalLine($horStartLine->getStart(), -$gameHeight);
        $pouleWidth = $this->getColumnWidth(Column::Poule);
        $startWidth = $this->getColumnWidth(Column::Start);
        $fieldWidth = $this->getColumnWidth(Column::Field);
        $refereeWidth = $this->getColumnWidth(Column::Referee);

        $grayScale = (($game->getBatchNr() % 2) === 0 && $striped === true) ? 0.9 : 1;
        $this->page->setFillColor(new Zend_Pdf_Color_GrayScale($grayScale));

        $structureNameService = $this->page->getStructureNameService();
        $pouleName = $structureNameService->getPouleName($game->getPoule(), false);
        $this->drawTableCell($pouleName, new Rectangle($left, $pouleWidth));
        $leftNext = $left->addX($pouleWidth);

        if ($this->dateTimeColumn !== DateTimeColumn::None) {
            $text = $this->getDateTimeAsLocalString($game->getStartDateTime(), $this->dateTimeColumn);
            $this->drawTableCell($text, new Rectangle($leftNext, $startWidth));
            $leftNext = $leftNext->addX($startWidth);
        }

        $field = $game->getField();
        $fieldName = $field === null ? '' : $field->getName();
        $fieldDescription = $fieldName === null ? '' : $fieldName;
        $this->drawTableCell($fieldDescription, new Rectangle($leftNext, $fieldWidth));
        $left = $leftNext->addX($fieldWidth);

        $left = $this->drawPlacesAndScoreCell($game, $left);

        if ($this->refereeColumn !== RefereeColumn::None) {
            $text = '';
            if ($this->refereeColumn === RefereeColumn::Referee) {
                $referee = $game->getReferee();
                if ($referee !== null) {
                    $text = $referee->getInitials();
                }
            } else /*if ($this->refereeColumn === RefereeColumn::SelfReferee)*/ {
                $refereePlace = $game->getRefereePlace();
                if ($refereePlace !== null) {
                    $text = $structureNameService->getPlaceName($refereePlace, true, true);
                }
            }
            $this->drawTableCell($text, new Rectangle($left, $refereeWidth));
        }

        return $horStartLine->addY(-$gameHeight);
    }

    abstract protected function drawPlacesAndScoreCell(AgainstGame|TogetherGame $game, VerticalLine $left): VerticalLine;

    protected function getDateTimeAsLocalString(\DateTimeImmutable $dateTimeImmutable, DateTimeColumn $dateTimeColumn): string
    {
        // Convert the time to the desired timezone (Amsterdam)
        $localDateTime = $dateTimeImmutable->setTimezone(new \DateTimeZone('Europe/Amsterdam'));

        $pattern = $dateTimeColumn === DateTimeColumn::Time ? 'HH:mm' : 'dd-MM HH:mm';

        // Create an IntlDateFormatter for Dutch (Netherlands)
        $formatter = new \IntlDateFormatter(
            'nl_NL',                     // locale
            \IntlDateFormatter::FULL,   // date style – we’ll build a custom pattern anyway
            \IntlDateFormatter::NONE,   // time style – handled separately
            'Europe/Amsterdam',          // explicit timezone (matches $localDateTime)
            \IntlDateFormatter::GREGORIAN,
            $pattern                               // pattern: full weekday, day, short month, year, 24‑h time
        );

        // Format the DateTimeImmutable instance
        $formatted = $formatter->format($localDateTime);

        // Ensure the whole string is lower‑cased (mb_* handles multibyte characters correctly)
        return $formatted === false ? 'unknown date' : mb_strtolower($formatted, 'UTF-8');
    }


    protected function drawHeaderCell(string $val, Rectangle $cell, Align|null $align = Align::Center): void
    {
        $this->page->setFont($this->helper->getTimesFont(true), $this->config->getFontHeight());
        $this->drawCell($val, $cell, ['b' => 'black'], $align);
        $this->page->setFont($this->helper->getTimesFont(), $this->config->getFontHeight());
    }

    protected function drawTableCell(string $val, Rectangle $cell, Align|null $align = Align::Center): void
    {
        $this->drawCell($val, $cell, ['b' => 'gray'], $align);
    }

    /**
     * @param string $text
     * @param Rectangle $rectangle
     * @param array<string, string>| string | null $vtLineColors
     * @throws Zend_Pdf_Exception
     */
    public function drawCell(
        string $text,
        Rectangle $rectangle,
        array|string|null $vtLineColors = null,
        Align|null $align = null
    ): void {
        if ($align === null) {
            $align = Align::Center;
        }
        $this->page->drawCell($text, $rectangle, $align, $vtLineColors);
    }
}
