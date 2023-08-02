<?php

declare(strict_types=1);

namespace FCToernooi\Tournament\RegistrationSettings;

use Doctrine\ORM\EntityRepository;
use FCToernooi\Tournament;
use FCToernooi\Tournament\RegistrationSettings;
use SportsHelpers\Repository as BaseRepository;
use FCToernooi\Tournament\RegistrationSettings as TournamentRegistrationSettings;

/**
 * @template-extends EntityRepository<TournamentRegistrationSettings>
 */
class Repository extends EntityRepository
{
    /**
     * @use BaseRepository<TournamentRegistrationSettings>
     */
    use BaseRepository;

    public function saveDefault(Tournament $tournament): RegistrationSettings {
        $endDateTime = $tournament->getCompetition()->getStartDateTime()->modify('-1 days');
        $settings = new RegistrationSettings(
            $tournament,
            false,
            $endDateTime,
            false,
            ''
        );
        $this->save($settings, true);
        return $settings;
    }
}
