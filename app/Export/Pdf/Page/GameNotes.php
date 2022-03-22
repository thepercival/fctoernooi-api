<?php

declare(strict_types=1);

namespace App\Export\Pdf\Page;

use App\Export\Pdf\Align;
use App\Export\Pdf\Document\GameNotes as GameNotesDocument;
use App\Export\Pdf\Page as ToernooiPdfPage;
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
    public const Margin = 15;
    public const RowHeight = 20;

    protected ScoreConfigService $scoreConfigService;
    protected TranslationService $translationService;
    protected QRService $qrService;
    protected string|null $qrCodeUrlPrefix = null;

    public function __construct(
        mixed $parent,
        mixed $param1,
        protected AgainstGame|TogetherGame $gameOne,
        protected AgainstGame|TogetherGame|null $gameTwo
    ) {
        parent::__construct($parent, $param1);
        $this->setLineWidth(0.5);
        $this->scoreConfigService = new ScoreConfigService();
        $this->translationService = new TranslationService();
        $this->qrService = new QRService();
    }

//    public function getParent(): GameNotesDocument
//    {
//        return $this->parent;
//    }

    public function getPageMargin(): float
    {
        return 20;
    }

    public function getHeaderHeight(): float
    {
        return 0;
    }

    protected function getPartWidth(): float
    {
        return ($this->getDisplayWidth() - (4 * GameNotes::Margin)) / 5;
    }

    protected function getLeftPartWidth(): float
    {
        return $this->getPartWidth();
    }

    protected function getDetailPartWidth(): float
    {
        return $this->getPartWidth() + GameNotes::Margin  + $this->getPartWidth();
    }

    protected function getStartDetailLabel(): float
    {
        return $this->getPageMargin() + $this->getLeftPartWidth() + GameNotes::Margin;
    }

    protected function getStartDetailValue(): float
    {
        return $this->getStartDetailLabel() + $this->getDetailPartWidth() + GameNotes::Margin;
    }

    protected function getQrCodeUrlPrefix(): string
    {
        if ($this->qrCodeUrlPrefix === null) {
            $this->qrCodeUrlPrefix = $this->parent->getUrl() . 'admin/game/' .
                (string)$this->parent->getTournament()->getId() .
                '/';
        }
        return $this->qrCodeUrlPrefix;
    }

    public function draw(bool $oneGamePerPage = false): void
    {
        $y = $this->drawHeader('wedstrijdbriefje');
        $this->drawGame($this->gameOne, $y);

        $this->setLineColor(new Zend_Pdf_Color_Html('black'));
        if ($oneGamePerPage !== true) {
            $this->setLineDashingPattern([10, 10]);
            $this->drawLine(
                $this->getPageMargin(),
                $this->getHeight() / 2,
                $this->getWidth() - $this->getPageMargin(),
                $this->getHeight() / 2
            );
            $this->setLineDashingPattern(Zend_Pdf_Page::LINE_DASHING_SOLID);
        }
        if ($this->gameTwo !== null) {
            $y = $this->drawHeader('wedstrijdbriefje', ($this->getHeight() / 2) - $this->getPageMargin());
            $this->drawGame($this->gameTwo, $y);
        }
    }

    abstract protected function drawPlaces(AgainstGame|TogetherGame $game, float $x, float $y, float $width, float $height): void;

    abstract protected function drawGameRoundNumber(AgainstGame|TogetherGame $game, float $x, float $y, float $width, float $height): float;

    abstract protected function drawScore(AgainstGame|TogetherGame $game, float $y): void;

    protected function drawGame(AgainstGame|TogetherGame $game, float $y): void
    {
        $this->setFont($this->parent->getFont(), $this->parent->getFontHeight());
        $rowHeight = GameNotes::RowHeight;
        $yNext = $this->drawGameDetail($game, $y);
        $this->drawQRCode($game, $y);
        $yNext -= $rowHeight;
        // $y -= $rowHeight; // extra lege regel
        $this->drawScore($game, $yNext);
    }

    protected function drawQRCode(AgainstGame|TogetherGame $game, float $y): void
    {
        $url = $this->getQrCodeUrlPrefix() . (string)$game->getId();

        $imgSize = $this->getLeftPartWidth() * 1.5;
        $qrPath = $this->qrService->writeGameToJpg($this->parent->getTournament(), $game, $url, (int)$imgSize);
        /** @var Zend_Pdf_Resource_Image $img */
        $img = Zend_Pdf_Resource_ImageFactory::factory($qrPath);
        $this->drawImage($img, $this->getPageMargin(), $y - $imgSize, ($this->getPageMargin() + $imgSize), $y);
    }

    protected function drawGameDetail(AgainstGame|TogetherGame $game, float $y): float
    {
        $this->setFont($this->parent->getFont(), $this->parent->getFontHeight());

        $height = GameNotes::RowHeight;
        $margin = GameNotes::Margin;
        $width = $this->getDetailPartWidth();
        $sportVariant = $game->getCompetitionSport()->createVariant();
        $roundNumber = $game->getRound()->getNumber();
        $planningConfig = $roundNumber->getValidPlanningConfig();
        $nameService = $this->parent->getNameService();
        $labelStartX = $this->getStartDetailLabel();
        $sepStartX = $this->getStartDetailValue() - GameNotes::Margin;
        $valueStartX = $this->getStartDetailValue();

        $bNeedsRanking = $game->getPoule()->needsRanking();

        $roundNumberName = $nameService->getRoundNumberName($roundNumber);
        $this->drawCell('ronde', $labelStartX, $y, $width, $height, Align::Right);
        $this->drawCell(':', $sepStartX, $y, $margin, $height, Align::Center);
        $this->drawCell($roundNumberName, $valueStartX, $y, $width, $height);
        $y -= $height;

        // game-name
        $sGame = $nameService->getPouleName($game->getPoule(), false);
        $gameLabel = $bNeedsRanking ? 'poule' : 'wedstrijd';
        $this->drawCell($gameLabel, $labelStartX, $y, $width, $height, Align::Right);
        $this->drawCell(':', $sepStartX, $y, $margin, $height, Align::Center);
        $this->drawCell($sGame, $valueStartX, $y, $width, $height);
        $y -= $height;

        // places
        if (!($sportVariant instanceof AllInOneGameSportVariant)) { // gameRoundNumber
            $this->drawCell('plekken', $labelStartX, $y, $width, $height, Align::Right);
            $this->drawCell(':', $sepStartX, $y, $margin, $height, Align::Center);
            $this->drawPlaces($game, $valueStartX, $y, $width, $height);
            $y -= $height;
        }
        if ($bNeedsRanking) { // gameRoundNumber
            $this->drawCell('speelronde', $labelStartX, $y, $width, $height, Align::Right);
            $this->drawCell(':', $sepStartX, $y, $margin, $height, Align::Center);
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

            $this->drawCell('tijdstip', $labelStartX, $y, $width, $height, Align::Right);
            $this->drawCell(':', $sepStartX, $y, $margin, $height, Align::Center);
            $this->drawCell($dateTime, $valueStartX, $y, $width, $height);
            $y -= $height;

            $this->drawCell('duur', $labelStartX, $y, $width, $height, Align::Right);
            $this->drawCell(':', $sepStartX, $y, $margin, $height, Align::Center);
            $this->drawCell($duration, $valueStartX, $y, $width, $height);
            $y -= $height;
        }

        // field
        {
            $this->drawCell('veld', $labelStartX, $y, $width, $height, Align::Right);
            $this->drawCell(':', $sepStartX, $y, $margin, $height, Align::Center);
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
            $this->drawCell($fieldDescription, $valueStartX, $y, $width, $height);
            $y -= $height;
        }

        $referee = $game->getReferee();
        $refereePlace = $game->getRefereePlace();
        if ($referee !== null || $refereePlace !== null) {
            if ($referee !== null) {
                $refName = $referee->getInitials();
            } else {
                $refName = $nameService->getPlaceName($refereePlace, true, true);
            }
            $this->drawCell('scheidsrechter', $labelStartX, $y, $width, $height, Align::Right);
            $this->drawCell(':', $sepStartX, $y, $margin, $height, Align::Center);
            $this->drawCell($refName, $valueStartX, $y, $width, $height);
            $y -= $height;
        }
        $firstScoreConfig = $game->getScoreConfig();

        $this->drawCell('score', $labelStartX, $y, $width, $height, Align::Right);
        $this->drawCell(':', $sepStartX, $y, $margin, $height, Align::Center);
        $descr = $this->getScoreConfigDescription($firstScoreConfig);
        $this->drawCell($descr, $valueStartX, $y, $width, $height);
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
