<?php

declare(strict_types=1);

namespace App\Export\Pdf;

use App\Export\Pdf\Configs\GameLineConfig;
use App\Export\Pdf\Configs\GameNotesConfig;
use App\Export\Pdf\Configs\GamesConfig;
use App\Export\Pdf\Configs\LockerRoomConfig;
use App\Export\Pdf\Configs\LockerRoomLabelConfig;
use App\Export\Pdf\Configs\PoulePivotConfig;
use App\Export\Pdf\Configs\QRCodeConfig;
use App\Export\Pdf\Configs\Structure\CategoryConfig;
use App\Export\Pdf\Configs\Structure\PouleConfig;
use App\Export\Pdf\Configs\Structure\RoundConfig;
use App\Export\Pdf\Configs\Structure\StructureConfig;
use App\Export\Pdf\Document as PdfDocument;
use App\Export\Pdf\Document\GameNotes as GameNotesDocument;
use App\Export\Pdf\Document\LockerRooms as LockerRoomsDocument;
use App\Export\Pdf\Document\Planning\Games as GamesDocument;
use App\Export\Pdf\Document\Planning\GamesPerField as GamesPerFieldDocument;
use App\Export\Pdf\Document\Planning\GamesPerPoule as GamesPerPouleDocument;
use App\Export\Pdf\Document\PoulePivotTables as PoulePivotTablesDocument;
use App\Export\Pdf\Document\QRCode as QRCodeDocument;
use App\Export\Pdf\Document\Structure as StructureDocument;
use App\Export\PdfProgress;
use App\Export\PdfSubject;
use FCToernooi\Tournament;
use Selective\Config\Configuration;
use Sports\Competition\Sport as CompetitionSport;
use Sports\Game\Against as AgainstGame;
use Sports\Game\State as GameState;
use Sports\Game\Together as TogetherGame;
use Sports\Poule;
use Sports\Round;
use Sports\Structure;

/**
 * @psalm-suppress PropertyNotSetInConstructor
 */
class DocumentFactory
{
    protected string $wwwUrl;

    public function __construct(Configuration $config)
    {
        $this->wwwUrl = $config->getString('www.wwwurl');
    }

    public function createSubject(
        Tournament $tournament,
        Structure $structure,
        PdfSubject $subject,
        PdfProgress $progress,
        float $maxSubjectProgress
    ): PdfDocument {
        switch ($subject) {
            case PdfSubject::Structure:
                $config = new StructureConfig(
                    15,
                    new CategoryConfig(
                        18,
                        14,
                        15,
                        new RoundConfig(
                            18,
                            14,
                            15,
                            new PouleConfig(2, 14)
                        )
                    )
                );
                return new StructureDocument(
                    $tournament,
                    $structure,
                    $this->wwwUrl,
                    $progress,
                    $maxSubjectProgress,
                    $config
                );
            case PdfSubject::PoulePivotTables:
                $config = new PoulePivotConfig();
                return new PoulePivotTablesDocument(
                    $tournament,
                    $structure,
                    $this->wwwUrl,
                    $progress,
                    $maxSubjectProgress,
                    $config
                );
            case PdfSubject::Planning:
                $gamesCfg = new GamesConfig(20, 18, 14);
                $gameLineCfg = new GameLineConfig(12, 10);
                return new GamesDocument(
                    $tournament,
                    $structure,
                    $this->wwwUrl,
                    $progress,
                    $maxSubjectProgress,
                    $gamesCfg,
                    $gameLineCfg
                );
            case PdfSubject::GamesPerPoule:
                $gamesCfg = new GamesConfig(20, 18, 14);
                $gameLineCfg = new GameLineConfig(12, 11);
                return new GamesPerPouleDocument(
                    $tournament,
                    $structure,
                    $this->wwwUrl,
                    $progress,
                    $maxSubjectProgress,
                    $gamesCfg,
                    $gameLineCfg
                );
            case PdfSubject::GamesPerField:
                $gamesCfg = new GamesConfig(20, 18, 14);
                $gameLineCfg = new GameLineConfig(12, 10);
                return new GamesPerFieldDocument(
                    $tournament,
                    $structure,
                    $this->wwwUrl,
                    $progress,
                    $maxSubjectProgress,
                    $gamesCfg,
                    $gameLineCfg
                );
            case PdfSubject::GameNotes:
                $config = new GameNotesConfig();
                return new GameNotesDocument(
                    $tournament,
                    $structure,
                    $this->wwwUrl,
                    $progress,
                    $maxSubjectProgress,
                    $config
                );
            case PdfSubject::QrCode:
                $config = new QRCodeConfig();
                return new QRCodeDocument(
                    $tournament,
                    $structure,
                    $this->wwwUrl,
                    $progress,
                    $maxSubjectProgress,
                    $config
                );
            case PdfSubject::LockerRooms:
                $config = new LockerRoomConfig(20, 16, 12);
                $labelConfig = new LockerRoomLabelConfig();
                return new LockerRoomsDocument(
                    $tournament,
                    $structure,
                    $this->wwwUrl,
                    $progress,
                    $maxSubjectProgress,
                    $config,
                    $labelConfig
                );
        }
        throw new \Exception('unknown subject', E_ERROR);
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
}
