<?php

declare(strict_types=1);

namespace App\Repositories\Sports;

use Doctrine\ORM\EntityRepository;
use Sports\Competition\CompetitionSport;
use Sports\Planning\GameAmountConfig;
use Sports\Round\Number as RoundNumber;

/**
 * @template-extends EntityRepository<GameAmountConfig>
 */
final class GameAmountConfigRepository extends EntityRepository
{
    public function addObjects(CompetitionSport $competitionSport, RoundNumber $roundNumber): void
    {
        $gameAmountConfig = $roundNumber->getGameAmountConfig($competitionSport);
        if ($gameAmountConfig === null) {
            return;
        }
        $this->getEntityManager()->persist($gameAmountConfig);
        $nextRoundNumber = $roundNumber->getNext();
        if ($nextRoundNumber !== null) {
            $this->addObjects($competitionSport, $nextRoundNumber);
        }
    }

    public function removeObjects(CompetitionSport $competitionSport): void
    {
        $gameAmountConfigs = $this->findBy(["competitionSport" => $competitionSport ]);
        foreach ($gameAmountConfigs as $config) {
            $this->getEntityManager()->remove($config);
        }
    }
}
