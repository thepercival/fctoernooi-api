<?php
/**
 * Created by PhpStorm.
 * User: coen
 * Date: 1-10-17
 * Time: 21:41
 */

namespace FCToernooi\Tournament;

use Doctrine\DBAL\Connection;
use FCToernooi\User;
use Voetbal\Association;
use Voetbal\Field;
use Voetbal\Referee;
use Voetbal\League;
use Voetbal\Season;
use Voetbal\Competition;
use FCToernooi\Tournament;
use FCToernooi\Tournament\Repository as TournamentRepository;
use FCToernooi\Role\Repository as RoleRepository;
use FCToernooi\Role\Service as RoleService;
use FCToernooi\Role;
use League\Period\Period;

class Service
{

    /**
     * @var \Voetbal\Service
     */
    protected $voetbalService;
    /**
     * @var TournamentRepository
     */
    protected $repos;
    /**
     * @var RoleRepository
     */
    protected $roleRepos;

    /**
     * @var Connection
     */
    protected $conn;


    /**
     * Service constructor.
     * @param \Voetbal\Service $voetbalService
     * @param Repository $tournamentRepos
     * @param RoleRepository $roleRepos
     * @param \FCToernooi\Tournament\Connection $conn
     */
    public function __construct(
        \Voetbal\Service $voetbalService,
        TournamentRepository $tournamentRepos,
        RoleRepository $roleRepos,
        Connection $conn
    )
    {
        $this->voetbalService = $voetbalService;
        $this->repos = $tournamentRepos;
        $this->roleRepos = $roleRepos;
        $this->conn = $conn;
    }

    /**
     * @param Tournament $tournamentSer
     * @param User $user
     * @return null
     * @throws \Exception
     */
    public function create( Tournament $tournamentSer, User $user )
    {
        $this->conn->beginTransaction();
        $competitionSer = $tournamentSer->getCompetition();
        $tournament = null;
        try {
            // create association
            $associationName = $this->getAssociationNameFromUserId( $user->getId() );
            $associationRepos = $this->voetbalService->getRepository(Association::class);
            $association = $associationRepos->findOneBy( array( 'name' => $associationName ) );
            if( $association === null ){
                $assService = $this->voetbalService->getService( Association::class );
                $association = $assService->create( $associationName );
            }

            // create league
            $leagueSer = $competitionSer->getLeague();
            $leagueService = $this->voetbalService->getService( League::class );
            $league = $leagueService->create( $leagueSer->getName(), $leagueSer->getSport(), $association );

            // check season, per jaar een seizoen, als seizoen niet bestaat, dan aanmaken
            $year = date("Y");
            $seasonRepos = $this->voetbalService->getRepository( Season::class );
            $season = $seasonRepos->findOneBy( array('name' => $year ) );
            if( $season === null ){
                $seasonService = $this->voetbalService->getService( Season::class );
                $period = new Period( new \DateTimeImmutable($year."-01-01"), new \DateTimeImmutable($year."-12-31") );
                $season = $seasonService->create( $year, $period );
            }

            $fieldsSer = $competitionSer->getFields();
            $competitionSer->setFields([]);
            $refereesSer = $competitionSer->getReferees();
            $competitionSer->setReferees([]);
            $competitionService = $this->voetbalService->getService(Competition::class);
            $competition = $competitionService->create($league, $season, $competitionSer->getStartDateTime() );

            $fieldService = $this->voetbalService->getService( Field::class );
            foreach( $fieldsSer as $fieldSet ) {
                $fieldService->create( $fieldSet->getNumber(), $fieldSet->getName(), $competition );
            }

            $refereeService = $this->voetbalService->getService( Referee::class );
            foreach( $refereesSer as $referesSer ) {
                $refereeService->create( $referesSer->getInitials(), $referesSer->getName(), $competition );
            }

            $tournamentSer->setCompetition($competition);
            $tournament = $this->repos->save($tournamentSer);

            $roleService = new RoleService( $this->roleRepos );
            $roles = $roleService->set( $tournament, $user, Role::ALL );

            $this->conn->commit();
        } catch (\Exception $e) {
            // Rollback the failed transaction attempt
            $this->conn->rollback();
            throw $e;
        }
        return $tournament;
    }

    /**
     * @param Tournament $tournament
     * @param \DateTimeImmutable $dateTime
     * @param $name
     * @return Tournament
     */
    public function changeBasics( Tournament $tournament, \DateTimeImmutable $dateTime, $name )
    {
        $competitionService = $this->voetbalService->getService(Competition::class);
        $competition = $tournament->getCompetition();
        $competitionService->changeStartDateTime( $competition, $dateTime );

        $leagueService = $this->voetbalService->getService(League::class);
        $league = $tournament->getCompetition()->getLeague();
        $leagueService->changeBasics( $league, $name, null );

        return $tournament;
    }

    /**
     * @param Tournament $tournament
     */
    public function remove( Tournament $tournament )
    {
        $leagueRepos = $this->voetbalService->getRepository(League::class);
        return $leagueRepos->remove( $tournament->getCompetition()->getLeague() );
    }

    public function getAssociationNameFromUserId( $userId ) {
        $userId = (string) $userId;
        while( strlen($userId) < Association::MIN_LENGTH_NAME ) {
            $userId = "0" . $userId;
        }
        return $userId;
    }

    public function mayUserChangeTeam( User $user, Association  $association )
    {
        $roleValues = Role::ADMIN + Role::STRUCTUREADMIN;
        $tournaments = $this->repos->findByPermissions($user, $roleValues);
        foreach ($tournaments as $tournament) {
            if ($tournament->getCompetition()->getLeague()->getAssociation() === $association) {
                return true;
            }
        }
        return false;
    }
}