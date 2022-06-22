<?php

declare(strict_types=1);

namespace App\Export\Pdf\Page;

use App\Export\Pdf\Align;
use App\Export\Pdf\Configs\HeaderConfig;
use App\Export\Pdf\Document\GameNotes as GameNotesDocument;
use App\Export\Pdf\Line\Horizontal as HorizontalLine;
use App\Export\Pdf\Page as ToernooiPdfPage;
use App\Export\Pdf\Point;
use App\Export\Pdf\Rectangle;
use DateTimeZone;
use FCToernooi\QRService;
use FCToernooi\TranslationService;
use Sports\Competition\Sport as CompetitionSport;
use Sports\Game\Against as AgainstGame;
use Sports\Game\Together as TogetherGame;
use Sports\Round;
use Sports\Score\Config as ScoreConfig;
use Sports\Score\Config\Service as ScoreConfigService;
use SportsHelpers\Sport\Variant\AllInOneGame as AllInOneGameSportVariant;
use Zend_Pdf_Color_Html;
use Zend_Pdf_Page;
use Zend_Pdf_Resource_Image;
use Zend_Pdf_Resource_ImageFactory;

/**
 * @template-extends ToernooiPdfPage<GameNotesDocument>
 */
abstract class GameNotes extends ToernooiPdfPage
{
    protected ScoreConfigService $scoreConfigService;
    protected TranslationService $translationService;
    protected QRService $qrService;

    public function __construct(
        mixed $parent,
        mixed $param1,
        protected AgainstGame|TogetherGame $gameOne,
        protected AgainstGame|TogetherGame|null $gameTwo
    ) {
        parent::__construct($parent, $param1);
        $this->setFont($this->helper->getTimesFont(), $this->parent->getConfig()->getFontHeight());
        $this->setLineWidth(0.5);
        $this->scoreConfigService = new ScoreConfigService();
        $this->translationService = new TranslationService();
        $this->qrService = new QRService();
    }

//    public function getParent(): GameNotesDocument
//    {
//        return $this->parent;
//    }

    protected function getPartWidth(): float
    {
        return ($this->getDisplayWidth() - (4 * $this->parent->getConfig()->getMargin())) / 5;
    }

    protected function getLeftPartWidth(): float
    {
        return $this->getPartWidth();
    }

    protected function getDetailPartWidth(): float
    {
        return $this->getPartWidth() + $this->parent->getConfig()->getMargin() + $this->getPartWidth();
    }

    protected function getStartDetailLabel(): float
    {
        return self::PAGEMARGIN + $this->getLeftPartWidth() + $this->parent->getConfig()->getMargin();
    }

    protected function getStartDetailValue(): float
    {
        return $this->getStartDetailLabel() + $this->getDetailPartWidth() + $this->parent->getConfig()->getMargin();
    }

    protected function getQrCodeUrlPrefix(AgainstGame|TogetherGame $game): string
    {
        $suffix = ($game instanceof AgainstGame) ? 'against' : 'together';
        return $this->parent->getUrl() . 'admin/game' . $suffix . '/' .
                (string)$this->parent->getTournament()->getId() . '/';
    }

    public function draw(bool $oneGamePerPage = false): void
    {
        $y = $this->drawHeader($this->parent->getTournament()->getName(), 'wedstrijdbriefje');
        $this->drawGame($this->gameOne, $y);

        $this->setLineColor(new Zend_Pdf_Color_Html('black'));
        if ($oneGamePerPage !== true) {
            $this->setLineDashingPattern([10, 10]);
            $this->drawLine(
                self::PAGEMARGIN,
                $this->getHeight() / 2,
                $this->getWidth() - self::PAGEMARGIN,
                $this->getHeight() / 2
            );
            $this->setLineDashingPattern(Zend_Pdf_Page::LINE_DASHING_SOLID);
        }
        if ($this->gameTwo !== null) {
            $y = $this->drawHeader(
                $this->parent->getTournament()->getName(),
                'wedstrijdbriefje',
                new HeaderConfig(
                    ($this->getHeight() / 2) - self::PAGEMARGIN
                )
            );
            $this->drawGame($this->gameTwo, $y);
        }
    }

    abstract protected function drawPlaces(AgainstGame|TogetherGame $game, float $x, float $y, float $width, float $height): void;

    abstract protected function drawGameRoundNumber(AgainstGame|TogetherGame $game, float $x, float $y, float $width, float $height): float;

    abstract protected function drawScore(AgainstGame|TogetherGame $game, float $y): void;

    protected function drawGame(AgainstGame|TogetherGame $game, float $y): void
    {
        $this->setFont($this->helper->getTimesFont(), $this->parent->getConfig()->getFontHeight());
        $rowHeight = $this->parent->getConfig()->getRowHeight();
        $yNext = $this->drawGameDetail($game, $y);
        $this->drawQRCode($game, $y);
        $yNext -= $rowHeight;
        // $y -= $rowHeight; // extra lege regel
        $this->drawScore($game, $yNext);
    }

