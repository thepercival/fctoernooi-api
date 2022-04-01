<?php

declare(strict_types=1);

namespace FCToernooi\Payment;

use Doctrine\ORM\EntityRepository;
use FCToernooi\Payment as PaymentBase;
use SportsHelpers\Repository as BaseRepository;

/**
 * @template-extends EntityRepository<PaymentBase>
 */
class Repository extends EntityRepository
{
    /**
     * @use BaseRepository<PaymentBase>
     */
    use BaseRepository;
}
