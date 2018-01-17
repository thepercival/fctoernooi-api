<?php
/**
 * Created by PhpStorm.
 * User: coen
 * Date: 28-1-17
 * Time: 21:20
 */

namespace FCToernooi\Auth;

use FCToernooi\User;
use FCToernooi\User\Repository as UserRepository;

class Service
{
	/**
	 * @var UserRepository
	 */
	protected $repos;

	/**
	 * Service constructor.
	 *
	 * @param UserRepository $userRepository
	 */
	public function __construct( UserRepository $userRepository )
	{
		$this->repos = $userRepository;
	}

	/**
	 * @param string $emailaddress
	 * @param string $password
	 * @param string $name
	 *
	 * @throws \Exception
	 */
	public function register( $emailaddress, $password, $name = null )
	{
        if ( strlen( $password ) < User::MIN_LENGTH_PASSWORD or strlen( $password ) > User::MAX_LENGTH_PASSWORD ){
            throw new \InvalidArgumentException( "het wachtwoord moet minimaal ".User::MIN_LENGTH_PASSWORD." karakters bevatten en mag maximaal ".User::MAX_LENGTH_PASSWORD." karakters bevatten", E_ERROR );
        }
		if ( $emailaddress !== "coendunnink@gmail.com" ){
			throw new \Exception("alleen het emailadres coendunnink@gmail.com kan geregistreerd worden op het moment, expirimentele fase");
		}
		$userTmp = $this->repos->findOneBy( array('emailaddress' => $emailaddress ) );
		if ( $userTmp ) {
			throw new \Exception("het emailadres is al in gebruik",E_ERROR);
		}
		if ( $name !== null ) {
            $userTmp = $this->repos->findOneBy( array('name' => $name ) );
            if ( $userTmp ) {
                throw new \Exception("de gebruikersnaam is al in gebruik",E_ERROR);
            }
        }
        $user = new User($emailaddress, password_hash( $password, PASSWORD_DEFAULT) );
		$savedUser = $this->repos->save($user);
		// $this->sendRegisterEmail( $emailaddress );
		return $savedUser;
	}

	protected function sendRegisterEmail( $emailAddress )
    {
        $subject = 'welkom bij FCToernooi';
        $body = '
        <p>Hallo,</p>
        <p>            
        Welkom bij FCToernooi! Wij wensen je veel plezier met het gebruik van de FCToernooi.
        </p>
        <p>
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
        $headers .= "From: ".$from." <".$fromEmail.">" . "\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion();
        $params = "-r ".$from;

        //if ( !mail( $emailAddress, $subject, $body, $headers, $params) ) {
            // $app->flash("error", "We're having trouble with our mail servers at the moment.  Please try again later, or contact us directly by phone.");
          //  error_log('Mailer Error: emailerror' );
            // $app->halt(500);
        // }
    }


}