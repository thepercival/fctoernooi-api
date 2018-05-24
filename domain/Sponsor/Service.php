<?php
/**
 * Created by PhpStorm.
 * User: coen
 * Date: 22-5-18
 * Time: 13:18
 */

namespace FCToernooi\Sponsor;

use Doctrine\DBAL\Connection;
use FCToernooi\Sponsor as Sponsor;
use FCToernooi\Tournament;
use FCToernooi\Sponsor\Repository as SponsorRepository;

class Service
{
    /**
     * @var SponsorRepository
     */
    protected $repos;
    /**
     * @var Connection
     */
    protected $conn;


    /**
     * Service constructor.
     * @param SponsorRepository $repos
     * @param Connection $conn
     */
    public function __construct( SponsorRepository $repos, Connection $conn )
    {
        $this->repos = $repos;
        $this->conn = $conn;
    }

    /**
     * @param Tournament $tournament
     * @param string $name
     * @param string|null $url
     * @return Sponsor|null
     * @throws \Exception
     */
    public function create( Tournament $tournament, string $name, string $url = null )
    {
        $this->conn->beginTransaction();
        $sponsor = null;
        try {
            $sponsor = new Sponsor( $tournament, $name );
            $sponsor->setUrl( $url );
            $sponsor = $this->repos->save($sponsor);

            $this->conn->commit();
        } catch (\Exception $e) {
            $this->conn->rollback();
            throw $e;
        }
        return $sponsor;
    }

    /**
     * @param Sponsor $sponsor
     * @param string $name
     * @param string|null $url
     * @return Sponsor
     * @throws \Exception
     */
    public function changeBasics( Sponsor $sponsor, string $name, string $url = null )
    {
        $this->conn->beginTransaction();
        try {
            $sponsor->setName( $name );
            $sponsor->setUrl( $url );
            $sponsor = $this->repos->save($sponsor);

            $this->conn->commit();
        } catch (\Exception $e) {
            $this->conn->rollback();
            throw $e;
        }
        return $sponsor;
    }

    /**
     * @param Sponsor $sponsor
     */
    public function remove( Sponsor $sponsor )
    {
        return $this->repos->remove( $sponsor );
    }
}