<?php
/**
 * Created by PhpStorm.
 * User: coen
 * Date: 1-10-17
 * Time: 21:41
 */

namespace FCToernooi\Auth;

use DateTimeImmutable;
use FCToernooi\Role;
use FCToernooi\TournamentUser;
use FCToernooi\User;
use FCToernooi\User\Repository as UserRepository;
use FCToernooi\Tournament;
use FCToernooi\TournamentUser\Repository as TournamentUserRepository;
use FCToernooi\Tournament\Invitation\Repository as TournamentInvitationRepository;
use FCToernooi\Tournament\Invitation as TournamentInvitation;

class SyncService
{
    /**
     * @var UserRepository
     */
    private $userRepos;
    /**
     * @var TournamentUserRepository
     */
    private $tournamentUserRepos;
    /**
     * @var TournamentInvitationRepository
     */
    private $tournamentInvitationRepos;

    public function __construct(
        UserRepository $userRepos,
        TournamentUserRepository $tournamentUserRepos,
        TournamentInvitationRepository $tournamentInvitationRepos
    ) {
        $this->userRepos = $userRepos;
        $this->tournamentUserRepos = $tournamentUserRepos;
        $this->tournamentInvitationRepos = $tournamentInvitationRepos;
    }

    public function add(Tournament $tournament, int $roles, string $emailaddress = null)
    {
        if (strlen($emailaddress) === 0) {
            return;
        }
        /** @var User|null $user */
        $user = $this->userRepos->findOneBy(["emailaddress" => $emailaddress]);

        if ($user !== null) {
            $tournamentUser = $tournament->getUser($user);
            if ($tournamentUser !== null) {
                $tournamentUser->setRoles($tournamentUser->getRoles() & $roles);
            } else {
                $tournamentUser = new TournamentUser($tournament, $user, $roles);
            }
            $this->tournamentUserRepos->save($tournamentUser);
            return $tournamentUser;
        }

        $invitation = $this->tournamentInvitationRepos->findOneBy(
            ["tournament" => $tournament, "emailaddress" => $emailaddress]
        );
        if ($invitation !== null) {
            $invitation->setRoles($invitation->getRoles() & $roles);
        } else {
            $invitation = new TournamentInvitation($tournament, $emailaddress, $roles);
            $invitation->setCreatedDateTime(new DateTimeImmutable());
        }
        return $this->tournamentInvitationRepos->save($invitation);
    }

    public function remove(Tournament $tournament, int $roles, string $emailaddress = null)
    {
        if (strlen($emailaddress) === 0) {
            return;
        }
        /** @var User|null $user */
        $user = $this->userRepos->findOneBy(["emailaddress" => $emailaddress]);

        if ($user !== null) {
            $tournamentUser = $tournament->getUser($user);
            if ($tournamentUser === null) {
                return;
            }
            if ($tournamentUser->getRoles() === Role::REFEREE) {
                $tournament->getUsers()->removeElement($tournamentUser);
                $this->tournamentUserRepos->remove($tournamentUser);
            } else {
                if ($tournamentUser->getRoles() > Role::REFEREE) {
                    $tournamentUser->setRoles($tournamentUser->getRoles() - Role::REFEREE);
                    $this->tournamentUserRepos->save($tournamentUser);
                }
            }
            return;
        }

        $invitation = $this->tournamentInvitationRepos->findOneBy(
            ["tournament" => $tournament, "emailaddress" => $emailaddress]
        );
        if ($invitation === null) {
            return;
        }
        if ($invitation->getRoles() === Role::REFEREE) {
            $this->tournamentInvitationRepos->remove($invitation);
        } else {
            if ($invitation->getRoles() > Role::REFEREE) {
                $invitation->setRoles($invitation->getRoles() - Role::REFEREE);
                $this->tournamentInvitationRepos->save($invitation);
            }
        }
    }
}
