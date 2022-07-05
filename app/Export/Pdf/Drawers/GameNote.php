<?php

declare(strict_types=1);

namespace App\Export\Pdf\Drawers;

use App\Export\Pdf\Align;
use App\Export\Pdf\Configs\GameNotesConfig;
use App\Export\Pdf\Line\Horizontal as HorizontalLine;
use App\Export\Pdf\Page as PdfPage;
use App\Export\Pdf\Page\GameNotes as GameNotesPage;
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
use Zend_Pdf_Resource_Image;
use Zend_Pdf_Resource_ImageFactory;

abstract class GameNote
{
    protected Helper $helper;
    protected ScoreConfigService $scoreConfigService;
    protected TranslationService $translationService;
    protected QRService $qrService;

    public function __construct(protected GameNotesConfig $config)
    {
        $this->helper = new Helper();
        $this->scoreConfigService = new ScoreConfigService();
        $this->translationService = new TranslationService();
        $this->qrService = new QRService();
    }

    public function renderGame(GameNotesPage $page, AgainstGame|TogetherGame $game, HorizontalLine $top): void
    {
        $page->setFont($this->helper->getTimesFont(), $this->config->getFontHeight());
        $rowHeight = $this->config->getRowHeight();
        $topQRCode = $this->drawGameDetail($page, $game, $top);
        $this->drawQRCode($page, $game, $topQRCode);

        // $y -= $rowHeight; // extra lege regel
        $this->drawScore($page, $game, $topQRCode->addY(-$rowHeight));
    }