    protected function drawQRCode(AgainstGame|TogetherGame $game, float $y): void
    {
        $url = $this->getQrCodeUrlPrefix($game) . (string)$game->getId();

        $imgSize = $this->getLeftPartWidth() * 1.5;
        $qrPath = $this->qrService->writeGameToJpg($this->parent->getTournament(), $game, $url, (int)$imgSize);
        /** @var Zend_Pdf_Resource_Image $img */
        $img = Zend_Pdf_Resource_ImageFactory::factory($qrPath);
        $this->drawImage($img, self::PAGEMARGIN, $y - $imgSize, (self::PAGEMARGIN + $imgSize), $y);
    }

    protected function drawGameDetail(AgainstGame|TogetherGame $game, float $y): float
    {
        $this->setFont($this->helper->getTimesFont(), $this->parent->getConfig()->getFontHeight());

        $height = $this->parent->getConfig()->getRowHeight();
        $margin = $this->parent->getConfig()->getMargin();
        $width = $this->getDetailPartWidth();
        $sportVariant = $game->getCompetitionSport()->createVariant();
        $roundNumber = $game->getRound()->getNumber();
        $planningConfig = $roundNumber->getValidPlanningConfig();
        $structureNameService = $this->getStructureNameService();
        $labelStartX = $this->getStartDetailLabel();
        $sepStartX = $this->getStartDetailValue() - $margin;
        $valueStartX = $this->getStartDetailValue();

        $bNeedsRanking = $game->getPoule()->needsRanking();

        $roundNumberName = $structureNameService->getRoundNumberName($roundNumber);
        $rectangle = new Rectangle(new HorizontalLine(new Point($labelStartX, $y), $width), $height);
        $this->drawCell('ronde', $rectangle, Align::Right);
        $rectangle = new Rectangle(new HorizontalLine(new Point($sepStartX, $y), $margin), $height);
        $this->drawCell(':', $rectangle, Align::Center);
        $rectangle = new Rectangle(new HorizontalLine(new Point($valueStartX, $y), $width), $height);
        $this->drawCell($roundNumberName, $rectangle);
        $y -= $height;

        // game-name
        $sGame = $structureNameService->getPouleName($game->getPoule(), false);
        $gameLabel = $bNeedsRanking ? 'poule' : 'wedstrijd';
        $rectangle = new Rectangle(new HorizontalLine(new Point($labelStartX, $y), $width), $height);
        $this->drawCell($gameLabel, $rectangle, Align::Right);
        $rectangle = new Rectangle(new HorizontalLine(new Point($sepStartX, $y), $margin), $height);
        $this->drawCell(':', $rectangle, Align::Center);
        $rectangle = new Rectangle(new HorizontalLine(new Point($valueStartX, $y), $width), $height);
        $this->drawCell($sGame, $rectangle);
        $y -= $height;

        // places
        if (!($sportVariant instanceof AllInOneGameSportVariant)) { // gameRoundNumber
            $rectangle = new Rectangle(new HorizontalLine(new Point($labelStartX, $y), $width), $height);
            $this->drawCell('plekken', $rectangle, Align::Right);
            $rectangle = new Rectangle(new HorizontalLine(new Point($sepStartX, $y), $margin), $height);
            $this->drawCell(':', $rectangle, Align::Center);
            $this->drawPlaces($game, $valueStartX, $y, $width, $height);
            $y -= $height;
        }
        if ($bNeedsRanking) { // gameRoundNumber
            $rectangle = new Rectangle(new HorizontalLine(new Point($labelStartX, $y), $width), $height);
            $this->drawCell('speelronde', $rectangle, Align::Right);
            $rectangle = new Rectangle(new HorizontalLine(new Point($sepStartX, $y), $margin), $height);
            $this->drawCell(':', $rectangle, Align::Center);
            $y = $this->drawGameRoundNumber($game, $valueStartX, $y, $width, $height);
        }

        if ($planningConfig->getEnableTime()) {
            setlocale(LC_ALL, 'nl_NL.UTF-8'); //
            $localDateTime = $game->getStartDateTime()->setTimezone(new DateTimeZone('Europe/Amsterdam'));
            $dateTime = strtolower(
                $localDateTime->format('H:i') . '     ' . strftime('%a %d %b %Y', $localDateTime->getTimestamp())
            );
            // $dateTime = strtolower( $localDateTime->format("H:i") . "     " . $localDateTime->format("D d M") );
            $duration = $planningConfig->getMinutesPerGame() . ' min.';
            if ($planningConfig->getExtension()) {
                $duration .= ' (' . $planningConfig->getMinutesPerGameExt() . ' min.)';
            }
            $rectangle = new Rectangle(new HorizontalLine(new Point($labelStartX, $y), $width), $height);
            $this->drawCell('tijdstip', $rectangle, Align::Right);
            $rectangle = new Rectangle(new HorizontalLine(new Point($sepStartX, $y), $margin), $height);
            $this->drawCell(':', $rectangle, Align::Center);
            $rectangle = new Rectangle(new HorizontalLine(new Point($valueStartX, $y), $width), $height);
            $this->drawCell($dateTime, $rectangle);
            $y -= $height;

            $rectangle = new Rectangle(new HorizontalLine(new Point($labelStartX, $y), $width), $height);
            $this->drawCell('duur', $rectangle, Align::Right);
            $rectangle = new Rectangle(new HorizontalLine(new Point($sepStartX, $y), $margin), $height);
            $this->drawCell(':', $rectangle, Align::Center);
            $rectangle = new Rectangle(new HorizontalLine(new Point($valueStartX, $y), $width), $height);
            $this->drawCell($duration, $rectangle);
            $y -= $height;
        }

        // field
        {
            $rectangle = new Rectangle(new HorizontalLine(new Point($labelStartX, $y), $width), $height);
            $this->drawCell('veld', $rectangle, Align::Right);
            $rectangle = new Rectangle(new HorizontalLine(new Point($sepStartX, $y), $margin), $height);
            $this->drawCell(':', $rectangle, Align::Center);
            $fieldDescription = '';
            $field = $game->getField();
            if ($field !== null) {
                $fieldName = $field->getName();
                if ($fieldName !== null) {
                    $fieldDescription = $fieldName;
                }
            }
            if ($roundNumber->getCompetition()->hasMultipleSports()) {
                $fieldDescription .= ' - ' . $game->getCompetitionSport()->getSport()->getName();
            }
            $rectangle = new Rectangle(new HorizontalLine(new Point($valueStartX, $y), $width), $height);
            $this->drawCell($fieldDescription, $rectangle);
            $y -= $height;
        }

        $referee = $game->getReferee();
        $refereePlace = $game->getRefereePlace();
        if ($referee !== null || $refereePlace !== null) {
            if ($referee !== null) {
                $refName = $referee->getInitials();
            } else {
                $refName = $structureNameService->getPlaceName($refereePlace, true, true);
            }
            $rectangle = new Rectangle(new HorizontalLine(new Point($labelStartX, $y), $width), $height);
            $this->drawCell('scheidsrechter', $rectangle, Align::Right);
            $rectangle = new Rectangle(new HorizontalLine(new Point($sepStartX, $y), $margin), $height);
            $this->drawCell(':', $rectangle, Align::Center);
            $rectangle = new Rectangle(new HorizontalLine(new Point($valueStartX, $y), $width), $height);
            $this->drawCell($refName, $rectangle);
            $y -= $height;
        }
        $firstScoreConfig = $game->getScoreConfig();

        $rectangle = new Rectangle(new HorizontalLine(new Point($labelStartX, $y), $width), $height);
        $this->drawCell('score', $rectangle, Align::Right);
        $rectangle = new Rectangle(new HorizontalLine(new Point($sepStartX, $y), $margin), $height);
        $this->drawCell(':', $rectangle, Align::Center);
        $descr = $this->getScoreConfigDescription($firstScoreConfig);
        $rectangle = new Rectangle(new HorizontalLine(new Point($valueStartX, $y), $width), $height);
        $this->drawCell($descr, $rectangle);
        $y -= $height;

        return $y;
    }

