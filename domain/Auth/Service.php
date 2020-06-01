<?php
/**
 * Created by PhpStorm.
 * User: coen
 * Date: 28-1-17
 * Time: 21:20
 */

namespace FCToernooi\Auth;

use Exception;
use FCToernooi\Auth\SyncService as AuthSyncService;
use FCToernooi\Role;
use FCToernooi\Tournament;
use FCToernooi\TournamentUser;
use FCToernooi\User;
use FCToernooi\User\Repository as UserRepository;
use FCToernooi\TournamentUser\Repository as TournamentUserRepository;
use FCToernooi\Tournament\Invitation\Repository as TournamentInvitationRepository;
use FCToernooi\Tournament\Repository as TournamentRepository;
use Firebase\JWT\JWT;
use Selective\Config\Configuration;
use Tuupola\Base62;
use App\Mailer;

class Service
{
    /**
     * @var UserRepository
     */
    protected $userRepos;
    /**
     * @var TournamentUserRepository
     */
    protected $tournamentUserRepos;
    /**
     * @var TournamentRepository
     */
    protected $tournamentRepos;
    /**
     * @var TournamentInvitationRepository
     */
    protected $tournamentInvitationRepos;
    /**
     * @var AuthSyncService
     */
    protected $syncService;
    /**
     * @var Configuration
     */
    protected $config;
    /**
     * @var Mailer
     */
    protected $mailer;

    public function __construct(
        UserRepository $userRepos,
        TournamentUserRepository $tournamentUserRepos,
        TournamentRepository $tournamentRepos,
        TournamentInvitationRepository $tournamentInvitationRepos,
        AuthSyncService $syncService,
        Configuration $config,
        Mailer $mailer
    ) {
        $this->userRepos = $userRepos;
        $this->tournamentUserRepos = $tournamentUserRepos;
        $this->tournamentRepos = $tournamentRepos;
        $this->tournamentInvitationRepos = $tournamentInvitationRepos;
        $this->syncService = $syncService;
        $this->config = $config;
        $this->mailer = $mailer;
    }

    /**
     * @param string $emailaddress
     * @param string $password
     * @param string|null $name
     * @return User|null
     * @throws Exception
     */
    public function register(string $emailaddress, string $password, string $name = null): ?User
    {
        if (strlen($password) < User::MIN_LENGTH_PASSWORD or strlen($password) > User::MAX_LENGTH_PASSWORD) {
            throw new \InvalidArgumentException(
                "het wachtwoord moet minimaal " . User::MIN_LENGTH_PASSWORD . " karakters bevatten en mag maximaal " . User::MAX_LENGTH_PASSWORD . " karakters bevatten",
                E_ERROR
            );
        }
        $userTmp = $this->userRepos->findOneBy(array('emailaddress' => $emailaddress));
        if ($userTmp) {
            throw new Exception("het emailadres is al in gebruik", E_ERROR);
        }
        if ($name !== null) {
            $userTmp = $this->userRepos->findOneBy(array('name' => $name));
            if ($userTmp) {
                throw new Exception("de gebruikersnaam is al in gebruik", E_ERROR);
            }
        }

        $user = new User($emailaddress);
        $user->setSalt(bin2hex(random_bytes(15)));
        $user->setPassword(password_hash($user->getSalt() . $password, PASSWORD_DEFAULT));

        $savedUser = $this->userRepos->save($user);
        $invitations = $this->tournamentInvitationRepos->findBy(["emailaddress" => $user->getEmailaddress()]);
        $tournamentUsers = $this->syncService->processInvitations($savedUser, $invitations);
        $this->sendRegisterEmail($emailaddress, $tournamentUsers);

        return $savedUser;
    }