    protected function drawGameDetail(
        GameNotesPage $page,
        AgainstGame|TogetherGame $game,
        HorizontalLine $top
    ): HorizontalLine {
        $page->setFont($this->helper->getTimesFont(), $this->config->getFontHeight());

        $height = $this->config->getRowHeight();
        $margin = $this->config->getMargin();
        $width = $this->getDetailPartWidth($top);
        $sportVariant = $game->getCompetitionSport()->createVariant();
        $roundNumber = $game->getRound()->getNumber();
        $planningConfig = $roundNumber->getValidPlanningConfig();
        $structureNameService = $page->getParent()->getStructureNameService();
        $labelStartX = $this->getStartDetailLabel($top);
        $sepStartX = $this->getStartDetailValue($top) - $margin;
        $valueStartX = $this->getStartDetailValue($top);
        $y = $top->getY();

        $bNeedsRanking = $game->getPoule()->needsRanking();

        $roundNumberName = $structureNameService->getRoundNumberName($roundNumber);
        $rectangle = new Rectangle(new HorizontalLine(new Point($labelStartX, $y), $width), -$height);
        $page->drawCell('ronde', $rectangle, Align::Right);
        $rectangle = new Rectangle(new HorizontalLine(new Point($sepStartX, $y), $margin), -$height);
        $page->drawCell(':', $rectangle, Align::Center);
        $rectangle = new Rectangle(new HorizontalLine(new Point($valueStartX, $y), $width), -$height);
        $page->drawCell($roundNumberName, $rectangle);
        $y -= $height;

        // game-name
        $sGame = $structureNameService->getPouleName($game->getPoule(), false);
        $gameLabel = $bNeedsRanking ? 'poule' : 'wedstrijd';
        $rectangle = new Rectangle(new HorizontalLine(new Point($labelStartX, $y), $width), -$height);
        $page->drawCell($gameLabel, $rectangle, Align::Right);
        $rectangle = new Rectangle(new HorizontalLine(new Point($sepStartX, $y), $margin), -$height);
        $page->drawCell(':', $rectangle, Align::Center);
        $rectangle = new Rectangle(new HorizontalLine(new Point($valueStartX, $y), $width), -$height);
        $page->drawCell($sGame, $rectangle);
        $y -= $height;

        // places
        if (!($sportVariant instanceof AllInOneGameSportVariant)) { // gameRoundNumber
            $rectangle = new Rectangle(new HorizontalLine(new Point($labelStartX, $y), $width), -$height);
            $page->drawCell('plekken', $rectangle, Align::Right);
            $rectangle = new Rectangle(new HorizontalLine(new Point($sepStartX, $y), $margin), -$height);
            $page->drawCell(':', $rectangle, Align::Center);
            $placesRectangle = new Rectangle(new HorizontalLine(new Point($valueStartX, $y), $width), -$height);
            $this->drawPlaces($page, $game, $placesRectangle);
            $y -= $height;
        }
        if ($bNeedsRanking) { // gameRoundNumber
            $rectangle = new Rectangle(new HorizontalLine(new Point($labelStartX, $y), $width), -$height);
            $page->drawCell('speelronde', $rectangle, Align::Right);
            $rectangle = new Rectangle(new HorizontalLine(new Point($sepStartX, $y), $margin), -$height);
            $page->drawCell(':', $rectangle, Align::Center);
            $gameRoundNumberRectngle = new Rectangle(new HorizontalLine(new Point($valueStartX, $y), $width), -$height);
            $y = $this->drawGameRoundNumber($page, $game, $gameRoundNumberRectngle)->getY();
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
            $rectangle = new Rectangle(new HorizontalLine(new Point($labelStartX, $y), $width), -$height);
            $page->drawCell('tijdstip', $rectangle, Align::Right);
            $rectangle = new Rectangle(new HorizontalLine(new Point($sepStartX, $y), $margin), -$height);
            $page->drawCell(':', $rectangle, Align::Center);
            $rectangle = new Rectangle(new HorizontalLine(new Point($valueStartX, $y), $width), -$height);
            $page->drawCell($dateTime, $rectangle);
            $y -= $height;

            $rectangle = new Rectangle(new HorizontalLine(new Point($labelStartX, $y), $width), -$height);
            $page->drawCell('duur', $rectangle, Align::Right);
            $rectangle = new Rectangle(new HorizontalLine(new Point($sepStartX, $y), $margin), -$height);
            $page->drawCell(':', $rectangle, Align::Center);
            $rectangle = new Rectangle(new HorizontalLine(new Point($valueStartX, $y), $width), -$height);
            $page->drawCell($duration, $rectangle);
            $y -= $height;
        }

        // field
        {
            $rectangle = new Rectangle(new HorizontalLine(new Point($labelStartX, $y), $width), -$height);
            $page->drawCell('veld', $rectangle, Align::Right);
            $rectangle = new Rectangle(new HorizontalLine(new Point($sepStartX, $y), $margin), -$height);
            $page->drawCell(':', $rectangle, Align::Center);
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
            $rectangle = new Rectangle(new HorizontalLine(new Point($valueStartX, $y), $width), -$height);
            $page->drawCell($fieldDescription, $rectangle);
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
            $rectangle = new Rectangle(new HorizontalLine(new Point($labelStartX, $y), $width), -$height);
            $page->drawCell('scheidsrechter', $rectangle, Align::Right);
            $rectangle = new Rectangle(new HorizontalLine(new Point($sepStartX, $y), $margin), -$height);
            $page->drawCell(':', $rectangle, Align::Center);
            $rectangle = new Rectangle(new HorizontalLine(new Point($valueStartX, $y), $width), -$height);
            $page->drawCell($refName, $rectangle);
            $y -= $height;
        }
        $firstScoreConfig = $game->getScoreConfig();

        $rectangle = new Rectangle(new HorizontalLine(new Point($labelStartX, $y), $width), -$height);
        $page->drawCell('score', $rectangle, Align::Right);
        $rectangle = new Rectangle(new HorizontalLine(new Point($sepStartX, $y), $margin), -$height);
        $page->drawCell(':', $rectangle, Align::Center);
        $descr = $this->getScoreConfigDescription($firstScoreConfig);
        $rectangle = new Rectangle(new HorizontalLine(new Point($valueStartX, $y), $width), -$height);
        $page->drawCell($descr, $rectangle);
        $y -= $height;

        return new HorizontalLine(new Point($top->getStart()->getX(), $y), $top->getWidth());
    }

