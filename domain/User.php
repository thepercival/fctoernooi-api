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
    private $emailaddress;

	/**
	 * @var string
	 */
	private $password;

    /**
     * @var string
     */
    private $name;

	const MIN_LENGTH_EMAIL = 6;
    const MAX_LENGTH_EMAIL = 100;
    const MIN_LENGTH_PASSWORD = 3;
    const MAX_LENGTH_PASSWORD = 50;
    const MIN_LENGTH_NAME = 3;
    const MAX_LENGTH_NAME = 15;
    const MAX_LENGTH_HASH = 256;

	public function __construct( $emailaddress, $password )
	{
        $this->setEmailaddress( $emailaddress );
        $this->setPassword( $password );
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
     * @return string
     */
    public function getEmailaddress()
    {
        return $this->emailaddress;
    }

    /**
     * @param string $emailaddress
     */
    public function setEmailaddress( $emailaddress )
    {
        if ( strlen( $emailaddress ) < static::MIN_LENGTH_EMAIL or strlen( $emailaddress ) > static::MAX_LENGTH_EMAIL ){
            throw new \InvalidArgumentException( "het emailadres moet minimaal ".static::MIN_LENGTH_EMAIL." karakters bevatten en mag maximaal ".static::MAX_LENGTH_EMAIL." karakters bevatten", E_ERROR );
        }

        if (!filter_var($emailaddress, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException( "het emailadres ".$emailaddress." is niet valide", E_ERROR );
        }
        $this->emailaddress = $emailaddress;
    }

	/**
	 * @return string
	 */
	public function getPassword()
	{
		return $this->password;
	}

	/**
	 * @param string
	 */
	public function setPassword( $password )
	{
        if ( strlen( $password ) === 0  ){
            throw new \InvalidArgumentException( "de wachtwoord-hash mag niet leeg zijn", E_ERROR );
        }
		$this->password = $password;
	}

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string $name
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