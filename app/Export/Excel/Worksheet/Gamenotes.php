<?php
/**
 * Created by PhpStorm.
 * User: coen
 * Date: 2-2-18
 * Time: 15:03
 */

namespace App\Export\Excel\Worksheet;

use App\Export\Excel\Spreadsheet;
use App\Export\Excel\Worksheet as WorksheetBase;
use App\Export\Pdf\Page as ToernooiPdfPage;
use FCToernooi\TranslationService;
use PhpOffice\PhpSpreadsheet\Style\Style;
use Voetbal\Game;
use Voetbal\Round;
use Voetbal\Poule;
use Voetbal\NameService;
use App\Export\Excel\Worksheet as FCToernooiWorksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use Voetbal\Round\Number as RoundNumber;
use Voetbal\Sport\ScoreConfig as SportScoreConfig;
use Voetbal\Sport\ScoreConfig\Service as SportScoreConfigService;

class Gamenotes extends FCToernooiWorksheet
{
    /**
     * @var SportScoreConfigService
     */
    protected $sportScoreConfigService;
    /**
     * @var TranslationService
     */
    protected $translationService;

    public function __construct( Spreadsheet $parent = null )
    {
        parent::__construct( $parent, 'wedstrijdbriefjes' );
        $parent->addSheet($this, Spreadsheet::INDEX_GAMENOTES );
        $this->setWidthColumns();
        $this->setCustomHeader();
        $this->sportScoreConfigService = new SportScoreConfigService();
        $this->translationService = new TranslationService();
    }

    protected function setWidthColumns()
    {
        $this->getColumnDimensionByColumn( 1 )->setWidth(10);
        $this->getColumnDimensionByColumn( 2 )->setWidth(28);
        $this->getColumnDimensionByColumn( 3 )->setWidth(4);
        $this->getColumnDimensionByColumn( 4 )->setWidth(14);
        $this->getColumnDimensionByColumn( 5 )->setWidth(14);
    }

    protected function getMaxNrOfColumns(): int {
        return 4;
    }

    public function draw() {
        $row = 1;
        $games = $this->getParent()->getScheduledGames( $this->getParent()->getStructure()->getRootRound() );
        foreach( $games as $game ) {
            $row = $this->drawGame( $game, $row );
        }
    }

