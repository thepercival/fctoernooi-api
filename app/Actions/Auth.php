<?php
/**
 * Created by PhpStorm.
 * User: coen
 * Date: 30-1-17
 * Time: 12:48
 */

namespace App\Actions;

use App\Exceptions\DomainRecordNotFoundException;
use App\Response\ErrorResponse;
use JMS\Serializer\SerializerInterface;
use FCToernooi\User;
use \Firebase\JWT\JWT;
use FCToernooi\User\Repository as UserRepository;
use FCToernooi\Auth\Service as AuthService;
use \Slim\Middleware\JwtAuthentication;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Tuupola\Base62;

final class Auth extends Action
{
    /**
     * @var AuthService
     */
	private $authService;
    /**
     * @var UserRepository
     */
    private $userRepository;
    /**
     * @var SerializerInterface
     */
    protected $serializer;

	public function __construct(AuthService $authService, UserRepository $userRepository, SerializerInterface $serializer )
	{
        $this->authService = $authService;
        $this->userRepository = $userRepository;
		$this->serializer = $serializer;
	}

    public function validateToken( Request $request, Response $response, $args ): Response
    {
        return $response->withStatus(200);
    }

	public function register( Request $request, Response $response, $args): Response
	{
		try{
            /** @var \stdClass $registerData */
            $registerData = $this->getFormData($request);
            if (property_exists($registerData, "emailaddress") === false) {
                throw new \Exception("geen emailadres ingevoerd");
            }
            if (property_exists($registerData, "password") === false) {
                throw new \Exception("geen wachtwoord ingevoerd");
            }
            $emailAddress = $registerData->emailaddress;
            $password = $registerData->password;

			$user = $this->authService->register( $emailAddress, $password );
			if ($user === null or !($user instanceof User)) {
				throw new \Exception( "de nieuwe gebruiker kan niet worden geretourneerd");
            }

            $data = [
                "token" => $this->authService->getToken( $user),
                "user" => [
                    "id" => $user->getId(),
                    "emailaddress" => $user->getEmailaddress()
                ]
            ];

            $json = $this->serializer->serialize( $data, 'json' );
            return $this->respondWithJson($response, $json);
		}
		catch( \Exception $e ) {
            return new ErrorResponse($e->getMessage(), 422);
        }
	}

    public function login( Request $request, Response $response, $args): Response
	{
       try{
           /** @var \stdClass $authData */
           $authData = $this->getFormData( $request );
           if( !property_exists( $authData, "emailaddress") || strlen($authData->emailaddress) === 0 ) {
               throw new \Exception( "het emailadres is niet opgegeven");
           }
           $emailaddress = filter_var($authData->emailaddress, FILTER_VALIDATE_EMAIL);
           if( $emailaddress === false ) {
               throw new \Exception( "het emailadres \"".$authData->emailaddress."\" is onjuist");
           }
           if( !property_exists( $authData, "password") || strlen($authData->password) === 0 ) {
               throw new \Exception( "het wachtwoord is niet opgegeven");
           }

           $user = $this->userRepository->findOneBy(
               array( 'emailaddress' => $emailaddress )
           );

           if (!$user or !password_verify( $user->getSalt() . $authData->password, $user->getPassword() ) ) {
               throw new \Exception( "ongeldige emailadres en wachtwoord combinatie");
           }

           /*if ( !$user->getActive() ) {
		    throw new \Exception( "activeer eerst je account met behulp van de link in je ontvangen email", E_ERROR );
		    }*/

           $data = [
               "token" => $this->authService->getToken( $user ),
               "userid" => $user->getId()
           ];

           return $this->respondWithJson( $response, $this->serializer->serialize( $data, 'json') );
		}
		catch( \Exception $e ){
            return new ErrorResponse($e->getMessage(), 422);
		}
	}

    public function passwordreset( Request $request, Response $response, $args ): Response
    {
        try{
            /** @var \stdClass $paswordResetData */
            $paswordResetData = $this->getFormData( $request );
            if( property_exists($paswordResetData, "emailaddress" ) === false ) {
                throw new \Exception( "geen emailadres ingevoerd");
            }
            $emailAddress = $paswordResetData->emailaddress;

            $retVal = $this->authService->sendPasswordCode( $emailAddress );

            $data = [ "retval" => $retVal ];
            $json = $this->serializer->serialize( $data, 'json' );
            return $this->respondWithJson($response, $json);
        }
        catch( \Exception $e ){
            return new ErrorResponse($e->getMessage(), 422);
        }
    }

    public function passwordchange( Request $request, Response $response, $args ): Response
    {
        try{
            /** @var \stdClass $paswordChangeData */
            $paswordChangeData = $this->getFormData( $request );
            if( property_exists($paswordChangeData, "emailaddress" ) === false ) {
                throw new \Exception( "geen emailadres ingevoerd");
            }
            if( property_exists($paswordChangeData, "password" ) === false ) {
                throw new \Exception( "geen wachtwoord ingevoerd");
            }
            if( property_exists($paswordChangeData, "code" ) === false ) {
                throw new \Exception( "geen code ingevoerd");
            }
            $emailAddress = $paswordChangeData->emailaddress;
            $password = $paswordChangeData->password;
            $code = (string) $paswordChangeData->code;

            $user = $this->authService->changePassword( $emailAddress, $password, $code );

            $data = [
                "token" => $this->authService->getToken( $user),
                "userid" => $user->getId()
            ];

            $json = $this->serializer->serialize( $data, 'json' );
            return $this->respondWithJson($response, $json);
        }
        catch( \Exception $e ){
            return new ErrorResponse($e->getMessage(), 422);
        }
    }

	/*
		public function edit( Request $request, Response $response, $args ): Response
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

		public function remove( Request $request, Response $response, $args ): Response
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