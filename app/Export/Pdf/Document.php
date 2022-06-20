<?php

declare(strict_types=1);

namespace App\Export\Pdf;

use App\Export\PdfProgress;
use FCToernooi\Tournament;
use Sports\Competition\Sport as CompetitionSport;
use Sports\Competitor\StartLocationMap;
use Sports\Game\Against as AgainstGame;
use Sports\Game\State as GameState;
use Sports\Game\Together as TogetherGame;
use Sports\Poule;
use Sports\Round;
use Sports\Round\Number as RoundNumber;
use Sports\Structure;
use Sports\Structure\NameService as StructureNameService;
use Zend_Pdf;

/**
 * @psalm-suppress PropertyNotSetInConstructor
 */
class Document extends Zend_Pdf
{
    protected StructureNameService|null $structureNameService = null;
    protected StartLocationMap|null $startLocationMap = null;

    /**
     * @var array<string, float>
     */
    protected array $widthText = [];

    public function __construct(
        protected Tournament $tournament,
        protected Structure $structure,
        protected string $url,
        protected PdfProgress $progress,
        protected float $maxSubjectProgress
    ) {
        parent::__construct();
    }

    public function render($newSegmentOnly = false, $outputStream = null): string
    {
        $this->fillContent();
        $retVal = parent::render($newSegmentOnly, $outputStream);
        $this->updateProgress();
        return $retVal;
    }

    protected function fillContent(): void
    {
        throw new \Exception('should be implemented', E_ERROR);
    }

    protected function updateProgress(): void
    {
        $this->progress->addProgression($this->maxSubjectProgress);
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
