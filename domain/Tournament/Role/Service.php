<?php
/**
 * Created by PhpStorm.
 * User: coen
 * Date: 1-10-17
 * Time: 12:14
 */

namespace FCToernooi\Tournament\Role;

use Doctrine\Common\Collections\ArrayCollection;
use FCToernooi\Tournament\Role\Repository as TournamentRoleRepository;
use FCToernooi\Tournament;
use FCToernooi\Tournament\Role;
use FCToernooi\User as User;


class Service
{
    /**
     * @var TournamentRoleRepository
     */
    protected $repos;

    /**
     * Service constructor.
     *
     * @param TournamentRoleRepository $repos
     */
    public function __construct( TournamentRoleRepository $repos )
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
    public function set( Tournament $tournament, User $user, $roles )
    {
        // get roles
        $rolesRet = new ArrayCollection();

        try {

            // flush roles
            $this->flushRoles( $tournament, $user );

            // save roles
            for($role = 1 ; $role < Role::ALL ; $role *= 2 ){
                if ( ( $role & $roles ) !== $role ){
                    continue;
                }
                $tournamentRole = new Role( $tournament, $user );
                $tournamentRole->setRole( $role );
                $this->repos->save($tournamentRole);
                $rolesRet->add($tournamentRole);
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