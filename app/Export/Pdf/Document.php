<?php

declare(strict_types=1);

namespace App\Export\Pdf;

use FCToernooi\Tournament;
use Sports\Competition\Sport as CompetitionSport;
use Sports\Competitor\Map as CompetitorMap;
use Sports\Game\Against as AgainstGame;
use Sports\Game\State as GameState;
use Sports\Game\Together as TogetherGame;
use Sports\NameService;
use Sports\Poule;
use Sports\Round;
use Sports\Round\Number as RoundNumber;
use Sports\Structure;
use Zend_Pdf;
use Zend_Pdf_Font;
use Zend_Pdf_Resource_Font;

/**
 * @psalm-suppress PropertyNotSetInConstructor
 */
class Document extends Zend_Pdf
{
    protected NameService|null $nameService = null;
    protected CompetitorMap|null $competitorMap = null;

    /**
     * @var array<string, float>
     */
    protected array $widthText = [];

    public function __construct(
        protected Tournament $tournament,
        protected Structure $structure,
        protected string $url
    ) {
        parent::__construct();
    }

    public function getFontHeight(): int
    {
        return 14;
    }

    public function getFontHeightSubHeader(): int
    {
        return 16;
    }

    public function render($newSegmentOnly = false, $outputStream = null): string
    {
        $this->fillContent();
        return parent::render($newSegmentOnly, $outputStream);
    }

    public function getFont(bool $bBold = false, bool $bItalic = false): Zend_Pdf_Resource_Font
    {
        $suffix = 'times.ttf';
        if ($bBold === true and $bItalic === false) {
            $suffix = 'timesbd.ttf';
        } elseif ($bBold === false and $bItalic === true) {
            $suffix = 'timesi.ttf';
        } elseif ($bBold === true and $bItalic === true) {
            $suffix = 'timesbi.ttf';
        }
        $sFontDir = __DIR__ . '/../../../fonts/';
        return Zend_Pdf_Font::fontWithPath($sFontDir . $suffix);
    }

    protected function fillContent(): void
    {
       throw new \Exception('should be implemented', E_ERROR);
    }



    /**
     * @param Round $round
     * @param CompetitionSport $competitionSport
     * @return list<AgainstGame>
     */
    protected function getAgainstGames(Round $round, CompetitionSport $competitionSport): array
    {
        $games = $round->getGamesWithState(GameState::Created);
        $filtered = array_filter($games, function (AgainstGame|TogetherGame $game) use ($competitionSport): bool {
            return $game->getCompetitionSport() === $competitionSport && $game instanceof AgainstGame;
        });
        return array_values($filtered);
    }

    /**
     * @param Round $round
     * @param CompetitionSport $competitionSport
     * @return list<TogetherGame>
     */
    protected function getSingleGames(Round $round, CompetitionSport $competitionSport): array
    {
        $games = $round->getGamesWithState(GameState::Created);
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

    public function getUrl(): string
    {
        return $this->url;
    }

    public function gamesOnSameDay(RoundNumber $roundNumber): bool
    {
        $dateOne = $roundNumber->getFirstStartDateTime();
        $dateTwo = $roundNumber->getLastStartDateTime();
//        if ($dateOne === null && $dateTwo === null) {
//            return true;
//        }
        return $dateOne->format('Y-m-d') === $dateTwo->format('Y-m-d');
    }

    public function getNameService(): NameService
    {
        if ($this->nameService === null) {
            $this->nameService = new NameService($this->getPlaceLocationMap());
        }
        return $this->nameService;
    }

    public function getPlaceLocationMap(): CompetitorMap
    {
        if ($this->competitorMap === null) {
            $competitors = array_values($this->tournament->getCompetitors()->toArray());
            $this->competitorMap = new CompetitorMap($competitors);
        }
        return $this->competitorMap;
    }
}
