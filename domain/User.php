<?php
/**
 * Created by PhpStorm.
 * User: coen
 * Date: 28-1-17
 * Time: 20:44
 */

namespace FCToernooi;

class User
{
	/**
	 * @var int
	 */
	private $id;

	/**
	 * @var string
	 */
	private $name;

	/**
	 * @var string
	 */
	private $password;

	/**
	 * @var string
	 */
	private $emailaddress;

    const MIN_LENGTH_NAME = 3;
    const MAX_LENGTH_NAME = 15;
    const MIN_LENGTH_EMAIL = 6;
    const MAX_LENGTH_EMAIL = 100;
    const MIN_LENGTH_PASSWORD = 3;
    const MAX_LENGTH_PASSWORD = 50;
    const MAX_LENGTH_HASH = 255;

	public function __construct( $name, $password, $emailaddress )
	{
		$this->setName( $name );
		$this->setPassword( $password );
		$this->setEmailaddress( $emailaddress );
	}

	/**
	 * Get id
	 *
	 * @return int
	 */
	public function getId()
	{
		return $this->id;
	}

    /**
     * @param $id
     */
    public function setId( $id )
    {
        $this->id = $id;
    }

	/**
	 * @return User\Name
	 */
	public function getName()
	{
		return $this->name;
	}

	/**
	 * @param User\Name $name
	 */
	public function setName( $name )
	{
        if ( strlen( $name ) < static::MIN_LENGTH_NAME or strlen( $name ) > static::MAX_LENGTH_NAME ){
            throw new \InvalidArgumentException( "de naam moet minimaal ".static::MIN_LENGTH_NAME." karakters bevatten en mag maximaal ".static::MAX_LENGTH_NAME." karakters bevatten", E_ERROR );
        }

        if( !ctype_alnum($name)){
            throw new \InvalidArgumentException( "de naam mag alleen cijfers en letters bevatten", E_ERROR );
        }
		$this->name = $name;
	}

	/**
	 * @return string
	 */
	public function getPassword()
	{
		return $this->password;
	}

	/**
	 * @param Ustring
	 */
	public function setPassword( $password )
	{
        if ( strlen( $password ) < static::MIN_LENGTH_PASSWORD or strlen( $password ) > static::MAX_LENGTH_PASSWORD ){
            throw new \InvalidArgumentException( "het wachtwoord moet minimaal ".static::MIN_LENGTH_PASSWORD." karakters bevatten en mag maximaal ".static::MAX_LENGTH_PASSWORD." karakters bevatten", E_ERROR );
        }
		$this->password = $password;
	}

	/**
	 * @return User\Emailaddress
	 */
	public function getEmailaddress()
	{
		return $this->emailaddress;
	}

	/**
	 * @param User\Emailaddress $emailaddress
	 */
	public function setEmailaddress( $emailaddress )
	{
        if ( strlen( $emailaddress ) < static::MIN_LENGTH_EMAIL or strlen( $emailaddress ) > static::MAX_LENGTH_EMAIL ){
            throw new \InvalidArgumentException( "het emailadres moet minimaal ".static::MIN_LENGTH_EMAIL." karakters bevatten en mag maximaal ".static::MAX_LENGTH_EMAIL." karakters bevatten", E_ERROR );
        }

        if (!filter_var($emailaddress, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException( "het emailadres ".$emailaddress." is niet valide", E_ERROR );
        }
	}

    /**
     * @return TournamentRole[] | ArrayCollection
     */
//    public function getTournamentRoles()
//    {
//        return $this->tournamentRoles;
//    }

    /**
     *
     */
//    public function getTournaments()
//    {
//        $tournaments = new ArrayCollection();
//        foreach($this->tournamentRoles as $tournamentRoles) {
//            $tournaments->add($tournamentRoles->getTournament());
//        }
//
//        return $tournaments;
//    }
}