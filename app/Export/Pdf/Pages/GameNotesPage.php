<?php

declare(strict_types=1);

namespace App\Export\Pdf\Pages;

use App\Export\Pdf\Configs\HeaderConfig;
use App\Export\Pdf\Documents\GameNotesDocument as GameNotesDocument;
use App\Export\Pdf\Line\Horizontal as HorizontalLine;
use App\Export\Pdf\Pages;
use App\Export\Pdf\Page as ToernooiPdfPage;
use App\Export\Pdf\Point;
use App\ImageSize;
use Sports\Game\Against as AgainstGame;
use Sports\Game\Together as TogetherGame;
use Zend_Pdf_Color_Html;
use Zend_Pdf_Page;

/**
 * @template-extends ToernooiPdfPage<GameNotesDocument>
 */
class GameNotesPage extends ToernooiPdfPage
{
    public function __construct(
        mixed $parent,
        mixed $param1
    ) {
        parent::__construct($parent, $param1);
        $this->setFont($this->helper->getTimesFont(), $this->parent->getConfig()->getFontHeight());
        $this->setLineWidth(0.5);
    }

    public function getParent(): GameNotesDocument
    {
        return $this->parent;
    }

    private function getSubHeader(AgainstGame|TogetherGame $game): string
    {
        return $this->getRefereeName($game) ?? 'wedstrijdbriefje';
    }

    private function getRefereeName(AgainstGame|TogetherGame $game): string|null
    {
        $referee = $game->getReferee();
        if ($referee !== null) {
            return $referee->getInitials();
        }

        $refereePlace = $game->getRefereePlace();
        if ($refereePlace !== null) {
            $startLocation = $refereePlace->getStartLocation();
            if ($startLocation !== null) {
                $competitor = $this->parent->getStartLocationMap()->getCompetitor($startLocation);
                return $competitor?->getName();
            }
        }
        return null;
    }

    public function renderGames(AgainstGame|TogetherGame $gameOne, AgainstGame|TogetherGame|null $gameTwo): void
    {
        $subHeader = $this->getSubHeader($gameOne);
        $logoPath = $this->parent->getTournamentLogoPath(ImageSize::Small);
        $y = $this->drawHeader($this->parent->getTournament()->getName(), $logoPath, $subHeader);
        $top = new HorizontalLine(new Point(ToernooiPdfPage::PAGEMARGIN, $y), $this->getDisplayWidth());
        $this->renderGame($gameOne, $top);

        $this->setLineColor(new Zend_Pdf_Color_Html('black'));
        if ($gameTwo === null) {
            return;
        }

        $this->setLineDashingPattern([10, 10]);
        $this->drawLine(
            self::PAGEMARGIN,
            $this->getHeight() / 2,
            $this->getWidth() - self::PAGEMARGIN,
            $this->getHeight() / 2
        );
        $this->setLineDashingPattern(Zend_Pdf_Page::LINE_DASHING_SOLID);

        $subHeader = $this->getSubHeader($gameTwo);
        $y = $this->drawHeader(
            $this->parent->getTournament()->getName(),
            $this->parent->getTournamentLogoPath(ImageSize::Small),
            $subHeader,
            new HeaderConfig(
                ($this->getHeight() / 2) - self::PAGEMARGIN
            )
        );
        $top = new HorizontalLine(new Point(ToernooiPdfPage::PAGEMARGIN, $y), $this->getDisplayWidth());
        $this->renderGame($gameTwo, $top);
    }

    private function renderGame(AgainstGame|TogetherGame $game, HorizontalLine $top): void
    {
        $this->parent->createDrawer($game)->renderGame($this, $game, $top);
    }
}
