<?php

use Voetbal\Planning\Game;
use Voetbal\Game as GameBase;
use Voetbal\NameService;
use Voetbal\Planning\Batch as PlanningResourceBatch;
use Monolog\Logger;

function consoleBatch(PlanningResourceBatch $batch ) {
    echo '------batch ' . $batch->getNumber() . ' assigned -------------' . PHP_EOL;
    consoleBatchHelper($batch->getRoot());
}

function consoleBatchHelper(PlanningResourceBatch $batch) {
    consoleGames($batch->getGames(), $batch);
    if ($batch->hasNext()) {
        consoleBatchHelper($batch->getNext());
    }
}

/**
 * @param array|Game[] $games
 * @param PlanningResourceBatch|null $batch
 */
function consoleGames( Logger $logger, array $games, PlanningResourceBatch $batch = null) {
    foreach( $games as $game ) {
        consoleGame($logger, $game, $batch);
    }
}

function useColors( Logger $logger ): bool {
    foreach( $logger->getHandlers() as $handler ) {
        if( $handler->getUrl() !== "php://stdout" ) {
            return false;
        }
    }
    return true;
}

function consoleGame(Logger $logger, Game $game, PlanningResourceBatch $batch = null) {
    $useColors = useColors( $logger );
    $refDescr = ($game->getRefereePlace() ? $game->getRefereePlace()->getLocation() : '');
    $refNumber = $useColors ? ($game->getRefereePlace() ? $game->getRefereePlace()->getNumber() : 0 ) : -1;
    $batchColor = $useColors ? ($game->getBatchNr() % 10) : -1;
    $fieldColor = $useColors ? $game->getField()->getNumber() : -1;
    $logger->info( consoleColor($batchColor, 'batch ' . $game->getBatchNr() ) . " " .
        // . '(' . $game->getRoundNumber(), 2 ) . consoleString( $game->getSubNumber(), 2 ) . ") "
        'poule ' . $game->getPoule()->getNumber()
        . ', ' . consolePlaces($game, GameBase::HOME, $useColors, $batch)
        . ' vs ' . consolePlaces($game, GameBase::AWAY, $useColors, $batch)
        . ' , ref ' . consoleColor($refNumber, $refDescr)

        . ', ' . consoleColor($fieldColor, 'field ' . $game->getField()->getNumber())
        . ', sport ' . $game->getField()->getSport()->getNumber()
    );
}

function consolePlaces( Game $game, bool $homeAway, bool $useColors, PlanningResourceBatch $batch = null ): string {
    $placesAsArrayOfStrings = $game->getPlaces($homeAway)->map( function( $gamePlace ) use ($useColors, $batch) {
        $colorNumber = $useColors ? $gamePlace->getPlace()->getNumber() : -1;
        $gamesInARow = $batch ? ('(' . $batch->getGamesInARow($gamePlace->getPlace()) . ')') : '';
        return consoleColor($colorNumber, $gamePlace->getPlace()->getLocation() . $gamesInARow);
    })->toArray();
    return implode( $placesAsArrayOfStrings, ' & ');
}

function consoleColor(int $number, string $content ): string {
    $sColor = null;
    if ($number === 1) {
        $sColor = '0;31'; // red
    } else if ($number === 2) {
        $sColor = '0;32'; // green
    } else if ($number === 3) {
        $sColor = '0;34'; // blue;
    } else if ($number === 4) {
        $sColor = '1;33'; // yellow
    } else if ($number === 5) {
        $sColor = '0;35'; // purple
    } else if ($number === 6) {
        $sColor = '0;37'; // light_gray
    } else if ($number === 7) {
        $sColor = '0;36'; // cyan
    } else if ($number === 8) {
        $sColor = '1;32'; // light green
    } else if ($number === 9) {
        $sColor = '1;36'; // light cyan
    } else if ($number === -1) {
        return $content;
    } else {
        $sColor = '1;37'; // white
    }

    //    'black'] = '0;30';
    //    'dark_gray'] = '1;30';
    //    'green'] = ;
    //    'light_red'] = '1;31';
    //    'purple'] = '0;35';
    //    'light_purple'] = '1;35';
    //    'brown'] = '0;33';

    $coloredString = "\033[" . $sColor . "m";
    return $coloredString .  $content . "\033[0m";

}

function consoleString($value, int $minLength): string {
    $str = '' . $value;
    while ( strlen($str) < $minLength) {
        $str = ' ' . $str;
    }
    return $str;
}
