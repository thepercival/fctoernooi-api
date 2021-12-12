<?php

declare(strict_types=1);

namespace FCToernooi\Tournament\Invitation;

use Doctrine\ORM\EntityRepository;
use FCToernooi\Tournament\Invitation as InvitationBase;
use SportsHelpers\Repository as BaseRepository;

/**
 * @template-extends EntityRepository<InvitationBase>
 */
class Repository extends EntityRepository
{
    /**
     * @use BaseRepository<InvitationBase>
     */
    use BaseRepository;
}