    protected function getInputScoreConfigDescription(ScoreConfig $firstScoreConfig): string
    {
        $scoreNamePlural = $this->translationService->getScoreNamePlural($firstScoreConfig);
        if ($firstScoreConfig->getMaximum() === 0) {
            return $scoreNamePlural;
        }
        $direction = $this->getDirectionName($firstScoreConfig);
        return $direction . ' ' . $firstScoreConfig->getMaximum() . ' ' . $scoreNamePlural;
    }

    protected function getScoreConfigDescription(ScoreConfig $scoreConfig): string
    {
        $text = '';
        $nextScoreConfig = $scoreConfig->getNext();
        if ($nextScoreConfig !== null && $nextScoreConfig->getEnabled()) {
            if ($nextScoreConfig->getMaximum() === 0) {
                $text .= 'zoveel mogelijk ';
                $text .= $this->translationService->getScoreNamePlural($nextScoreConfig);
            } else {
                $text .= 'eerst bij ';
                $text .= $nextScoreConfig->getMaximum() . ' ';
                $text .= $this->translationService->getScoreNamePlural($nextScoreConfig);
            }
            $text .= ', ' . $scoreConfig->getMaximum() . ' ';
            $text .= $this->translationService->getScoreNamePlural($scoreConfig) . ' per ';
            $text .= $this->translationService->getScoreNameSingular($nextScoreConfig);
        } elseif ($scoreConfig->getMaximum() === 0) {
            $text .= 'zoveel mogelijk ';
            $text .= $this->translationService->getScoreNamePlural($scoreConfig);
        } else {
            $text .= 'eerst bij ';
            $text .= $scoreConfig->getMaximum() . ' ';
            $text .= $this->translationService->getScoreNamePlural($scoreConfig);
        }
        return $text;
    }

    protected function getDirectionName(ScoreConfig $scoreConfig): string
    {
        return $this->translationService->getScoreDirection($scoreConfig->getDirection());
    }

    protected function getNrOfScoreLines(Round $round, CompetitionSport $competitionSport): int
    {
        return $this->parent->getNrOfGameNoteScoreLines($round, $competitionSport);
    }
}
