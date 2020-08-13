<?php
/**
 * Created by PhpStorm.
 * User: coen
 * Date: 1-10-17
 * Time: 12:19
 */

namespace FCToernooi\User;

use FCToernooi\User;
use FCToernooi\Tournament;

/**
 * Class Repository
 * @package FCToernooi\User
 */
class Repository extends \Sports\Repository
{
    public function find($id, $lockMode = null, $lockVersion = null): ?User
    {
        return $this->_em->find($this->_entityName, $id, $lockMode, $lockVersion);
    }
}
