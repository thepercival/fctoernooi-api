<?php
/**
 * Created by PhpStorm.
 * User: coen
 * Date: 1-10-17
 * Time: 12:14
 */

namespace FCToernooi\Role;

use Doctrine\Common\Collections\ArrayCollection;
use FCToernooi\Role\Repository as RoleRepository;
use FCToernooi\Tournament;
use FCToernooi\Role;
use FCToernooi\User;

class Service
{
    /**
     * @var RoleRepository
     */
    protected $repos;

    /**
     * Service constructor.
     *
     * @param RoleRepository $repos
     */
    public function __construct( RoleRepository $repos )
    {
        $this->repos = $repos;
    }

    /**
     * @param Tournament $tournament
     * @param User $user
     * @param $roles
     * @return ArrayCollection
     * @throws \Exception
     */
    public function set( Tournament $tournament, User $user, $roleValues )
    {
        // get roles
        $rolesRet = new ArrayCollection();

        try {

            // flush roles
            $this->flushRoles( $tournament, $user );

            // save roles
            for($roleValue = 1 ; $roleValue < Role::ALL ; $roleValue *= 2 ){
                if ( ( $roleValue & $roleValues ) !== $roleValue ){
                    continue;
                }
                $role = new Role( $tournament, $user );
                $role->setValue( $roleValue );
                $this->repos->save($role);
                $rolesRet->add($role);
            }

        }
        catch( \Exception $e ){
            throw new \Exception(urlencode($e->getMessage()), E_ERROR );
        }

        return $rolesRet;
    }

    /**
     * @param Tournament $tournament
     * @param User $user
     */
    protected function flushRoles( Tournament $tournament, User $user )
    {
        $roles = $this->repos->findBy(
            array(
                'tournament' => $tournament,
                'user' => $user
            )
        );
        foreach( $roles as $role ){
            $this->repos->remove($role);
        }
    }
}