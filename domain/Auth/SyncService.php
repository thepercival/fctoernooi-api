<?php

declare(strict_types=1);

namespace FCToernooi\Auth;

use App\Mailer;
use DateTimeImmutable;
use FCToernooi\CacheService;
use FCToernooi\Role;
use FCToernooi\Tournament;
use FCToernooi\Tournament\Invitation as TournamentInvitation;
use FCToernooi\Tournament\Invitation\Repository as TournamentInvitationRepository;
use FCToernooi\TournamentUser;
use FCToernooi\TournamentUser\Repository as TournamentUserRepository;
use FCToernooi\User;
use FCToernooi\User\Repository as UserRepository;
use Selective\Config\Configuration;
use Slim\Views\Twig as TwigView;

class SyncService
{
    public function __construct(
        private UserRepository $userRepos,
        private TournamentUserRepository $tournamentUserRepos,
        private TournamentInvitationRepository $tournamentInvitationRepos,
        private Mailer $mailer,
        private TwigView $view,
        private CacheService $cacheService,
        private Configuration $config
    ) {
    }

    public function add(
        Tournament $tournament,
        int $roles,
        string $emailaddress = null,
        bool $sendMail = false
    ): TournamentUser|TournamentInvitation|null {
        if ($emailaddress === null) {
            return null;
        }
        $user = $this->userRepos->findOneBy(['emailaddress' => $emailaddress]);

        if ($user !== null) {
            $tournamentUser = $tournament->getUser($user);
            $newUser = false;
            if ($tournamentUser === null) {
                $tournamentUser = new TournamentUser($tournament, $user, $roles);
                $newUser = true;
            } else {
                $tournamentUser->setRoles($tournamentUser->getRoles() | $roles);
            }
            $this->tournamentUserRepos->save($tournamentUser);
            $this->cacheService->resetTournament((int)$tournament->getId());

            if ($sendMail && $newUser) {
                $this->sendTournamentUserEmail($tournamentUser);
            }
            return $tournamentUser;
        }

        $invitation = $this->tournamentInvitationRepos->findOneBy(
            ['tournament' => $tournament, 'emailaddress' => $emailaddress]
        );
        $newInvitation = false;

        if ($invitation === null) {
            $invitation = new TournamentInvitation($tournament, $emailaddress, $roles);
            $invitation->setCreatedDateTime(new DateTimeImmutable());
            $newInvitation = true;
        } else {
            $invitation->setRoles($invitation->getRoles() | $roles);
        }
        $this->tournamentInvitationRepos->save($invitation);
        if ($sendMail && $newInvitation) {
            $this->sendTournamentInvitationEmail($invitation);
        }
        return $invitation;
    }

    public function remove(Tournament $tournament, int $roles, string $emailaddress = null): void
    {
        if ($emailaddress === null) {
            return;
        }
        $user = $this->userRepos->findOneBy(['emailaddress' => $emailaddress]);

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
            $this->cacheService->resetTournament((int)$tournament->getId());
            return;
        }

        $invitation = $this->tournamentInvitationRepos->findOneBy(
            ['tournament' => $tournament, 'emailaddress' => $emailaddress]
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
     * @param list<TournamentInvitation> $invitations
     * @return list<TournamentUser>
     */
    public function processInvitations(User $user, array $invitations): array
    {
        $tournamentUsers = [];
        while (count($invitations) > 0) {
            $invitation = array_shift($invitations);
            $this->tournamentInvitationRepos->remove($invitation);
            $tournamentUser = new TournamentUser(
                $invitation->getTournament(),
                $user,
                $invitation->getRoles()
            );
            $this->tournamentUserRepos->save($tournamentUser);
            $tournamentUsers[] = $tournamentUser;
        }
        return $tournamentUsers;
    }

    /**
     * @param User $user
     * @return list<TournamentInvitation>
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
            $this->tournamentInvitationRepos->save($invitation);
            $invitations[] = $invitation;
        }
        return $invitations;
    }

    protected function sendTournamentUserEmail(TournamentUser $tournamentUser): void
    {
        $tournamentName = $tournamentUser->getTournament()->getCompetition()->getLeague()->getName();

        $subject = 'uitnodiging voor toernooi "' . $tournamentName . '"';
        $url = $this->config->getString('www.wwwurl');

        $content = $this->view->fetch(
            'tournamentuser.twig',
            [
                'subject' => $subject,
                // 'wwwUrl' => $this->config->getString('www.wwwurl'),
                'url' => $url,
                'tournamentName' => $tournamentName,
                'tournamentRoles' => $this->getTournamentRoles([$tournamentUser])
            ]
        );

        $this->mailer->send($subject, $content, $tournamentUser->getUser()->getEmailaddress(), false);
    }

    /**
     * @param list<TournamentUser> $tournamentUsers
     * @return list<array<string, string>>
     */
    protected function getTournamentRoles(array $tournamentUsers): array
    {
        $roles = [];
        $filteredTournamentUsers = array_filter($tournamentUsers, function (TournamentUser $tournamentUser): bool {
            return $tournamentUser->getRoles() > 0;
        });
        foreach ($filteredTournamentUsers as $tournamentUser) {
            $roleDefinitions = Role::getDefinitions($tournamentUser->getRoles());
            foreach ($roleDefinitions as $roleDefinition) {
                $roles[] = [
                    'tournamentName' => $tournamentUser->getTournament()->getCompetition()->getLeague()->getName(),
                    'roleName' => $roleDefinition['name'],
                    'roleDescription' => $roleDefinition['description']
                ];
            }
        }
        return $roles;
    }

    protected function sendTournamentInvitationEmail(TournamentInvitation $invitation): void
    {
        $tournamentName = $invitation->getTournament()->getCompetition()->getLeague()->getName();

        $subject = 'uitnodiging voor toernooi "' . $tournamentName . '"';
        $url = $this->config->getString('www.wwwurl');

        $content = $this->view->fetch(
            'tournamentinvitation.twig',
            [
                'subject' => $subject,
                // 'wwwUrl' => $this->config->getString('www.wwwurl'),
                'url' => $url,
                'tournamentName' => $tournamentName,
                'tournamentRoles' => $this->getTournamentRolesByInvitation($invitation)
            ]
        );

        $this->mailer->send($subject, $content, $invitation->getEmailaddress(), false);
    }

    /**
     * @param TournamentInvitation $invitation
     * @return list<array<string, string>>
     */
    protected function getTournamentRolesByInvitation(TournamentInvitation $invitation): array
    {
        $roles = [];
        $roleDefinitions = Role::getDefinitions($invitation->getRoles());
        foreach ($roleDefinitions as $roleDefinition) {
            $roles[] = [
                'tournamentName' => $invitation->getTournament()->getCompetition()->getLeague()->getName(),
                'roleName' => $roleDefinition['name'],
                'roleDescription' => $roleDefinition['description']
            ];
        }
        return $roles;
    }

}
