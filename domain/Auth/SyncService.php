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

    public function __construct(
        UserRepository $userRepos,
        TournamentUserRepository $tournamentUserRepos,
        TournamentInvitationRepository $tournamentInvitationRepos,
        Mailer $mailer
    ) {
        $this->userRepos = $userRepos;
        $this->tournamentUserRepos = $tournamentUserRepos;
        $this->tournamentInvitationRepos = $tournamentInvitationRepos;
        $this->mailer = $mailer;
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
            $newUser = $tournamentUser === null;
            if ($newUser) {
                $tournamentUser = new TournamentUser($tournament, $user, $roles);
            } else {
                $tournamentUser->setRoles($tournamentUser->getRoles() & $roles);
            }
            $this->tournamentUserRepos->save($tournamentUser);
            if ($newUser) {
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
            $invitation->setRoles($invitation->getRoles() & $roles);
        }
        $this->tournamentInvitationRepos->save($invitation);
        if ($newInvitation) {
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

    protected function sendEmailToNewUser($emailAddress, array $roles)
    {
        $subject = 'welkom bij FCToernooi';
        $bodyBegin = <<<EOT
<p>Hallo,</p>
<p>Welkom bij FCToernooi! Wij wensen je veel plezier met het gebruik van de FCToernooi.</p>
EOT;

        $bodyMiddle = '';
        if (count($roles) > 0) {
            $bodyMiddle = '<p>Je staat geregistreerd als scheidsrechter voor de volgende toernooien:<ul>';
//            foreach ($roles as $role) {
//                $bodyMiddle .= '<li><a href="' . $this->config->getString("www.wwwurl") . $role->getTournament()->getId(
//                    ) . '">' . $role->getTournament()->getCompetition()->getLeague()->getName() . '</a></li>';
//            }
            $bodyMiddle .= '</ul></p>';
        }
        $bodyEnd = <<<EOT
<p>
Mocht je vragen/opmerkingen/klachten/suggesties/etc hebben ga dan naar <a href="https://github.com/thepercival/fctoernooi/issues">https://github.com/thepercival/fctoernooi/issues</a>
</p>        
<p>met vriendelijke groet,<br/>FCToernooi</p>
EOT;
        $this->mailer->send($subject, $bodyBegin . $bodyMiddle . $bodyEnd, $emailAddress);
    }

    protected function sendEmailTournamentInvitation(TournamentInvitation $invitation)
    {
        $subject = 'welkom bij FCToernooi';
        $bodyBegin = <<<EOT
<p>Hallo,</p>
<p>Welkom bij FCToernooi! Wij wensen je veel plezier met het gebruik van de FCToernooi.</p>
EOT;

        $bodyMiddle = '';
        if (count($roles) > 0) {
            $bodyMiddle = '<p>Je staat geregistreerd als scheidsrechter voor de volgende toernooien:<ul>';
//            foreach ($roles as $role) {
//                $bodyMiddle .= '<li><a href="' . $this->config->getString("www.wwwurl") . $role->getTournament()->getId(
//                    ) . '">' . $role->getTournament()->getCompetition()->getLeague()->getName() . '</a></li>';
//            }
            $bodyMiddle .= '</ul></p>';
        }
        $bodyEnd = <<<EOT
<p>
Mocht je vragen/opmerkingen/klachten/suggesties/etc hebben ga dan naar <a href="https://github.com/thepercival/fctoernooi/issues">https://github.com/thepercival/fctoernooi/issues</a>
</p>        
<p>met vriendelijke groet,<br/>FCToernooi</p>
EOT;
        $this->mailer->send($subject, $bodyBegin . $bodyMiddle . $bodyEnd, $emailAddress);
    }
}
