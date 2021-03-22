<?php

declare(strict_types=1);

namespace FCToernooi\Tournament\Invitation;

use Doctrine\ORM\EntityRepository;
use FCToernooi\Tournament\Invitation as InvitationBase;

/**
 * @template-extends EntityRepository<InvitationBase>
 */
class Repository extends EntityRepository
{
    use \Sports\Repository;
}
