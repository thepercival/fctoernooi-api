<?php
declare(strict_types=1);

namespace App\Export\Pdf;

use App\Export\Pdf\GameLine\Column;
use DateTimeImmutable;
use DateTimeZone;
use League\Period\Period;
use Sports\Game\Against as AgainstGame;
use Sports\Game\Together as TogetherGame;
use SportsHelpers\Against\Side as AgainstSide;
use Sports\Score\Config\Service as ScoreConfigService;
use Sports\Game;
use App\Export\Pdf\GameLine\Column\Referee as RefereeColumn;
use App\Export\Pdf\GameLine\Column\DateTime as DateTimeColumn;
use App\Export\Pdf\GameLine\Column\Against as AgainstColumn;

use Sports\Round\Number as RoundNumber;
use Sports\State;
use Zend_Pdf_Color_GrayScale;

abstract class GameLine
{
    /**
     * @var array<int, float>
     */
    protected array $columnWidths = [];
    protected ScoreConfigService $scoreConfigService;

    public function __construct(protected Page\Planning $page, protected int $shoDateTime, protected int $showReferee)
    {
        $this->scoreConfigService = new ScoreConfigService();
    }

    protected function initColumnWidths(): void
    {
        $this->columnWidths[Column::Poule] = 0.05;
        $this->columnWidths[Column::Field] = 0.05;
        $this->columnWidths[Column::PlacesAndScore] = 0.90;

        if ($this->shoDateTime !== DateTimeColumn::None) {
            if ($this->shoDateTime === DateTimeColumn::DateTime) {
                $this->columnWidths[Column::Start] = 0.15;
            } elseif ($this->shoDateTime === DateTimeColumn::Time) {
                $this->columnWidths[Column::Start] = 0.075;
            }
            $this->columnWidths[Column::PlacesAndScore] -= $this->columnWidths[Column::Start];
        }

        if ($this->showReferee !== RefereeColumn::None) {
            if ($this->showReferee === RefereeColumn::Referee) {
                $this->columnWidths[Column::Referee] = 0.08;
            } elseif ($this->showReferee === RefereeColumn::SelfReferee) {
                $this->columnWidths[Column::Referee] = 0.22;
            }
            $this->columnWidths[Column::PlacesAndScore] -= $this->columnWidths[Column::Referee];
        }
    }

    public function getGameHeight(): float
    {
        return $this->page->getRowHeight();
    }

    protected function getColumnWidth(int $columnId): float
    {
        if (!isset($this->columnWidths[$columnId])) {
            return 0;
        }
        return $this->columnWidths[$columnId] * $this->page->getDisplayWidth();
    }

    protected function getGameWidth(): float
    {
        return $this->getColumnWidth(Column::Poule) +
            $this->getColumnWidth(Column::Start) +
            $this->getColumnWidth(Column::Field) +
            $this->getColumnWidth(Column::PlacesAndScore) +
            $this->getColumnWidth(Column::Referee);
    }

    public function drawHeader(bool $needsRanking, float $y): float
    {
        $x = $this->page->getPageMargin();
        $height = $this->page->getRowHeight();
        $pouleWidth = $this->getColumnWidth(Column::Poule);
        $startWidth = $this->getColumnWidth(Column::Start);
        $refereeWidth = $this->getColumnWidth(Column::Referee);
        $fieldWidth = $this->getColumnWidth(Column::Field);

        $x = $this->drawCell($needsRanking ? 'p.' : 'vs', $x, $y, $pouleWidth, $height);

        if ($this->shoDateTime !== DateTimeColumn::None) {
            $text = 'tijd';
            if ($this->shoDateTime === DateTimeColumn::DateTime) {
                $text = 'datum tijd';
            }
            $x = $this->drawCell($text, $x, $y, $startWidth, $height);
        }

        $x = $this->drawCell('v.', $x, $y, $fieldWidth, $height);

        $x = $this->drawPlacesAndScoreHeader($x, $y);

        if ($this->showReferee !== RefereeColumn::None) {
            $title = 'scheidsrechter';
            if ($this->showReferee === RefereeColumn::Referee) {
                $title = 'sch.';
            }
            $this->drawCell($title, $x, $y, $refereeWidth, $height);
        }

        return $y - $height;
    }

