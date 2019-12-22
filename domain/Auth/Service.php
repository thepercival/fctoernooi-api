<?php
/**
 * Created by PhpStorm.
 * User: coen
 * Date: 28-1-17
 * Time: 21:20
 */

namespace FCToernooi\Auth;

use FCToernooi\Tournament;
use FCToernooi\User;
use FCToernooi\User\Repository as UserRepository;
use FCToernooi\Role\Repository as RoleRepository;
use FCToernooi\Role;
use FCToernooi\Tournament\Repository as TournamentRepository;
use Firebase\JWT\JWT;
use Tuupola\Base62;
use Psr\Http\Message\ServerRequestInterface as Request;

class Service
{
	/**
	 * @var UserRepository
	 */
	protected $userRepos;
    /**
     * @var RoleRepository
     */
    protected $roleRepos;
    /**
     * @var TournamentRepository
     */
    protected $tournamentRepos;
    /**
     * @var Settings
     */
    protected $settings;

    /**
     * Service constructor.
     * @param UserRepository $userRepos
     * @param RoleRepository $roleRepos
     * @param TournamentRepository $tournamentRepos
     * @param Settings $settings
     */
	public function __construct(
	    UserRepository $userRepos,
        RoleRepository $roleRepos,
        TournamentRepository $tournamentRepos,
        Settings $settings )
	{
		$this->userRepos = $userRepos;
        $this->roleRepos = $roleRepos;
        $this->tournamentRepos = $tournamentRepos;
        $this->settings = $settings;
	}

    public function checkAuth( Request $request, Tournament $tournament = null ): User
    {
        $user = $this->getUser($request);
        if ( $user === null ){
            throw new \Exception("de ingelogde gebruikers kon niet gevonden worden", E_ERROR );
        }
        if( $tournament !== null && !$tournament->hasRole( $user, Role::ADMIN ) ) {
            throw new \Exception( "je hebt geen rechten om het toernooi aan te passen" );
        }
        return $user;
    }

    public function getUser( Request $request ): ?User {
        $token = $request->getAttribute('token');
        if ( $token === null || !$token->isPopulated() ) {
            return null;
        }
        return $user = $this->userRepos->find($token->getUserId());
    }

    /**
     * @param $emailaddress
     * @param $password
     * @param null $name
     * @return |null
     * @throws \Doctrine\DBAL\ConnectionException
     */
	public function register( $emailaddress, $password, $name = null )
	{
        if ( strlen( $password ) < User::MIN_LENGTH_PASSWORD or strlen( $password ) > User::MAX_LENGTH_PASSWORD ){
            throw new \InvalidArgumentException( "het wachtwoord moet minimaal ".User::MIN_LENGTH_PASSWORD." karakters bevatten en mag maximaal ".User::MAX_LENGTH_PASSWORD." karakters bevatten", E_ERROR );
        }
		$userTmp = $this->userRepos->findOneBy( array('emailaddress' => $emailaddress ) );
		if ( $userTmp ) {
			throw new \Exception("het emailadres is al in gebruik",E_ERROR);
		}
		if ( $name !== null ) {
            $userTmp = $this->userRepos->findOneBy( array('name' => $name ) );
            if ( $userTmp ) {
                throw new \Exception("de gebruikersnaam is al in gebruik",E_ERROR);
            }
        }

        $user = new User($emailaddress);
        $user->setSalt( bin2hex(random_bytes(15) ) );
        $user->setPassword( password_hash( $user->getSalt() . $password, PASSWORD_DEFAULT) );

        $conn = $this->userRepos->getEM()->getConnection();
        $conn->beginTransaction();
        $savedUser = null;
        try {
            $savedUser = $this->userRepos->save($user);
            $roles = $this->syncRefereeRolesForUser( $user );
            $this->sendRegisterEmail( $emailaddress, $roles );
            $conn->commit();
        } catch (\Exception $e) {
            $conn->rollback();
            throw $e;
        }
		return $savedUser;
	}

    public function syncRefereeRolesForUser( User $user ): array
    {
        $rolesRet = [];
        $em = $this->roleRepos->getEM();

        // remove referee roles
        {
            $params = ['value' => Role::REFEREE, 'user' => $user ];
            $refereeRoles = $this->roleRepos->findBy( $params );
            foreach( $refereeRoles as $refereeRole ) {
                $em->remove( $refereeRole );
            }
        }
        $em->flush();

        // add referee roles
        $tournaments = $this->tournamentRepos->findByEmailaddress( $user->getEmailaddress() );
        foreach( $tournaments as $tournament ) {
            $refereeRole = new Role( $tournament, $user);
            $refereeRole->setValue(Role::REFEREE);
            $em->persist( $refereeRole );
            $rolesRet[] = $refereeRole;
        }
        $em->flush();
        return $rolesRet;
    }

