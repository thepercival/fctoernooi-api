<?php

declare(strict_types=1);

namespace App\Services;

use App\Mailer;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Exception;
use FCToernooi\Auth\SyncService as AuthSyncService;
use FCToernooi\Role;
use FCToernooi\Tournament\Invitation as TournamentInvitation;
use FCToernooi\TournamentUser;
use FCToernooi\User;
use Firebase\JWT\JWT;
use InvalidArgumentException;
use Selective\Config\Configuration;
use Slim\Views\Twig as TwigView;
use Tuupola\Base62;

/**
 * @api
 */
final class AuthService
{
    /** @var EntityRepository<User>  */
    protected EntityRepository $userRepos;
    /** @var EntityRepository<TournamentInvitation>  */
    protected EntityRepository $tournamentInvitationRepos;


    public function __construct(
        protected AuthSyncService $syncService,
        protected EntityManagerInterface $em,
        protected Configuration $config,
        protected Mailer $mailer,
        private TwigView $view,
        private EntityManagerInterface $entityManager
    ) {
        $metaData = $entityManager->getClassMetadata(User::class);
        $this->userRepos = new EntityRepository($entityManager, $metaData);
        $metaData = $entityManager->getClassMetadata(TournamentInvitation::class);
        $this->tournamentInvitationRepos = new EntityRepository($entityManager, $metaData);
    }

    /**
     * @param string $emailaddress
     * @param string $password
     * @param string|null $name
     * @return User|null
     * @throws Exception
     */
    public function register(string $emailaddress, string $password, string|null $name = null): ?User
    {
        if (strlen($password) < User::MIN_LENGTH_PASSWORD or strlen($password) > User::MAX_LENGTH_PASSWORD) {
            throw new InvalidArgumentException(
                'het wachtwoord moet minimaal ' . User::MIN_LENGTH_PASSWORD . ' karakters bevatten en mag maximaal ' . User::MAX_LENGTH_PASSWORD . ' karakters bevatten',
                E_ERROR
            );
        }
        $userTmp = $this->userRepos->findOneBy(['emailaddress' => $emailaddress]);
        if ($userTmp !== null) {
            throw new Exception('het emailadres is al in gebruik', E_ERROR);
        }
        if ($name !== null) {
            $userTmp = $this->userRepos->findOneBy(['name' => $name]);
            if ($userTmp !== null) {
                throw new Exception('de gebruikersnaam is al in gebruik', E_ERROR);
            }
        }

        $salt = bin2hex(random_bytes(15));
        $hashedPassword = password_hash($salt . $password, PASSWORD_DEFAULT);
        $user = new User($emailaddress, $salt, $hashedPassword);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        // @TODO CDK PAYMENT
        // $this->creditActionRepos->addCreateAccountCredits($user);

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
                    'tournamentName' => $tournamentUser->getTournament()->getName(),
                    'roleName' => $roleDefinition['name'],
                    'roleDescription' => $roleDefinition['description']
                ];
            }
        }
        return $roles;
    }

    public function resetPasswordCodeAndSend(string $emailAddress): bool
    {
        $user = $this->userRepos->findOneBy(['emailaddress' => $emailAddress]);
        if ($user === null) {
            throw new Exception('kan geen code versturen');
        }
        $conn = $this->em->getConnection();
        $conn->beginTransaction();
        try {
            $user->resetForgetpassword();

            $this->entityManager->persist($user);
            $this->entityManager->flush();

            $this->sendPasswordCodeMail($user);
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

        return JWT::encode(
            $payload,
            $this->config->getString("auth.jwtsecret"),
            $this->config->getString('auth.jwtalgorithm') );
    }

    protected function sendPasswordCodeMail(User $user): void
    {
        $forgetpasswordToken = $user->getForgetpasswordToken();
        $forgetpasswordDeadline = $user->getForgetpasswordDeadline();
        if ($forgetpasswordDeadline === null) {
            throw new Exception('je hebt je wachtwoord al gewijzigd, vraag opnieuw een nieuw wachtwoord aan');
        }
        $forgetpasswordDeadline = $forgetpasswordDeadline->modify('-1 days');

        $content = $this->view->fetch(
            'recoverpassword.twig',
            [
                'subject' => 'wachtwoord herstellen',
                // 'wwwUrl' => $this->config->getString('www.wwwurl'),
                'forgetpasswordToken' => $forgetpasswordToken,
                'forgetpasswordDeadline' => $this->getDateTimeAsLocalString($forgetpasswordDeadline)
            ]
        );

        $this->mailer->send('wachtwoord herstellen', $content, $user->getEmailaddress(), false);
    }

    public function changePassword(string $emailAddress, string $password, string $code): User
    {
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
        $this->entityManager->persist($user);
        $this->entityManager->flush();
        return $user;
    }

    public function sendValidationCodeMail(User $user, string $code, DateTimeImmutable $expireDateTime): void
    {
        $subject = 'emailadres valideren';
        $validateUrl = $this->config->getString('www.wwwurl') . 'user/validate/' . $code;

        $content = $this->view->fetch(
            'validate.twig',
            [
                'subject' => $subject,
                // 'wwwUrl' => $this->config->getString('www.wwwurl'),
                'expireDateTime' => $this->getDateTimeAsLocalString($expireDateTime),
                'validateUrl' => $validateUrl
            ]
        );

        $this->mailer->send($subject, $content, $user->getEmailaddress(), false);
    }

    protected function getDateTimeAsLocalString(\DateTimeImmutable $dateTimeImmutable): string
    {
        // Convert the time to the desired timezone (Amsterdam)
        $localDateTime = $dateTimeImmutable->setTimezone(new \DateTimeZone('Europe/Amsterdam'));

        // Create an IntlDateFormatter for Dutch (Netherlands)
        $formatter = new \IntlDateFormatter(
            'nl_NL',                     // locale
            \IntlDateFormatter::FULL,   // date style – we’ll build a custom pattern anyway
            \IntlDateFormatter::NONE,   // time style – handled separately
            'Europe/Amsterdam',          // explicit timezone (matches $localDateTime)
            \IntlDateFormatter::GREGORIAN,
            "EEEE d MMM yyyy HH:mm"     // pattern: full weekday, day, short month, year, 24‑h time
        );

        // Format the DateTimeImmutable instance
        $formatted = $formatter->format($localDateTime);

        // Ensure the whole string is lower‑cased (mb_* handles multibyte characters correctly)
        return $formatted === false ? 'unknown date' : mb_strtolower($formatted, 'UTF-8');
    }


    /**
     * @psalm-suppress UnusedParam
     */
    public function validate(User $user): void
    {
        // @TODO CDK PAYMENT
//        $user->setValidated(true);
//        $user->setValidateIn(0); // if earlier validated
//        $this->userRepos->save($user, true);
//
//
//        $this->creditActionRepos->addValidateCredits($user);
    }
}
