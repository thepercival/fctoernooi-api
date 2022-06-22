<?php

declare(strict_types=1);

namespace App\Export\Pdf\Drawers;

use App\Export\Pdf\Align;
use App\Export\Pdf\Configs\GameLineConfig;
use App\Export\Pdf\Drawers\GameLine\Column\Against as AgainstColumn;
use App\Export\Pdf\Drawers\GameLine\Column\DateTime;
use App\Export\Pdf\Drawers\GameLine\Column\DateTime as DateTimeColumn;
use App\Export\Pdf\Drawers\GameLine\Column\Referee as RefereeColumn;
use App\Export\Pdf\Line\Horizontal as HorizontalLine;
use App\Export\Pdf\Line\Vertical as VerticalLine;
use App\Export\Pdf\Page as PdfPage;
use App\Export\Pdf\Page\Traits\GameLine\Column;
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

    public function __construct(
        protected PdfPage $page,
        protected GameLineConfig $config,
        RoundNumber $roundNumber,
        float $totalWidth = null
    ) {
        $this->scoreConfigService = new ScoreConfigService();
        $this->totalWidth = $totalWidth !== null ? $totalWidth : $this->page->getWidth();
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
        $nrOfLines = (int) ceil($game->getPlaces()->count() / $this->config->getMaxNrOfPlacesPerLine());
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

    public function drawHeader(bool $needsRanking, Rectangle $rectangle): void
    {
        $pouleWidth = $this->getColumnWidth(Column::Poule);
        $startWidth = $this->getColumnWidth(Column::Start);
        $refereeWidth = $this->getColumnWidth(Column::Referee);
        $fieldWidth = $this->getColumnWidth(Column::Field);

        $rankingLeft = $rectangle->getLeft();
        $rankingCell = new Rectangle($rankingLeft, $pouleWidth);
        $this->drawCell($needsRanking ? 'p.' : 'vs', $rankingCell, 'black');

        $fieldLft = $rankingCell->getRight();
        if ($this->dateTimeColumn !== DateTimeColumn::None) {
            $text = 'tijd';
            if ($this->dateTimeColumn === DateTimeColumn::DateTime) {
                $text = 'datum tijd';
            }
            $dateCell = new Rectangle($rankingCell->getRight(), $startWidth);
            $this->drawCell($text, $dateCell, 'black');
            $fieldLft = $dateCell->getRight();
        }
        $fieldCell = new Rectangle($fieldLft, $fieldWidth);
        $this->drawCell('v.', $fieldCell);

        $refereeLeft = $this->drawPlacesAndScoreHeader($fieldCell->getRight());
        if ($this->refereeColumn !== RefereeColumn::None) {
            $title = 'scheidsrechter';
            if ($this->refereeColumn === RefereeColumn::Referee) {
                $title = 'sch.';
            }
            $this->drawCell($title, new Rectangle($refereeLeft, $refereeWidth), 'black');
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
        $width = $rectangle->getWidth();
        $vertLine = $startVertLine->addX($this->getColumnWidth(Column::Poule));
        $this->page->setFillColor(new Zend_Pdf_Color_GrayScale(1));
        if ($dateTimeColumn !== DateTimeColumn::None) {
            $text = $this->getDateTime($recess->getStartDateTime(), $dateTimeColumn);
            $rectangle = new Rectangle($vertLine, $this->getColumnWidth(Column::Start));
            $this->drawCell($text, $rectangle, ['top' => 'black']);
            $vertLine = $rectangle->getRight();
        }
        $vertLine = $vertLine->addX($this->getColumnWidth(Column::Field));

        $rectangle = new Rectangle($vertLine, $this->getColumnWidth(Column::PlacesAndScore));
        $this->drawCell($recess->getName(), $rectangle, ['top' => 'black']);
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
        $this->page->drawCell($pouleName, new Rectangle($left, $pouleWidth));
        $left = $left->addX($pouleWidth);

        if ($this->dateTimeColumn !== DateTimeColumn::None) {
            $text = $this->getDateTime($game->getStartDateTime(), $this->dateTimeColumn);
            $this->page->drawCell($text, new Rectangle($left, $startWidth));
            $left = $left->addX($pouleWidth);
        }

        $field = $game->getField();
        $fieldName = $field === null ? '' : $field->getName();
        $fieldDescription = $fieldName === null ? '' : $fieldName;
        $this->page->drawCell($fieldDescription, new Rectangle($left, $fieldWidth));
        $left = $left->addX($fieldWidth);

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
            $this->page->drawCell($text, new Rectangle($left, $refereeWidth));
        }

        return $horStartLine->addY(-$gameHeight);
    }

    abstract protected function drawPlacesAndScoreCell(AgainstGame|TogetherGame $game, VerticalLine $left): VerticalLine;

    protected function getDateTime(DateTimeImmutable $dateTime, DateTimeColumn $dateTimeColumn): string
    {
//        $df = new \IntlDateFormatter('nl_NL',\IntlDateFormatter::LONG, \IntlDateFormatter::NONE,'Europe/Oslo');
//        $dateElements = explode(" ", $df->format($game->getStartDateTime()));
//        $month = strtolower( substr( $dateElements[1], 0, 3 ) );
//        $text = $game->getStartDateTime()->format("d") . " " . $month . " ";
//        return $localDateTime->format('d-m ') . $text;

        setlocale(LC_ALL, 'nl_NL.UTF-8'); //
        $localDateTime = $dateTime->setTimezone(new DateTimeZone('Europe/Amsterdam'));

        $text = $localDateTime->format('H:i');
        if ($dateTimeColumn === DateTimeColumn::Time) {
            return $text;
        }
        return mb_strtolower(
            strftime('%d-%m', $localDateTime->getTimestamp()) . ' ' .
            $text
        );
    }

    protected function getDateTimeAsStringForEmail(DateTimeImmutable $dateTimeImmutable): string
    {
        setlocale(LC_ALL, 'nl_NL.UTF-8'); //
        $localDateTime = $dateTimeImmutable->setTimezone(new DateTimeZone('Europe/Amsterdam'));
        return mb_strtolower(
            strftime('%A %e %b %Y', $localDateTime->getTimestamp()) . ' ' .
            $localDateTime->format('H:i')
        );
    }

    /**
     * @param string $sText
     * @param Rectangle $rectangle
     * @param array<string, string>| string | null $vtLineColors
     * @throws Zend_Pdf_Exception
     */
    public function drawCell(
        string $text,
        Rectangle $rectangle,
        array|string|null $vtLineColors = null,
    ): void {
        $this->page->drawCell($text, $rectangle, Align::Center, $vtLineColors);
    }
}
