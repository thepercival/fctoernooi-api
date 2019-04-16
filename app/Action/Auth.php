<?php
/**
 * Created by PhpStorm.
 * User: coen
 * Date: 30-1-17
 * Time: 12:48
 */

namespace App\Action;

use JMS\Serializer\Serializer;
use FCToernooi\User;
use \Firebase\JWT\JWT;
use FCToernooi\User\Repository as UserRepository;
use FCToernooi\Auth\Service as AuthService;
use \Slim\Middleware\JwtAuthentication;
use Tuupola\Base62;

final class Auth
{
    /**
     * @var AuthService
     */
	private $service;
    /**
     * @var UserRepository
     */
    private $userRepository;
    /**
     * @var Serializer
     */
    protected $serializer;
    /**
     * @var array
     */
	protected $settings;

	public function __construct(AuthService $service, UserRepository $userRepository, Serializer $serializer, $settings )
	{
        $this->service = $service;
        $this->userRepository = $userRepository;
		$this->serializer = $serializer;
		$this->settings = $settings;
	}

    public function validateToken( $request, $response, $args)
    {
        return $response->withStatus(200);
    }

	public function register( $request, $response, $args)
	{
		$sErrorMessage = null;
		try{
		    $arrRegisterData = $request->getParsedBody();
            if( array_key_exists("emailaddress", $arrRegisterData ) === false ) {
                throw new \Exception( "geen emailadres ingevoerd");
            }
            if( array_key_exists("password", $arrRegisterData ) === false ) {
                throw new \Exception( "geen wachtwoord ingevoerd");
            }
            $emailAddress = $arrRegisterData["emailaddress"];
            $password = $arrRegisterData["password"];

			$user = $this->service->register( $emailAddress, $password );
			if ($user === null or !($user instanceof User)) {
				throw new \Exception( "de nieuwe gebruiker kan niet worden geretourneerd");
            }

            $data = [
                "token" => $this->getToken( $user),
                "user" => [
                    "id" => $user->getId(),
                    "emailaddress" => $user->getEmailaddress()
                ]
            ];

            return $response
                ->withStatus(201)
                ->withHeader('Content-Type', 'application/json;charset=utf-8')
                ->write($this->serializer->serialize( $data, 'json'));
            ;
		}
		catch( \Exception $e ){
			$sErrorMessage = $e->getMessage();
		}
        return $response->withStatus(400)->write( $sErrorMessage );
	}

	public function login($request, $response, $args)
	{
//	    var_dump($request->getParams());
//	    var_dump($args);
//	    die();
        $emailaddress = $request->getParam('emailaddress');
		$password = $request->getParam('password');

		$sErrorMessage = null;
		try{
			$user = $this->userRepository->findOneBy(
				array( 'emailaddress' => $emailaddress )
			);

            if (!$user or !password_verify( $user->getSalt() . $password, $user->getPassword() ) ) {
				throw new \Exception( "ongeldige emailadres en wachtwoord combinatie");
			}

			/*if ( !$user->getActive() ) {
				throw new \Exception( "activeer eerst je account met behulp van de link in je ontvangen email", E_ERROR );
			}*/


            $data = [
                "token" => $this->getToken( $user),
                "userid" => $user->getId()
            ];

			return $response
				->withStatus(201)
				->withHeader('Content-Type', 'application/json;charset=utf-8')
				->write($this->serializer->serialize( $data, 'json'));
			;
		}
		catch( \Exception $e ){
			$sErrorMessage = $e->getMessage();
		}
		return $response->withStatus(400)->write( $sErrorMessage );
	}

    public function passwordreset($request, $response, $args)
    {
        $sErrorMessage = null;
        try{
            $arrRegisterData = $request->getParsedBody();
            if( array_key_exists("emailaddress", $arrRegisterData ) === false ) {
                throw new \Exception( "geen emailadres ingevoerd");
            }
            $emailAddress = $arrRegisterData["emailaddress"];

            $retVal = $this->service->sendPasswordCode( $emailAddress );

            $data = [ "retval" => $retVal ];
            return $response
                ->withStatus(201)
                ->withHeader('Content-Type', 'application/json;charset=utf-8')
                ->write($this->serializer->serialize( $data, 'json'));
            ;
        }
        catch( \Exception $e ){
            $sErrorMessage = $e->getMessage();
        }
        return $response->withStatus(400)->write( $sErrorMessage );
    }