    public function drawGame( Game $game, int $row ): int
    {
        $range = $this->range( 1, $row, 5, $row );
        $this->border( $this->getStyle($range), 'top' );

        $roundNumber = $game->getRound()->getNumber();
        $planningConfig = $roundNumber->getValidPlanningConfig();

        $bNeedsRanking = $game->getPoule()->needsRanking();

        $nameService = $this->getParent()->getNameService();

        // ronde
        $row = $this->drawGameRow( $row, "ronde", $nameService->getRoundNumberName($roundNumber) );

        // poule
        $row = $this->drawGameRow( $row, $bNeedsRanking ? "poule" : "wedstrijd", $nameService->getPouleName($game->getPoule(), false) );

        // plekken
        $home = $nameService->getPlacesFromName( $game->getPlaces( Game::HOME ), false, !$planningConfig->getTeamup() );
        $away = $nameService->getPlacesFromName( $game->getPlaces( Game::AWAY ), false, !$planningConfig->getTeamup() );
        $row = $this->drawGameRow( $row, "plekken", $home . " - " . $away );

        // speelronde
        if( $bNeedsRanking ) {
            $row = $this->drawGameRow( $row, "speelronde", $game->getRound()->getNumber()->getNumber() );
        }


        if( $roundNumber->getValidPlanningConfig()->getEnableTime() ) {
            setlocale(LC_ALL, 'nl_NL.UTF-8'); //
            $localDateTime = $game->getStartDateTime()->setTimezone(new \DateTimeZone('Europe/Amsterdam'));
            $dateTime = strtolower( $localDateTime->format("H:i") . "     " . strftime("%a %d %b %Y", $localDateTime->getTimestamp() ) );
            // $dateTime = strtolower( $localDateTime->format("H:i") . "     " . $localDateTime->format("D d M") );
            $duration = $planningConfig->getMinutesPerGame() . ' min.';
            if( $planningConfig->hasExtension() === true ) {
                $duration .= ' (' . $planningConfig->getMinutesPerGameExt() . ' min.)';
            }
            // tijdstip
            $row = $this->drawGameRow( $row, "tijdstip", $dateTime );

            // duur
            $row = $this->drawGameRow( $row, "duur", $duration );
        }

        // field
        $fieldDescription = $game->getField()->getName();
        if( $roundNumber->getCompetition()->hasMultipleSportConfigs() ) {
            $fieldDescription .= " - " . $game->getField()->getSport()->getName();
        }
        $row = $this->drawGameRow( $row, "veld", $fieldDescription );

        if( $game->getReferee() !== null ) {
            $row = $this->drawGameRow( $row, "scheidsrechter", $game->getReferee()->getInitials() );
        } else if( $game->getRefereePlace() !== null ) {
            $refPlace = $nameService->getPlaceName( $game->getRefereePlace(), true, true);
            $row = $this->drawGameRow( $row, "scheidsrechter",$refPlace );
        }
        $row++;

        {
            $cell = $this->getCellByColumnAndRow(1, $row);
            $cell->getStyle()->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $cell->setValue( "wedstrijd" );

            $home = $nameService->getPlacesFromName( $game->getPlaces( Game::HOME ), true, true );
            $cell = $this->getCellByColumnAndRow(2, $row);
            $cell->getStyle()->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $cell->setValue( $home );

            $cell = $this->getCellByColumnAndRow(3, $row);
            $cell->getStyle()->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $cell->setValue("-");

            $away = $nameService->getPlacesFromName( $game->getPlaces( Game::HOME ), true, true );
            $cell = $this->getCellByColumnAndRow(4, $row);
            $cell->getStyle()->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $cell->setValue( $away);

            $row += 2;
        }

        $inputScoreConfig = $this->sportScoreConfigService->getInput( $game->getSportScoreConfig() );
        $calculateScoreConfig = $this->sportScoreConfigService->getCalculate( $game->getSportScoreConfig() );

        $dots = '...............';
        if( $inputScoreConfig !== null ) {
            $inputDescr = $this->getInputScoreConfigDescription( $inputScoreConfig, $planningConfig->getEnableTime() );
            if( $inputScoreConfig !== $calculateScoreConfig ) {
                $nrOfScoreLines = $this->getNrOfScoreLines($calculateScoreConfig->getMaximum());
                for( $gameUnitNr = 1 ; $gameUnitNr <= $nrOfScoreLines ; $gameUnitNr++ ) {
                    $descr = $this->translationService->getScoreNameMultiple(TranslationService::language, $calculateScoreConfig) . ' ' . $gameUnitNr;
                    {
                        $cell = $this->getCellByColumnAndRow(1, $row);
                        $cell->getStyle()->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                        $cell->setValue( $descr );

                        $cell = $this->getCellByColumnAndRow(2, $row);
                        $cell->getStyle()->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                        $cell->setValue( $dots );

                        $cell = $this->getCellByColumnAndRow(3, $row);
                        $cell->getStyle()->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                        $cell->setValue("-");

                        $cell = $this->getCellByColumnAndRow(4, $row);
                        $cell->getStyle()->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                        $cell->setValue( $dots);

                        $cell = $this->getCellByColumnAndRow(5, $row);
                        $cell->getStyle()->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                        $cell->setValue( $inputDescr );

                        $row ++;
                    }
                }
            } else {
                {
                    $cell = $this->getCellByColumnAndRow(1, $row);
                    $cell->getStyle()->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    $cell->setValue( 'uitslag' );

                    $cell = $this->getCellByColumnAndRow(2, $row);
                    $cell->getStyle()->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    $cell->setValue( $dots );

                    $cell = $this->getCellByColumnAndRow(3, $row);
                    $cell->getStyle()->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $cell->setValue("-" );

                    $cell = $this->getCellByColumnAndRow(4, $row);
                    $cell->getStyle()->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                    $cell->setValue( $dots );

                    $cell = $this->getCellByColumnAndRow(5, $row);
                    $cell->getStyle()->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    $cell->setValue( $inputDescr );

                    $row++;
                }
            }
        }

        $row += 2;

        if( $planningConfig->hasExtension() ) {
            $cell = $this->getCellByColumnAndRow(1, $row);
            $cell->getStyle()->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $cell->setValue( 'na verleng.' );

            $cell = $this->getCellByColumnAndRow(2, $row);
            $cell->getStyle()->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $cell->setValue( $dots );

            $cell = $this->getCellByColumnAndRow(3, $row);
            $cell->getStyle()->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $cell->setValue("-" );

            $cell = $this->getCellByColumnAndRow(4, $row);
            $cell->getStyle()->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $cell->setValue( $dots );

            if( $inputScoreConfig !== null ) {
                $name = $this->translationService->getScoreNameMultiple(TranslationService::language, $inputScoreConfig);
                $cell = $this->getCellByColumnAndRow(5, $row);
                $cell->getStyle()->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $cell->setValue( $name );
            }
        }
        return $this->getStartRow( $row );
    }

    public function drawGameRow( int $row, string $description, string $value, int $align = null ): int
    {
        $cell = $this->getCellByColumnAndRow(2, $row);
        $cell->getStyle()->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $cell->setValue( $description );

        $cell = $this->getCellByColumnAndRow(3, $row);
        $cell->getStyle()->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $cell->setValue(":");

        $align = $align !== null ? $align : Alignment::HORIZONTAL_LEFT;
        $cell = $this->getCellByColumnAndRow(4, $row);
        $cell->getStyle()->getAlignment()->setHorizontal($align);
        $cell->setValue( $value);

        return $row + 1;
    }

    protected function getInputScoreConfigDescription( SportScoreConfig $inputScoreConfig, $timeEnabled): string {
        $direction = $this->getDirectionName($inputScoreConfig);
        $name = $this->translationService->getScoreNameMultiple(TranslationService::language, $inputScoreConfig);
        if( $inputScoreConfig->getMaximum() === 0 || $timeEnabled === true ) {
            return $name;
        }
        return $direction . ' ' . $inputScoreConfig->getMaximum() . ' ' . $name;
    }

    protected function getDirectionName(SportScoreConfig $scoreConfig ) {
        return $scoreConfig->getDirection() === SportScoreConfig::UPWARDS ? 'naar' : 'vanaf';
    }

    protected function getNrOfScoreLines( int $scoreConfigMax ) : int {
        $nrOfScoreLines = ($scoreConfigMax * 2) - 1;
        if( $nrOfScoreLines > 5 ) {
            $nrOfScoreLines = 5;
        }
        return $nrOfScoreLines;
    }

    protected function getStartRow( int $row ): int {
        $middle = $this->getMiddle();
        $rest = ( $row % WorksheetBase::HEIGHT_IN_CELLS );
        if( $rest < $middle ) {
            return ($row - $rest) + $middle;
        }
        return ($row - $rest) + WorksheetBase::HEIGHT_IN_CELLS + 1;
    }

    protected function getMiddle(): int {
        return (int)(( WorksheetBase::HEIGHT_IN_CELLS + ( WorksheetBase::HEIGHT_IN_CELLS % 2 ) ) / 2);
    }
}