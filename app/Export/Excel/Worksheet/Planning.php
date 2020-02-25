<?php
/**
 * Created by PhpStorm.
 * User: coen
 * Date: 9-11-18
 * Time: 19:28
 */

namespace App\Export\Excel\Worksheet;

use App\Export\Excel\Spreadsheet;
use App\Export\Excel\Worksheet as FCToernooiWorksheet;
use League\Period\Period;
use Voetbal\Round;
use Voetbal\Game;
use Voetbal\Round\Number as RoundNumber;
use Voetbal\NameService;
use Voetbal\Sport\ScoreConfig\Service as SportScoreConfigService;

abstract class Planning extends FCToernooiWorksheet
{
    /**
     * @var SportScoreConfigService
     */
    protected $sportScoreConfigService;
    /**
     * @var bool
     */
    protected $drewbreak;

    /**
     * @var Period
     */
    protected $tournamentBreak;

    use GamesTrait;

    const COLUMN_POULE = 1;
    const COLUMN_START = 2;
    const COLUMN_FIELD = 3;
    const COLUMN_HOME = 4;
    const COLUMN_SCORE = 5;
    const COLUMN_AWAY = 6;
    const COLUMN_REFEREE = 7;

    const NR_OF_COLUMNS = 7;

    public function __construct(Spreadsheet $parent, string $title, int $index)
    {
        parent::__construct($parent, $title);
        $parent->addSheet($this, $index);
        $this->sportScoreConfigService = new SportScoreConfigService();
        $this->drewbreak = false;
        $this->tournamentBreak = $parent->getTournament()->getBreak();
        $this->setCustomHeader();
    }

    public function drawBreakBeforeGame(Game $game): bool
    {
        if ($this->tournamentBreak === null) {
            return false;
        }
        if ($this->drewbreak === true) {
            return false;
        }
        return $game->getStartDateTime()->getTimestamp() === $this->tournamentBreak->getEndDate()->getTimestamp();
    }

    /**
     * add winnerslosers if roundnumber is 2 and has sibling
     *
     * @param Round $round
     * @param NameService $nameService
     * @return string
     */
//    protected function getRoundNameStructure( Round $round, NameService $nameService ): string
//    {
//        $roundName = $nameService->getRoundName( $round );
//        if( $round->getNumber() === 2 and $round->getOpposingRound() !== null ) {
//            $roundName .= ' - ' . $nameService->getWinnersLosersDescription($round->getWinnersOrlosers()) . 's';
//        }
//        return $roundName;
//    }

    abstract public function draw();

}