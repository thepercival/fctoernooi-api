<?php
/**
 * Created by PhpStorm.
 * User: coen
 * Date: 1-10-17
 * Time: 21:41
 */

namespace FCToernooi\Auth;

use App\Mailer;
use DateTimeImmutable;
use FCToernooi\Role;
use FCToernooi\TournamentUser;
use FCToernooi\User;
use FCToernooi\User\Repository as UserRepository;
use FCToernooi\Tournament;
use FCToernooi\TournamentUser\Repository as TournamentUserRepository;
use FCToernooi\Tournament\Invitation\Repository as TournamentInvitationRepository;
use FCToernooi\Tournament\Invitation as TournamentInvitation;
use Selective\Config\Configuration;

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
    /**
     * @var Mailer
     */
    protected $mailer;
    /**
     * @var Configuration
     */
    protected $config;

    public function __construct(
        UserRepository $userRepos,
        TournamentUserRepository $tournamentUserRepos,
        TournamentInvitationRepository $tournamentInvitationRepos,
        Mailer $mailer,
        Configuration $config
    ) {
        $this->userRepos = $userRepos;
        $this->tournamentUserRepos = $tournamentUserRepos;
        $this->tournamentInvitationRepos = $tournamentInvitationRepos;
        $this->mailer = $mailer;
        $this->config = $config;
    }

    public function add(Tournament $tournament, int $roles, string $emailaddress = null, bool $sendMail = false)
    {
        if (strlen($emailaddress) === 0) {
            return;
        }
        /** @var User|null $user */
        $user = $this->userRepos->findOneBy(["emailaddress" => $emailaddress]);

        if ($user !== null) {
            $tournamentUser = $tournament->getUser($user);
            $newUser = $tournamentUser === null;
            if ($newUser) {
                $tournamentUser = new TournamentUser($tournament, $user, $roles);
            } else {
                $tournamentUser->setRoles($tournamentUser->getRoles() | $roles);
            }
            $this->tournamentUserRepos->save($tournamentUser);
            if ($sendMail && $newUser) {
                $this->sendEmailTournamentUser($tournamentUser);
            }
            return $tournamentUser;
        }

        $invitation = $this->tournamentInvitationRepos->findOneBy(
            ["tournament" => $tournament, "emailaddress" => $emailaddress]
        );
        $newInvitation = $invitation === null;
        if ($newInvitation) {
            $invitation = new TournamentInvitation($tournament, $emailaddress, $roles);
            $invitation->setCreatedDateTime(new DateTimeImmutable());
        } else {
            $invitation->setRoles($invitation->getRoles() | $roles);
        }
        $this->tournamentInvitationRepos->save($invitation);
        if ($sendMail && $newInvitation) {
            $this->sendEmailTournamentInvitation($invitation);
        }
        return $invitation;
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
            $rolesToRemove = $tournamentUser->getRoles() & $roles;
            if ($tournamentUser->getRoles() === $rolesToRemove) {
                $tournament->getUsers()->removeElement($tournamentUser);
                $this->tournamentUserRepos->remove($tournamentUser);
            } else {
                $tournamentUser->setRoles($tournamentUser->getRoles() - $rolesToRemove);
                $this->tournamentUserRepos->save($tournamentUser);
            }
            return;
        }

        $invitation = $this->tournamentInvitationRepos->findOneBy(
            ["tournament" => $tournament, "emailaddress" => $emailaddress]
        );
        if ($invitation === null) {
            return;
        }
        $rolesToRemove = $invitation->getRoles() & $roles;
        if ($invitation->getRoles() === $rolesToRemove) {
            $this->tournamentInvitationRepos->remove($invitation);
        } else {
            $invitation->setRoles($invitation->getRoles() - $rolesToRemove);
            $this->tournamentInvitationRepos->save($invitation);
        }
    }

    /**
     * @param User $user
     * @param array | TournamentInvitation[] $invitations
     * @return array | TournamentUser[]
     */
    public function processInvitations(User $user, array $invitations): array
    {
        $tournamentUsers = [];
        while (count($invitations) > 0) {
            $invitation = array_shift($invitations);
            $this->tournamentInvitationRepos->remove($invitation);
            $tournamentUsers[] = $this->tournamentUserRepos->save(
                new TournamentUser(
                    $invitation->getTournament(), $user, $invitation->getRoles()
                )
            );
        }
        return $tournamentUsers;
    }

    /**
     * @param User $user
     * @return array|TournamentInvitation[]
     */
    public function revertTournamentUsers(User $user): array
    {
        $invitations = [];
        $tournamentUsers = $this->tournamentUserRepos->findBy(["user" => $user]);
        while (count($tournamentUsers) > 0) {
            $tournamentUser = array_shift($tournamentUsers);
            $tournamentUser->getTournament()->getUsers()->removeElement($tournamentUser);
            $this->tournamentUserRepos->remove($tournamentUser);
            $invitation = new TournamentInvitation(
                $tournamentUser->getTournament(),
                $tournamentUser->getUser()->getEmailaddress(),
                $tournamentUser->getRoles()
            );
            $invitation->setCreatedDateTime(new DateTimeImmutable());
            $invitations[] = $this->tournamentInvitationRepos->save($invitation);
        }
        return $invitations;
    }

    protected function sendEmailTournamentUser(TournamentUser $tournamentUser)
    {
        $url = $this->config->getString('www.wwwurl');
        $tournamentName = $tournamentUser->getTournament()->getCompetition()->getLeague()->getName();
        $suffix = "<p>Wanneer je <a href=\"" . $url . "user/login\">inlogt</a> op " . $url . " staat toernooi \"" . $tournamentName . "\"  bij je toernooien. </a></p>";
        $this->sendEmailForAuthorization(
            $tournamentUser->getUser()->getEmailaddress(),
            $tournamentName,
            $tournamentUser->getRoles(),
            $suffix
        );
    }

    protected function sendEmailTournamentInvitation(TournamentInvitation $invitation)
    {
        $url = $this->config->getString('www.wwwurl');
        $tournamentName = $invitation->getTournament()->getCompetition()->getLeague()->getName();
        $suffix = "<p>Wanneer je je <a href=\"" . $url . "user/register\">registreert</a> op " . $url . " staat toernooi \"" . $tournamentName . "\"  bij je toernooien. </a></p>";
        $this->sendEmailForAuthorization(
            $invitation->getEmailaddress(),
            $tournamentName,
            $invitation->getRoles(),
            $suffix
        );
    }

    protected function sendEmailForAuthorization(
        string $emailadress,
        string $tournamentName,
        int $roles,
        string $suffix
    ) {
        $subject = 'uitnodiging voor toernooi "' . $tournamentName . '"';
        $url = $this->config->getString('www.wwwurl');

        $body = "<p>Hallo,</p>" .
            "<p>Je bent uitgenodigd op " . $url . " voor toernooi \"" . $tournamentName . "\" door de beheerder van dit toernooi.<br/>" .
            "Je hebt de volgende rollen gekregen:</p>" .
            $this->getRoleDefinitions($roles) .
            $suffix .
            "<p>met vriendelijke groet,<br/>FCToernooi</p>";
        $this->mailer->send($subject, $body, $emailadress);
    }

    protected function getRoleDefinitions(int $roles): string
    {
        $retVal = "<table>";
        foreach (Role::getDefinitions($roles) as $definition) {
            $retVal .= "<tr><td>" . $definition["name"] . "</td><td>" . $definition["description"] . "</td></tr>";
        }
        return $retVal . "</table>";
    }
}
