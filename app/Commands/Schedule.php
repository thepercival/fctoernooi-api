<?php
declare(strict_types=1);

namespace App\Commands;

use App\Command;
use Psr\Container\ContainerInterface;
use Selective\Config\Configuration;
use SportsHelpers\GameMode;
use SportsHelpers\Sport\Variant\Against as AgainstSportVariant;
use SportsHelpers\Sport\Variant\AllInOneGame as AllInOneGameSportVariant;
use SportsHelpers\Sport\Variant\Single as SingleSportVariant;
use SportsPlanning\Combinations\GamePlaceStrategy;
use SportsPlanning\Schedule\Repository as ScheduleRepository;
use Symfony\Component\Console\Input\InputInterface;

class Schedule extends Command
{
    protected ScheduleRepository $scheduleRepos;

    public function __construct(ContainerInterface $container)
    {
        $this->scheduleRepos = $container->get(ScheduleRepository::class);
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

    protected function getGamePlaceStrategy(InputInterface $input): int
    {
        $gamePlaceStrategy = $input->getOption('gamePlaceStrategy');
        if (!is_string($gamePlaceStrategy) || strlen($gamePlaceStrategy) === 0) {
            return GamePlaceStrategy::EquallyAssigned;
        }
        $gamePlaceStrategy = (int)$gamePlaceStrategy;
        if ($gamePlaceStrategy !== GamePlaceStrategy::EquallyAssigned && $gamePlaceStrategy !== GamePlaceStrategy::RandomlyAssigned) {
            throw new \Exception('incorrect gamePlaceStrategy "' . $gamePlaceStrategy . '"', E_ERROR);
        }
        return $gamePlaceStrategy;
    }

    /**
     * @param InputInterface $input
     * @return int
     * @throws \Exception
     */
    protected function getGameMode(InputInterface $input): int
    {
        $nrOfHomePlaces = $this->getIntParam($input, 'nrOfHomePlaces', 0);
        if ($nrOfHomePlaces > 0) {
            return GameMode::AGAINST;
        }
        $nrOfGamePlaces = $this->getIntParam($input, 'nrOfGamePlaces', 0);
        if ($nrOfGamePlaces > 0) {
            return GameMode::SINGLE;
        }
        return GameMode::ALL_IN_ONE_GAME;
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

    protected function getSportVariant(InputInterface $input
    ): AgainstSportVariant|AllInOneGameSportVariant|SingleSportVariant {
        $gameMode = $this->getGameMode($input);
        if ($gameMode === GameMode::AGAINST) {
            $nrOfH2H = $this->getIntParam($input, 'nrOfH2H', 0);
            return new AgainstSportVariant(
                $this->getIntParam($input, 'nrOfHomePlaces'),
                $this->getIntParam($input, 'nrOfAwayPlaces'),
                $nrOfH2H,
                $this->getIntParam($input, 'nrOfGamesPerPlace', $nrOfH2H > 0 ? 0 : null)
            );
        }
        if ($gameMode === GameMode::ALL_IN_ONE_GAME) {
            return new AllInOneGameSportVariant($this->getIntParam($input, 'nrOfGamesPerPlace'));
        }
        return new SingleSportVariant(
            $this->getIntParam($input, 'nrOfGamePlaces'),
            $this->getIntParam($input, 'nrOfGamesPerPlace')
        );
    }
}
