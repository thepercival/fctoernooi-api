<?php

declare(strict_types=1);

namespace App\Export\Pdf;

use App\Export\Pdf\Drawers\Helper;
use App\Export\PdfProgress;
use App\ImagePathResolver;
use App\ImageProps;
use App\ImageSize;
use FCToernooi\Tournament;
use IntlDateFormatter;
use Sports\Competition\Sport as CompetitionSport;
use Sports\Competitor\StartLocationMap;
use Sports\Game\Against as AgainstGame;
use Sports\Game\State as GameState;
use Sports\Game\Together as TogetherGame;
use Sports\Poule;
use Sports\Structure;
use Sports\Structure\NameService as StructureNameService;
use Zend_Pdf;

/**
 * @psalm-suppress PropertyNotSetInConstructor
 */
abstract class Document extends Zend_Pdf
{
    public const THEME_BG = '#93c54b';

    protected StructureNameService|null $structureNameService = null;
    protected StartLocationMap|null $startLocationMap = null;
    protected Helper $helper;

    /**
     * @var array<string, float>
     */
    protected array $widthText = [];

    public function __construct(
        protected Tournament $tournament,
        protected Structure $structure,
        protected ImagePathResolver $imagePathResolver,
        protected PdfProgress $progress,
        protected float $maxSubjectProgress
    ) {
        parent::__construct();
        $this->helper = new Helper();
    }

    public function getDateFormatter(string $pattern = ''): IntlDateFormatter {
        return new IntlDateFormatter(
            'nl_NL',
            IntlDateFormatter::FULL,
            IntlDateFormatter::FULL,
            'Europe/Amsterdam',
            IntlDateFormatter::GREGORIAN,
            $pattern
        );
    }

    protected function getHelper(): Helper
    {
        return $this->helper;
    }

    public function getTournamentLogoPath(ImageSize|null $imageSize): string|null {
        $logoExtension = $this->tournament->getLogoExtension();
        if( $logoExtension === null ){
            return null;
        }
        $imageProps = null;
        if( $imageSize !== null ) {
            $imageProps = new ImageProps(ImageProps::Suffix . $imageSize->value, $imageSize);
        }
        return $this->imagePathResolver->getPath($this->tournament, $imageProps, $logoExtension);
    }

    public function render($newSegmentOnly = false, $outputStream = null): string
    {
        $this->renderCustom();
        $retVal = parent::render($newSegmentOnly, $outputStream);
        $this->updateProgress();
        return $retVal;
    }

    abstract protected function renderCustom(): void;
//    {
//        throw new \Exception('should be implemented', E_ERROR);
//    }

    protected function updateProgress(): void
    {
        $this->progress->addProgression($this->maxSubjectProgress);
    }

    /**
     * @param Poule $poule
     * @param CompetitionSport $competitionSport
     * @return list<AgainstGame>
     */
    protected function getAgainstGames(Poule $poule, CompetitionSport $competitionSport): array
    {
        $games = $poule->getGamesWithState(GameState::Created);
        $filtered = array_filter($games, function (AgainstGame|TogetherGame $game) use ($competitionSport): bool {
            return $game->getCompetitionSport() === $competitionSport && $game instanceof AgainstGame;
        });
        return array_values($filtered);
    }

    /**
     * @param Poule $poule
     * @param CompetitionSport $competitionSport
     * @return list<TogetherGame>
     */
    protected function getSingleGames(Poule $poule, CompetitionSport $competitionSport): array
    {
        $games = $poule->getGamesWithState(GameState::Created);
        $filtered = array_filter($games, function (AgainstGame|TogetherGame $game) use ($competitionSport): bool {
            return $game->getCompetitionSport() === $competitionSport && $game instanceof TogetherGame;
        });
        return array_values($filtered);
    }

    /**
     * @param Poule $poule
     * @param CompetitionSport $competitionSport
     * @return list<TogetherGame>
     */
    protected function getAllInOneGames(Poule $poule, CompetitionSport $competitionSport): array
    {
        $games = $poule->getGamesWithState(GameState::Created);
        $games = array_filter($games, function (AgainstGame|TogetherGame $game) use ($competitionSport): bool {
            return $game->getCompetitionSport() === $competitionSport && $game instanceof TogetherGame;
        });
        return array_values($games);
    }

    public function hasTextWidth(string $key): bool
    {
        return array_key_exists($key, $this->widthText);
    }

    public function getTextWidth(string $key): float
    {
        return $this->widthText[$key];
    }

    public function setTextWidth(string $key, float $value): float
    {
        $this->widthText[$key] = $value;
        return $value;
    }

    public function getStructure(): Structure
    {
        return $this->structure;
    }

    public function getTournament(): Tournament
    {
        return $this->tournament;
    }

    public function getWwwUrl(): string
    {
        return $this->imagePathResolver->getWwwUrl();
    }

    public function getStructureNameService(): StructureNameService
    {
        if ($this->structureNameService === null) {
            $this->structureNameService = new StructureNameService($this->getStartLocationMap());
        }
        return $this->structureNameService;
    }

    public function getStartLocationMap(): StartLocationMap
    {
        if ($this->startLocationMap === null) {
            $competitors = array_values($this->tournament->getCompetitors()->toArray());
            $this->startLocationMap = new StartLocationMap($competitors);
        }
        return $this->startLocationMap;
    }
}
