<?php

declare(strict_types=1);

namespace App\Export\Pdf;

use App\Export\Pdf\Configs\FrontPageConfig;
use App\Export\Pdf\Configs\GameLineConfig;
use App\Export\Pdf\Configs\GameNotesConfig;
use App\Export\Pdf\Configs\GamesConfig;
use App\Export\Pdf\Configs\IntroConfig;
use App\Export\Pdf\Configs\LockerRoomConfig;
use App\Export\Pdf\Configs\LockerRoomLabelConfig;
use App\Export\Pdf\Configs\PoulePivotConfig;
use App\Export\Pdf\Configs\QRCodeConfig;
use App\Export\Pdf\Configs\RegistrationFormConfig;
use App\Export\Pdf\Configs\Structure\CategoryConfig;
use App\Export\Pdf\Configs\Structure\PouleConfig;
use App\Export\Pdf\Configs\Structure\RoundConfig;
use App\Export\Pdf\Configs\Structure\StructureConfig;
use App\Export\Pdf\Documents\FrontPageDocument;
use App\Export\Pdf\Documents\GameNotesDocument;
use App\Export\Pdf\Documents\LockerRoomsDocument;
use App\Export\Pdf\Documents\Planning\GamesDocument;
use App\Export\Pdf\Documents\Planning\GamesPerFieldDocument;
use App\Export\Pdf\Document as FCToernooiPdfDocument;
use App\Export\Pdf\Documents\Planning\GamesPerPouleDocument;
use App\Export\Pdf\Documents\PoulePivotTablesDocument;
use App\Export\Pdf\Documents\QRCodeDocument;
use App\Export\Pdf\Documents\RegistrationFormDocument as RegistrationFormDocument;
use App\Export\Pdf\Documents\StructureDocument as StructureDocument;
use App\Export\Pdf\Documents\IntroDocument;
use App\Export\Pdf\Page as ToernooiPage;
use App\Export\PdfProgress;
use App\Export\PdfSubject;
use App\ImagePathResolver;
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
    protected ImagePathResolver $imagePathResolver;

    public function __construct(Configuration $config)
    {
        $this->imagePathResolver = new ImagePathResolver($config);
    }

    public function createSubject(
        Tournament $tournament,
        Tournament\RegistrationSettings|null $registrationSettings,
        Structure $structure,
        PdfSubject $subject,
        PdfProgress $progress,
        float $maxSubjectProgress
    ): FCToernooiPdfDocument {
        switch ($subject) {
            case PdfSubject::RegistrationForm:
                if( $registrationSettings === null) {
                    throw new \Exception('no registration settings found');
                }
                return new RegistrationFormDocument(
                    $tournament,
                    $registrationSettings,
                    $structure,
                    $this->imagePathResolver,
                    $progress,
                    $maxSubjectProgress,
                    new RegistrationFormConfig(18, 14)
                );
            case PdfSubject::Structure:
                $config = new StructureConfig(
                    new CategoryConfig(
                        18,
                        14,
                        15,
                        new RoundConfig(
                            18,
                            14,
                            15,
                            new PouleConfig(2, 14, 15)
                        )
                    )
                );
                return new StructureDocument(
                    $tournament,
                    $structure,
                    $this->imagePathResolver,
                    $progress,
                    $maxSubjectProgress,
                    $config
                );
            case PdfSubject::PoulePivotTables:
                return new PoulePivotTablesDocument(
                    $tournament,
                    $structure,
                    $this->imagePathResolver,
                    $progress,
                    $maxSubjectProgress,
                    new PoulePivotConfig()
                );
            case PdfSubject::Planning:
                $gamesCfg = new GamesConfig(20, 18, 14);
                $gameLineCfg = new GameLineConfig(12, 10);
                return new GamesDocument(
                    $tournament,
                    $structure,
                    $this->imagePathResolver,
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
                    $this->imagePathResolver,
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
                    $this->imagePathResolver,
                    $progress,
                    $maxSubjectProgress,
                    $gamesCfg,
                    $gameLineCfg
                );
            case PdfSubject::GameNotes:
                return new GameNotesDocument(
                    $tournament,
                    $structure,
                    $this->imagePathResolver,
                    $progress,
                    $maxSubjectProgress,
                    new GameNotesConfig()
                );
            case PdfSubject::QrCode:
                return new QRCodeDocument(
                    $tournament,
                    $structure,
                    $this->imagePathResolver,
                    $progress,
                    $maxSubjectProgress,
                    new QRCodeConfig()
                );
            case PdfSubject::LockerRooms:
                return new LockerRoomsDocument(
                    $tournament,
                    $structure,
                    $this->imagePathResolver,
                    $progress,
                    $maxSubjectProgress,
                    new LockerRoomConfig(20, 16, 12),
                    new LockerRoomLabelConfig()
                );
            case PdfSubject::FrontPage:
                return new FrontPageDocument(
                    $tournament,
                    $structure,
                    $this->imagePathResolver,
                    $progress,
                    $maxSubjectProgress,
                    new FrontPageConfig(ToernooiPage::PAGEMARGIN * 3, 28)
                );
            case PdfSubject::Intro:
                return new IntroDocument(
                    $tournament,
                    $structure,
                    $this->imagePathResolver,
                    $progress,
                    $maxSubjectProgress,
                    new IntroConfig(18, 14)
                );
//            case PdfSubject::Sponsor:
//                if( $registrationSettings === null) {
//                    throw new \Exception('no registration settings found');
//                }
//                return new FrontPageDocument(
//                    $tournament,
//                    $registrationSettings,
//                    $structure,
//                    $this->imagePathResolver,
//                    $progress,
//                    $maxSubjectProgress,
//                    new RegistrationFormConfig(18, 14)
//                );
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
