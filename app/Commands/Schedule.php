<?php

declare(strict_types=1);

namespace App\Commands;

use App\Command;
use Psr\Container\ContainerInterface;
use Selective\Config\Configuration;
use SportsHelpers\GameMode;
use SportsHelpers\Sport\Variant\Against\H2h as AgainstH2h;
use SportsHelpers\Sport\Variant\Against\GamesPerPlace as AgainstGpp;
use SportsHelpers\Sport\Variant\AllInOneGame;
use SportsHelpers\Sport\Variant\Single;
use SportsPlanning\Combinations\GamePlaceStrategy;
use SportsPlanning\Schedule\Repository as ScheduleRepository;
use Symfony\Component\Console\Input\InputInterface;

class Schedule extends Command
{
    protected ScheduleRepository $scheduleRepos;

    public function __construct(ContainerInterface $container)
    {
        /** @var ScheduleRepository $scheduleRepos */
        $scheduleRepos = $container->get(ScheduleRepository::class);
        $this->scheduleRepos = $scheduleRepos;

        /** @var Configuration $config */
        $config = $container->get(Configuration::class);
        parent::__construct($config);
    }

    protected function getNrOfPlaces(InputInterface $input): int
    {
        $nrOfPlaces = $input->getOption('nrOfPlaces');
        if (!is_string($nrOfPlaces) || strlen($nrOfPlaces) === 0) {
            throw new \Exception('incorrect nrOfPlaces "' . $nrOfPlaces . '"', E_ERROR);
        }
        $nrOfPlaces = (int)$nrOfPlaces;
        if ($nrOfPlaces === 0) {
            throw new \Exception('incorrect nrOfPlaces "' . $nrOfPlaces . '"', E_ERROR);
        }
        return $nrOfPlaces;
    }

    protected function getGamePlaceStrategy(InputInterface $input): GamePlaceStrategy
    {
        $gamePlaceStrategy = $input->getOption('gamePlaceStrategy');
        if (!is_string($gamePlaceStrategy) || strlen($gamePlaceStrategy) === 0) {
            return GamePlaceStrategy::EquallyAssigned;
        }
        if ($gamePlaceStrategy === GamePlaceStrategy::EquallyAssigned->name) {
            return GamePlaceStrategy::EquallyAssigned;
        }
        if ($gamePlaceStrategy === GamePlaceStrategy::RandomlyAssigned->name) {
            return GamePlaceStrategy::RandomlyAssigned;
        }
        throw new \Exception('incorrect gamePlaceStrategy "' . $gamePlaceStrategy . '"', E_ERROR);
    }

    /**
     * @param InputInterface $input
     * @return GameMode
     * @throws \Exception
     */
    protected function getGameMode(InputInterface $input): GameMode
    {
        $nrOfHomePlaces = $this->getIntParam($input, 'nrOfHomePlaces', 0);
        if ($nrOfHomePlaces > 0) {
            return GameMode::Against;
        }
        $nrOfGamePlaces = $this->getIntParam($input, 'nrOfGamePlaces', 0);
        if ($nrOfGamePlaces > 0) {
            return GameMode::Single;
        }
        return GameMode::AllInOneGame;
    }

    protected function getIntParam(InputInterface $input, string $param, int $default = null): int
    {
        $valueAsString = $input->getOption($param);
        if (!is_string($valueAsString) || strlen($valueAsString) === 0) {
            if ($default !== null) {
                return $default;
            }
            throw new \Exception('incorrect ' . $param . ' "' . $valueAsString . '"', E_ERROR);
        }
        return (int)$valueAsString;
    }

    protected function getSportVariant(
        InputInterface $input
    ): AgainstH2h|AgainstGpp|AllInOneGame|Single {
        $gameMode = $this->getGameMode($input);
        if ($gameMode === GameMode::Against) {
            $nrOfH2H = $this->getIntParam($input, 'nrOfH2H', 0);
            if( $nrOfH2H > 0 ) {
                return new AgainstH2h(
                    $this->getIntParam($input, 'nrOfHomePlaces'),
                    $this->getIntParam($input, 'nrOfAwayPlaces'),
                    $nrOfH2H
                );
            }
            return new AgainstGpp(
                $this->getIntParam($input, 'nrOfHomePlaces'),
                $this->getIntParam($input, 'nrOfAwayPlaces'),
                $this->getIntParam($input, 'nrOfGamesPerPlace')
            );
        }
        if ($gameMode === GameMode::AllInOneGame) {
            return new AllInOneGame($this->getIntParam($input, 'nrOfGamesPerPlace'));
        }
        return new Single(
            $this->getIntParam($input, 'nrOfGamePlaces'),
            $this->getIntParam($input, 'nrOfGamesPerPlace')
        );
    }
}