    /**
     * @param string $text
     * @param float $x
     * @param float $y
     * @param float $width
     * @param float $height
     * @param array<string, string>|string $vtLineColors
     * @return float
     * @throws \Zend_Pdf_Exception
     */
    protected function drawCell(
        string $text,
        float $x,
        float $y,
        float $width,
        float $height,
        array|string $vtLineColors = 'black'
    ): float {
        return $this->page->drawCell($text, $x, $y, $width, $height, Align::Center, $vtLineColors);
    }

    abstract protected function drawPlacesAndScoreHeader(float $x, float $y): float;

    public function drawBreak(RoundNumber $roundNumber, Period $tournamentBreak, float $y): float
    {
        $height = $this->page->getRowHeight();
        $pouleWidth = $this->getColumnWidth(Column::Poule);
        $startWidth = $this->getColumnWidth(Column::Start);
        $fieldWidth = $this->getColumnWidth(Column::Field);
        $placesAndScoreWidth = $this->getColumnWidth(Column::PlacesAndScore);
        $x = $this->page->getPageMargin() + $pouleWidth;
        $this->page->setFillColor(new Zend_Pdf_Color_GrayScale(1));
        if ($this->shoDateTime !== DateTimeColumn::None) {
            $text = $this->getDateTime($tournamentBreak->getStartDate());
            $x = $this->drawCell($text, $x, $y, $startWidth, $height, ['top' => 'black']);
        }
        $x += $fieldWidth;
        $this->drawCell('PAUZE', $x, $y, $placesAndScoreWidth, $height, ['top' => 'black']);
        return $y - $this->getGameHeight();
    }

    public function drawGame(AgainstGame|TogetherGame $game, float $y, bool $striped = false): float
    {
        $height = $this->page->getRowHeight();
        $pouleWidth = $this->getColumnWidth(Column::Poule);
        $startWidth = $this->getColumnWidth(Column::Start);
        $fieldWidth = $this->getColumnWidth(Column::Field);
        $refereeWidth = $this->getColumnWidth(Column::Referee);
        $x = $this->page->getPageMargin();

        $grayScale = (($game->getBatchNr() % 2) === 0 && $striped === true) ? 0.9 : 1;
        $this->page->setFillColor(new Zend_Pdf_Color_GrayScale($grayScale));

        $pouleName = $this->page->getParent()->getNameService()->getPouleName($game->getPoule(), false);
        $x = $this->drawCell($pouleName, $x, $y, $pouleWidth, $height);

        $nameService = $this->page->getParent()->getNameService();
        if ($this->shoDateTime !== DateTimeColumn::None) {
            $text = $this->getDateTime($game->getStartDateTime());
            $x = $this->drawCell($text, $x, $y, $startWidth, $height);
        }

        $field = $game->getField();
        $fieldName = $field === null ? '' : $field->getName();
        $fieldDescription = $fieldName === null ? '' : $fieldName;
        $x = $this->drawCell($fieldDescription, $x, $y, $fieldWidth, $height);

        $x = $this->drawPlacesAndScoreCell($game, $x, $y);

        if ($this->showReferee !== RefereeColumn::None) {
            $text = '';
            if ($this->showReferee === RefereeColumn::Referee) {
                $referee = $game->getReferee();
                if( $referee !== null ) {
                    $text = $referee->getInitials();
                }
            } elseif ($this->showReferee === RefereeColumn::SelfReferee) {
                $refereePlace = $game->getRefereePlace();
                if( $refereePlace !== null ) {
                    $text = $nameService->getPlaceName($refereePlace, true, true);
                }
            }
            $this->drawCell($text, $x, $y, $refereeWidth, $height);
        }

        return $y - $height;
    }

    abstract protected function drawPlacesAndScoreCell(AgainstGame|TogetherGame $game, float $x, float $y): float;

    protected function getDateTime(DateTimeImmutable $dateTime): string
    {
        $localDateTime = $dateTime->setTimezone(new DateTimeZone('Europe/Amsterdam'));
        $text = $localDateTime->format('H:i');
        if ($this->shoDateTime === DateTimeColumn::Time) {
            return $text;
        }
//        $df = new \IntlDateFormatter('nl_NL',\IntlDateFormatter::LONG, \IntlDateFormatter::NONE,'Europe/Oslo');
//        $dateElements = explode(" ", $df->format($game->getStartDateTime()));
//        $month = strtolower( substr( $dateElements[1], 0, 3 ) );
//        $text = $game->getStartDateTime()->format("d") . " " . $month . " ";
        return $localDateTime->format('d-m ') . $text;
    }
}
