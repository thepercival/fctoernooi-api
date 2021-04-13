<?php
declare(strict_types=1);

namespace FCToernooi\Tournament\Invitation;

use SportsHelpers\Repository\SaveRemove as SaveRemoveRepository;
use SportsHelpers\Repository as BaseRepository;
use Doctrine\ORM\EntityRepository;
use FCToernooi\Tournament\Invitation as InvitationBase;

/**
 * @template-extends EntityRepository<InvitationBase>
 * @template-implements SaveRemoveRepository<InvitationBase>
 */
class Repository extends EntityRepository implements SaveRemoveRepository
{
    use BaseRepository;
}