	protected function sendRegisterEmail( $emailAddress, array $roles )
    {
        $subject = 'welkom bij FCToernooi';
        $body = '
        <p>Hallo,</p>
        <p>            
        Welkom bij FCToernooi! Wij wensen je veel plezier met het gebruik van de FCToernooi.
        </p>';
        if( count( $roles ) > 0 ) {
            $body .= '<p>Je staat geregistreerd als scheidsrechter voor de volgende toernooien:<ul>';
            foreach( $roles as $role ) {
                $body .= '<li><a href="https://www.fctoernooi.nl/toernooi/view/'.$role->getTournament()->getId().'">'.$role->getTournament()->getCompetition()->getLeague()->getName().'</a></li>';
            }
            $body .= '</ul></p>';
        }
        $body .= '<p>
        Mocht je vragen/opmerkingen/klachten/suggesties/etc hebben ga dan naar <a href="https://github.com/thepercival/fctoernooi/issues">https://github.com/thepercival/fctoernooi/issues</a>
        </p>        
        <p>
        met vriendelijke groet,
        <br>
        FCToernooi
        </p>';

        $from = "FCToernooi";
        $fromEmail = "noreply@fctoernooi.nl";
        $headers  = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8" . "\r\n";
        $headers .= "From: ".$from." <" . $fromEmail . ">" . "\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion();
        $params = "-r ".$fromEmail;

        if ( !mail( $emailAddress, $subject, $body, $headers, $params) ) {
            // $app->flash("error", "We're having trouble with our mail servers at the moment.  Please try again later, or contact us directly by phone.");
            error_log('Mailer Error!' );
            // $app->halt(500);
        }
    }

    public function sendPasswordCode( $emailAddress ) {
        $user = $this->userRepos->findOneBy( array( 'emailaddress' => $emailAddress ) );
        if (!$user) {
            throw new \Exception( "kan geen code versturen");
        }
        $user->resetForgetpassword();
        $this->userRepos->save( $user );
        $this->mailPasswordCode( $user );
        return true;
    }

    public function getToken( User $user)
    {
        $jti = (new Base62)->encode(random_bytes(16));

        $now = new \DateTime();
        $future = new \DateTime("now +3 months");

        $payload = [
            "iat" => $now->getTimeStamp(),
            "exp" => $future->getTimeStamp(),
            "jti" => $jti,
            "sub" => $user->getId(),
        ];

        return JWT::encode($payload, $this->settings->getJwtSecret() );
    }

    protected function mailPasswordCode( User $user )
    {
        $subject = 'wachtwoord herstellen';
        $body = '
        <p>Hallo,</p>
        <p>            
        Met deze code kun je je wachtwoord herstellen: ' . $user->getForgetpasswordToken() . '.
        </p>
        <p>            
        Let op : je kunt deze code gebruiken tot en met ' . $user->getForgetpasswordDeadline()->modify("-1 days")->format("Y-m-d") . '.
        </p>
        <p>
        met vriendelijke groet,
        <br>
        FCToernooi
        </p>';

        $from = "FCToernooi";
        $fromEmail = "info@fctoernooi.nl";
        $headers  = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8" . "\r\n";
        $headers .= "From: ".$from." <" . $fromEmail . ">" . "\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion();
        $params = "-r ".$fromEmail;

        if ( !mail( $user->getEmailaddress(), $subject, $body, $headers, $params) ) {
            // $app->flash("error", "We're having trouble with our mail servers at the moment.  Please try again later, or contact us directly by phone.");
            error_log('Mailer Error!' );
            // $app->halt(500);
        }
    }

    public function changePassword( $emailAddress, $password, $code )
    {
        $user = $this->userRepos->findOneBy( array( 'emailaddress' => $emailAddress ) );
        if (!$user) {
            throw new \Exception( "het wachtwoord kan niet gewijzigd worden");
        }
        // check code and deadline
        if ($user->getForgetpasswordToken() !== $code ) {
            throw new \Exception( "het wachtwoord kan niet gewijzigd worden, je hebt een onjuiste code gebruikt");
        }
        $now = new \DateTimeImmutable();
        if ( $now > $user->getForgetpasswordDeadline() ) {
            throw new \Exception( "het wachtwoord kan niet gewijzigd worden, de wijzigingstermijn is voorbij");
        }

        // set password
        $user->setPassword( password_hash( $user->getSalt() . $password, PASSWORD_DEFAULT) );
        $user->setForgetpassword( null );
        return $this->userRepos->save($user);
    }
}