    public function passwordchange($request, $response, $args)
    {
        $sErrorMessage = null;
        try{
            $arrRegisterData = $request->getParsedBody();
            if( array_key_exists("emailaddress", $arrRegisterData ) === false ) {
                throw new \Exception( "geen emailadres ingevoerd");
            }
            if( array_key_exists("password", $arrRegisterData ) === false ) {
                throw new \Exception( "geen wachtwoord ingevoerd");
            }
            if( array_key_exists("code", $arrRegisterData ) === false ) {
                throw new \Exception( "geen code ingevoerd");
            }
            $emailAddress = $arrRegisterData["emailaddress"];
            $password = $arrRegisterData["password"];
            $code = (string) $arrRegisterData["code"];

            $user = $this->service->changePassword( $emailAddress, $password, $code );

            $data = [
                "token" => $this->getToken( $user),
                "userid" => $user->getId()
            ];

            return $response
                ->withStatus(201)
                ->withHeader('Content-Type', 'application/json;charset=utf-8')
                ->write($this->serializer->serialize( $data, 'json'));
            ;
        }
        catch( \Exception $e ){
            $sErrorMessage = $e->getMessage();
        }
        return $response->withStatus(400)->write( $sErrorMessage );
    }

	protected function getToken( User $user)
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

        return JWT::encode($payload, $this->settings['auth']['jwtsecret'] );
    }

	/*
		public function edit( $request, $response, $args)
		{
			$sErrorMessage = null;
			try{
				$user = $this->userResource->put( $args['id'], array(
						"name"=> $request->getParam('name'),
						"email" => $request->getParam('email') )
				);
				if (!$user)
					throw new \Exception( "de gewijzigde gebruiker kan niet worden geretouneerd");

				return $response->withJSON($user);
			}
			catch( \Exception $e ){
				$sErrorMessage = $e->getMessage();
			}
			return $response->withStatus(404)->write(rawurlencode( $sErrorMessage ) );
		}

		public function remove( $request, $response, $args)
		{
			$sErrorMessage = null;
			try{
				$user = $this->userResource->delete( $args['id'] );
				return $response;
			}
			catch( \Exception $e ){
				$sErrorMessage = $e->getMessage();
			}
			return $response->withStatus(404)->write('de gebruiker is niet verwijdered : ' . $sErrorMessage );
		}

		protected function sentEmailActivation( $user )
		{
			$activatehash = hash ( "sha256", $user["email"] . $this->settings["auth"]["activationsecret"] );
			// echo $activatehash;

			$sMessage =
				"<div style=\"font-size:20px;\">FC Toernooi</div>"."<br>".
				"<br>".
				"Hallo ".$user["name"].","."<br>"."<br>".
				"Bedankt voor het registreren bij FC Toernooi.<br>"."<br>".
				'Klik op <a href="'.$this->settings["www"]["url"].'activate?activationkey='.$activatehash.'&email='.rawurlencode( $user["email"] ).'">deze link</a> om je emailadres te bevestigen en je account te activeren.<br>'."<br>".
				'Wensen, klachten of vragen kunt u met de <a href="https://github.com/thepercival/fctoernooi/issues">deze link</a> bewerkstellingen.<br>'."<br>".
				"Veel plezier met het gebruiken van FC Toernooi<br>"."<br>".
				"groeten van FC Toernooi"
			;

			$mail = new \PHPMailer;
			$mail->isSMTP();
			$mail->Host = $this->settings["email"]["smtpserver"];
			$mail->setFrom( $this->settings["email"]["from"], $this->settings["email"]["fromname"] );
			$mail->addAddress( $user["email"] );
			$mail->addReplyTo( $this->settings["email"]["from"], $this->settings["email"]["fromname"] );
			$mail->isHTML(true);
			$mail->Subject = "FC Toernooi registratiegegevens";
			$mail->Body    = $sMessage;
			if(!$mail->send()) {
				throw new \Exception("de activatie email kan niet worden verzonden");
			}
		}

		protected function forgetEmailForgetPassword()
		{

		}*/
}