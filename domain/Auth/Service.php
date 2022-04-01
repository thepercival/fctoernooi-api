<?php

declare(strict_types=1);

namespace FCToernooi\Auth;

use App\Mailer;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use FCToernooi\Auth\SyncService as AuthSyncService;
use FCToernooi\CreditAction\Repository as CreditActionRepository;
use FCToernooi\Role;
use FCToernooi\Tournament\Invitation\Repository as TournamentInvitationRepository;
use FCToernooi\Tournament\Repository as TournamentRepository;
use FCToernooi\TournamentUser;
use FCToernooi\TournamentUser\Repository as TournamentUserRepository;
use FCToernooi\User;
use FCToernooi\User\Repository as UserRepository;
use Firebase\JWT\JWT;
use InvalidArgumentException;
use Selective\Config\Configuration;
use Slim\Views\Twig as TwigView;
use Tuupola\Base62;

class Service
{
    public function __construct(
        protected UserRepository $userRepos,
        protected CreditActionRepository $creditActionRepos,
        protected TournamentUserRepository $tournamentUserRepos,
        protected TournamentRepository $tournamentRepos,
        protected TournamentInvitationRepository $tournamentInvitationRepos,
        protected AuthSyncService $syncService,
        protected EntityManagerInterface $em,
        protected Configuration $config,
        protected Mailer $mailer,
        private TwigView $view
    ) {
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
            throw new InvalidArgumentException(
                'het wachtwoord moet minimaal ' . User::MIN_LENGTH_PASSWORD . ' karakters bevatten en mag maximaal ' . User::MAX_LENGTH_PASSWORD . ' karakters bevatten',
                E_ERROR
            );
        }
        /** @var User|null $userTmp */
        $userTmp = $this->userRepos->findOneBy(['emailaddress' => $emailaddress]);
        if ($userTmp !== null) {
            throw new Exception('het emailadres is al in gebruik', E_ERROR);
        }
        if ($name !== null) {
            /** @var User|null $userTmp */
            $userTmp = $this->userRepos->findOneBy(['name' => $name]);
            if ($userTmp !== null) {
                throw new Exception('de gebruikersnaam is al in gebruik', E_ERROR);
            }
        }

        $salt = bin2hex(random_bytes(15));
        $hashedPassword = password_hash($salt . $password, PASSWORD_DEFAULT);
        $user = new User($emailaddress, $salt, $hashedPassword);
        $this->userRepos->save($user, true);

        $this->creditActionRepos->addCreateAccountCredits($user);

        $invitations = $this->tournamentInvitationRepos->findBy(['emailaddress' => $user->getEmailaddress()]);
        $tournamentUsers = $this->syncService->processInvitations($user, $invitations);
        $this->sendRegisterEmail($emailaddress, $tournamentUsers);

        return $user;
    }

    /**
     * @param string $emailAddress
     * @param list<TournamentUser> $tournamentUsers
     */
    protected function sendRegisterEmail(string $emailAddress, array $tournamentUsers): void
    {
        $content = $this->view->fetch(
            'register.twig',
            [
                'subject' => 'welkom',
                'wwwUrl' => $this->config->getString('www.wwwurl'),
                'tournamentRoles' => $this->getTournamentRoles($tournamentUsers)
            ]
        );

        $this->mailer->send('welkom', $content, $emailAddress, false);
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

    public function sendPasswordCode(string $emailAddress): bool
    {
        $user = $this->userRepos->findOneBy(['emailaddress' => $emailAddress]);
        if ($user === null) {
            throw new Exception('kan geen code versturen');
        }
        $conn = $this->em->getConnection();
        $conn->beginTransaction();
        try {
            $user->resetForgetpassword();
            $this->userRepos->save($user);
            $this->mailPasswordCode($user);
            $conn->commit();
        } catch (Exception $exception) {
            $conn->rollBack();
            throw $exception;
        }
        return true;
    }

    public function createToken(User $user): string
    {
        $jti = (new Base62())->encode(random_bytes(16));

        $now = new DateTimeImmutable();
        $future = $now->modify('+3 months');
        // $future = $now->modify("+10 seconds");

        $payload = [
            'iat' => $now->getTimestamp(),
            'exp' => $future->getTimestamp(),
            'jti' => $jti,
            'sub' => $user->getId(),
        ];

        return JWT::encode($payload, $this->config->getString('auth.jwtsecret'));
    }

    protected function mailPasswordCode(User $user): void
    {
        $forgetpasswordToken = $user->getForgetpasswordToken();
        $forgetpasswordDeadline = $user->getForgetpasswordDeadline();
        if ($forgetpasswordDeadline === null) {
            throw new Exception('je hebt je wachtwoord al gewijzigd, vraag opnieuw een nieuw wachtwoord aan');
        }
        $forgetpasswordDeadline = $forgetpasswordDeadline->modify('-1 days')->format('Y-m-d');

        $content = $this->view->fetch(
            'recoverpassword.twig',
            [
                'subject' => 'wachtwoord herstellen',
                // 'wwwUrl' => $this->config->getString('www.wwwurl'),
                'forgetpasswordToken' => $forgetpasswordToken,
                'forgetpasswordDeadline' => $forgetpasswordDeadline
            ]
        );

        $this->mailer->send('wachtwoord herstellen', $content, $user->getEmailaddress(), false);
    }

    public function changePassword(string $emailAddress, string $password, string $code): User
    {
        /** @var User|null $user */
        $user = $this->userRepos->findOneBy(['emailaddress' => $emailAddress]);
        if ($user === null) {
            throw new Exception('het wachtwoord kan niet gewijzigd worden');
        }
        // check code and deadline
        if ($user->getForgetpasswordToken() !== $code) {
            throw new Exception('het wachtwoord kan niet gewijzigd worden, je hebt een onjuiste code gebruikt');
        }
        $now = new DateTimeImmutable();
        if ($now > $user->getForgetpasswordDeadline()) {
            throw new Exception('het wachtwoord kan niet gewijzigd worden, de wijzigingstermijn is voorbij');
        }
        $passwordHash = password_hash($user->getSalt() . $password, PASSWORD_DEFAULT);
        $user->setPassword($passwordHash);
        $user->setForgetpassword(null);
        $this->userRepos->save($user);
        return $user;
    }

    public function mailValidationCode(User $user, string $code, DateTimeImmutable $expireDateTime): void
    {
        $subject = 'emailadres valideren';
        $url = $this->config->getString('www.wwwurl') . 'user/validate/' . $code;

        setlocale(LC_ALL, 'nl_NL.UTF-8'); //
        $localDateTime = $expireDateTime->setTimezone(new DateTimeZone('Europe/Amsterdam'));
        $date = strtolower(
            strftime('%A %d %b %Y', $localDateTime->getTimestamp()) . ' ' .
            $localDateTime->format('H:i')
        );
        $body = <<<EOT
<p>Hallo,</p>
<p>            
Tot $date uur kun je met de volgende link je emailadres valideren : <a style="font-size: 125%" href="$url">$url</a> 
</p>
<p>
met vriendelijke groet,
<br>
FCToernooi
</p>
EOT;
        $this->mailer->send($subject, $body, $user->getEmailaddress());
    }

    public function validate(User $user): void
    {
        $user->setValidated(true);
        $user->setValidateIn(0); // if earlier validated
        $this->userRepos->save($user, true);

        $this->creditActionRepos->addValidateCredits($user);
    }
}
