<?php

declare(strict_types=1);

namespace App\Repositories;

use Doctrine\ORM\EntityRepository;
use FCToernooi\Tournament;
use FCToernooi\Tournament\RegistrationSettings;
use FCToernooi\Tournament\RegistrationSettings as TournamentRegistrationSettings;

/**
 * @api
 * @template-extends EntityRepository<TournamentRegistrationSettings>
 */
final class TournamentRegistrationSettingsRepository extends EntityRepository
{
    public function saveDefault(Tournament $tournament): RegistrationSettings {
        $endDateTime = $tournament->getCompetition()->getStartDateTime()->modify('-1 days');
        $settings = new RegistrationSettings(
            $tournament,
            false,
            $endDateTime,
            false,
            ''
        );
        $this->getEntityManager()->persist($settings);
        $this->getEntityManager()->flush();

        return $settings;
    }
}
