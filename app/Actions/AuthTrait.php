<?php
/**
 * Created by PhpStorm.
 * User: coen
 * Date: 22-5-18
 * Time: 13:02
 */

namespace App\Actions;

use FCToernooi\Token;
use FCToernooi\User\Repository as UserRepository;
use FCToernooi\Tournament;
use FCToernooi\Role;
use FCToernooi\User;

trait AuthTrait
{
    /**
     * @param Token $token
     * @param UserRepository $userRepos
     * @param Tournament|null $tournament
     * @return User
     * @throws \Exception
     */
    public function checkAuth( Token $token, UserRepository $userRepos, Tournament $tournament = null ): User
    {
        $user = null;
        if( $token->isPopulated() ){
            $user = $userRepos->find( $this->token->getUserId() );
        }
        if ( $user === null ){
            throw new \Exception("de ingelogde gebruikers kon niet gevonden worden", E_ERROR );
        }
        if( $tournament !== null && !$tournament->hasRole( $user, Role::ADMIN ) ) {
            throw new \Exception( "je hebt geen rechten om het toernooi aan te passen" );
        }
        return $user;
    }
}


