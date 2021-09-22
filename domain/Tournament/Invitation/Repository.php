<?php
declare(strict_types=1);

namespace FCToernooi\Tournament\Invitation;

use SportsHelpers\Repository as BaseRepository;
use Doctrine\ORM\EntityRepository;
use FCToernooi\Tournament\Invitation as InvitationBase;

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
