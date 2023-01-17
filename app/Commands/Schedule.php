<?php

declare(strict_types=1);

namespace App\Commands;

use App\Command;
use Psr\Container\ContainerInterface;
use Selective\Config\Configuration;
use SportsHelpers\GameMode;
use SportsHelpers\Sport\Variant\Against\GamesPerPlace as AgainstGpp;
use SportsHelpers\Sport\Variant\Against\H2h as AgainstH2h;
use SportsHelpers\Sport\Variant\AllInOneGame;
use SportsHelpers\Sport\Variant\Single;
use SportsPlanning\Poule;
use SportsPlanning\Schedule as BaseSchedule;
use SportsPlanning\Combinations\AssignedCounter;
use SportsPlanning\Schedule\Repository as ScheduleRepository;
use SportsPlanning\Schedule\Sport as SportSchedule;
use SportsPlanning\SportVariant\WithPoule\Against\GamesPerPlace as AgainstGppWithPoule;
use Symfony\Component\Console\Input\InputInterface;

class Schedule extends Command
{
    protected ScheduleRepository $scheduleRepos;

    use InputHelper;

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
        $nrOfPlaces = $this->getIntInput($input, 'nrOfPlaces');
        if ($nrOfPlaces === 0) {
            throw new \Exception('incorrect nrOfPlaces "' . $nrOfPlaces . '"', E_ERROR);
        }
        return $nrOfPlaces;
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
            if ($nrOfH2H > 0) {
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

    protected function getMaxDifference(BaseSchedule $schedule): int
    {
        return max($this->getAgainstDifference($schedule), $this->getWithDifference($schedule) );
    }

    protected function getAgainstDifference(BaseSchedule $schedule): int
    {
        $poule = $schedule->getPoule();

        $sportVariants = array_values($schedule->createSportVariants()->toArray());

        $assignedCounter = new AssignedCounter($poule, $sportVariants);
        foreach( $schedule->getSportSchedules() as $sportSchedule) {
            $sportVariant = $sportSchedule->createVariant();
            if (!($sportVariant instanceof AgainstGpp)) {
                continue;
            }
            $homeAways = $sportSchedule->convertGamesToHomeAways();
            $assignedCounter->assignHomeAways($homeAways);
        }
        return $assignedCounter->getAgainstSportAmountDifference();
    }

    protected function getWithDifference(BaseSchedule $schedule): int
    {
        $poule = $schedule->getPoule();

        $sportVariants = array_values($schedule->createSportVariants()->toArray());

        $assignedCounter = new AssignedCounter($poule, $sportVariants);
        foreach( $schedule->getSportSchedules() as $sportSchedule) {
            $sportVariant = $sportSchedule->createVariant();
            if (!($sportVariant instanceof AgainstGpp) /*&& !($sportVariant instanceof Single)*/) {
                continue;
            }
            $homeAways = $sportSchedule->convertGamesToHomeAways();
            $assignedCounter->assignHomeAways($homeAways);
        }
        return $assignedCounter->getWithSportAmountDifference();
    }

    /**
     * @param BaseSchedule $schedule
     * @return list<AgainstGpp>
     */
    protected function getAgainstGppSportVariants(BaseSchedule $schedule): array
    {
        $sportVariants = array_values($schedule->createSportVariants()->toArray());
        return array_values(array_filter($sportVariants, function(AgainstGpp|AgainstH2h|Single|AllInOneGame $sportVariant): bool {
            return $sportVariant instanceof AgainstGpp;
        }));
    }


//    protected function getMinimalMargin(BaseSchedule $schedule): int
//    {
//        foreach( $schedule->getSportSchedules() as $sportSchedule) {
//            $sportVariant = $sportSchedule->createVariant();
//            if (!($sportVariant instanceof AgainstGpp)) {
//                continue;
//            }
//            $againstGppWithPoule = new AgainstGppWithPoule($sportSchedule->getSchedule()->getPoule(), $sportVariant);
//            if( !$againstGppWithPoule->allAgainstSameNrOfGamesAssignable() ) {
//                return 1;
//            }
//            if( !$againstGppWithPoule->allWithSameNrOfGamesAssignable()) {
//                return 1;
//            }
//        }
//        return 0;
//    }
}