    /**
     * @param string $emailAddress
     * @param array|TournamentUser[] $tournamentUsers
     */
    protected function sendRegisterEmail(string $emailAddress, array $tournamentUsers)
    {
        $subject = 'welkom bij FCToernooi';
        $bodyBegin = <<<EOT
<p>Hallo,</p>
<p>Welkom bij FCToernooi! Wij wensen je veel plezier met het gebruik van de FCToernooi. Mocht je vragen hebben dan kun je <a href="https://drive.google.com/open?id=1HLwhbH4YXEbV7osGmFUt24gk_zxGjnVilTG0MpkkPUI">de handleiding</a> lezen, bellen of deze mail beantwoorden.</p>
EOT;
        $bodyMiddle = '';
        foreach ($tournamentUsers as $tournamentUser) {
            if ($tournamentUser->getRoles() === 0) {
                continue;
            }
            if (strlen($bodyMiddle) === 0) {
                $bodyMiddle = '<p>Je hebt voor de volgende toernooien rollen gekregen:</p>';
                $bodyMiddle .= '<table cellpadding="2" cellspacing="2" border="1"';
                $bodyMiddle .= "<thead><tr><th>toernooinaam</th><th>rolnaam</th><th>rolomschrijving</th></tr></thead>";
                $bodyMiddle .= "<tbody>";
            }
            $roleDefinitions = Role::getDefinitions($tournamentUser->getRoles());
            foreach ($roleDefinitions as $roleDefinition) {
                $bodyMiddle .= "<tr>";
                $bodyMiddle .= '<td>' . $tournamentUser->getTournament()->getCompetition()->getLeague()->getName(
                    ) . '</td>';
                $bodyMiddle .= '<td>' . $roleDefinition["name"] . '</td>';
                $bodyMiddle .= '<td>' . $roleDefinition["description"] . '</td>';
                $bodyMiddle .= "</tr>";
            }
        }
        if (strlen($bodyMiddle) > 0) {
            $bodyMiddle .= '</tbody></table><br/>';
        }
        $bodyEnd = '<p>met vriendelijke groet,<br/><br/>Coen Dunnink<br/>06-14363514<br/><a href="' . $this->config->getString(
                "www.wwwurl"
            ) . '">FCToernooi</a></p>';
        $this->mailer->send($subject, $bodyBegin . $bodyMiddle . $bodyEnd, $emailAddress);
    }

    public function sendPasswordCode($emailAddress)
    {
        $user = $this->userRepos->findOneBy(array('emailaddress' => $emailAddress));
        if (!$user) {
            throw new \Exception("kan geen code versturen");
        }
        $conn = $this->userRepos->getEM()->getConnection();
        $conn->beginTransaction();
        try {
            $user->resetForgetpassword();
            $this->userRepos->save($user);
            $this->mailPasswordCode($user);
            $conn->commit();
        } catch (\Exception $e) {
            $conn->rollback();
            throw $e;
        }


        return true;
    }

    public function createToken(User $user)
    {
        $jti = (new Base62)->encode(random_bytes(16));

        $now = new \DateTimeImmutable();
        $future = $now->modify("+3 months");
        // $future = $now->modify("+10 seconds");

        $payload = [
            "iat" => $now->getTimestamp(),
            "exp" => $future->getTimestamp(),
            "jti" => $jti,
            "sub" => $user->getId(),
        ];

        return JWT::encode($payload, $this->config->getString("auth.jwtsecret"));
    }

    protected function mailPasswordCode(User $user)
    {
        $subject = 'wachtwoord herstellen';
        $forgetpasswordToken = $user->getForgetpasswordToken();
        $forgetpasswordDeadline = $user->getForgetpasswordDeadline()->modify("-1 days")->format("Y-m-d");
        $body = <<<EOT
<p>Hallo,</p>
<p>            
Met deze code kun je je wachtwoord herstellen: $forgetpasswordToken 
</p>
<p>            
Let op : je kunt deze code gebruiken tot en met $forgetpasswordDeadline
</p>
<p>
met vriendelijke groet,
<br>
FCToernooi
</p>
EOT;
        $this->mailer->send($subject, $body, $user->getEmailaddress());
    }

    public function changePassword($emailAddress, $password, $code)
    {
        $user = $this->userRepos->findOneBy(array('emailaddress' => $emailAddress));
        if (!$user) {
            throw new \Exception("het wachtwoord kan niet gewijzigd worden");
        }
        // check code and deadline
        if ($user->getForgetpasswordToken() !== $code) {
            throw new \Exception("het wachtwoord kan niet gewijzigd worden, je hebt een onjuiste code gebruikt");
        }
        $now = new \DateTimeImmutable();
        if ($now > $user->getForgetpasswordDeadline()) {
            throw new \Exception("het wachtwoord kan niet gewijzigd worden, de wijzigingstermijn is voorbij");
        }

        // set password
        $user->setPassword(password_hash($user->getSalt() . $password, PASSWORD_DEFAULT));
        $user->setForgetpassword(null);
        return $this->userRepos->save($user);
    }
}