    protected function getStartDetailValue(HorizontalLine $horLine): float
    {
        return $this->getStartDetailLabel($horLine) +
            $this->getDetailPartWidth($horLine) +
            $this->config->getMargin();
    }

    protected function getStartDetailLabel(HorizontalLine $horLine): float
    {
        return PdfPage::PAGEMARGIN + $this->getLeftPartWidth($horLine) + $this->config->getMargin();
    }

    protected function getLeftPartWidth(HorizontalLine $horLine): float
    {
        // $this->getDisplayWidth()
        return $this->getPartWidth($horLine);
    }

    protected function getPartWidth(HorizontalLine $horLine): float
    {
        return ($horLine->getWidth() - (4 * $this->config->getMargin())) / 5;
    }

    protected function getDetailPartWidth(HorizontalLine $horLine): float
    {
        return $this->getPartWidth($horLine) + $this->config->getMargin() + $this->getPartWidth($horLine);
    }

    abstract protected function drawPlaces(
        GameNotesPage $page,
        AgainstGame|TogetherGame $game,
        Rectangle $rectangle
    ): void;

    abstract protected function drawGameRoundNumber(
        GameNotesPage $page,
        AgainstGame|TogetherGame $game,
        Rectangle $rectangle
    ): HorizontalLine;

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

    protected function drawQRCode(GameNotesPage $page, AgainstGame|TogetherGame $game, HorizontalLine $top): void
    {
        $url = $this->getQrCodeUrlPrefix($page, $game) . (string)$game->getId();

        $imgSize = $this->getLeftPartWidth($top) * 1.5;
        $tournament = $page->getParent()->getTournament();
        $qrPath = $this->qrService->writeGameToJpg($tournament, $game, $url, (int)$imgSize);
        /** @var Zend_Pdf_Resource_Image $img */
        $img = Zend_Pdf_Resource_ImageFactory::factory($qrPath);
        $page->drawImageExt($img, new Rectangle(new HorizontalLine($top->getStart(), $imgSize), $imgSize));
    }

    protected function getQrCodeUrlPrefix(GameNotesPage $page, AgainstGame|TogetherGame $game): string
    {
        $suffix = ($game instanceof AgainstGame) ? 'against' : 'together';
        return $page->getParent()->getUrl() . 'admin/game' . $suffix . '/' .
            (string)$page->getParent()->getTournament()->getId() . '/';
    }

    abstract protected function drawScore(
        GameNotesPage $page,
        AgainstGame|TogetherGame $game,
        HorizontalLine $top
    ): void;

    public function getNrOfScoreLines(Round $round, CompetitionSport $competitionSport): int
    {
        return $this->getNrOfScoreLinesHelper(
            $round->getValidScoreConfig($competitionSport),
            $round->getNumber()->getValidPlanningConfig()->getExtension()
        );
    }

    protected function getNrOfScoreLinesHelper(ScoreConfig $firstScoreConfig, bool $extension): int
    {
        if ($firstScoreConfig === $firstScoreConfig->getCalculate()) {
            return 1;
        }

        $nrOfScoreLines = (($firstScoreConfig->getCalculate()->getMaximum() * 2) - 1) + ($extension ? 1 : 0);
        if ($nrOfScoreLines < 1) {
            $nrOfScoreLines = 5;
        }
//        $maxNrOfScoreLines = AllInOneGameNotesPage::OnePageMaxNrOfScoreLines - ($extension ? 1 : 0);
//        if ($nrOfScoreLines > $maxNrOfScoreLines) {
//            $nrOfScoreLines = $maxNrOfScoreLines;
//        }
        return $nrOfScoreLines;
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

    protected function getDirectionName(ScoreConfig $scoreConfig): string
    {
        return $this->translationService->getScoreDirection($scoreConfig->getDirection());
    }
}
