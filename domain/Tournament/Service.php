<?php
/**
 * Created by PhpStorm.
 * User: coen
 * Date: 1-10-17
 * Time: 21:41
 */

namespace FCToernooi\Tournament;

use FCToernooi\User as User;
use Voetbal\Association;
use Voetbal\Field;
use Voetbal\Referee;
use Voetbal\League;
use Voetbal\Season;
use Voetbal\Competition;
use FCToernooi\Tournament;
use FCToernooi\Tournament\Repository as TournamentRepository;
use FCToernooi\Tournament\Role\Repository as TournamentRoleRepository;
use FCToernooi\Tournament\Role\Service as TournamentRoleService;
use League\Period\Period;
use Doctrine\ORM\EntityManager as EntityManager;

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
     * @var TournamentRoleRepository
     */
    protected $tournamentRoleRepos;

    /**
     * @var EntityManager
     */
    protected $em;


    /**
     * Service constructor.
     * @param \Voetbal\Service $voetbalService
     * @param TournamentRoleRepository $tournamentRoleRepos
     * @param EntityManager $em
     */
    public function __construct(
        \Voetbal\Service $voetbalService,
        TournamentRepository $tournamentRepos,
        TournamentRoleRepository $tournamentRoleRepos,
        EntityManager $em
    )
    {
        $this->voetbalService = $voetbalService;
        $this->repos = $tournamentRepos;
        $this->tournamentRoleRepos = $tournamentRoleRepos;
        $this->em = $em;
    }

    /**
     * @param Tournament $tournamentSer
     * @param User $user
     * @return null
     * @throws \Exception
     */
    public function create( Tournament $tournamentSer, User $user )
    {
        $this->em->getConnection()->beginTransaction();
        $competitionSer = $tournamentSer->getCompetition();
        $tournament = null;
        try {
            // create association
            $associationName = static::getAssociationNameFromUserId( $user->getId() );
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

            $tournamentRoleService = new TournamentRoleService( $this->tournamentRoleRepos );
            $tournamentRoles = $tournamentRoleService->set( $tournament, $user, Role::ALL );

            $this->em->getConnection()->commit();
        } catch (\Exception $e) {
            // Rollback the failed transaction attempt
            $this->em->getConnection()->rollback();
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

    public static function getAssociationNameFromUserId( $userId ) {
        $userId = (string) $userId;
        while( strlen($userId) < Association::MIN_LENGTH_NAME ) {
            $userId = "0" . $userId;
        }
        return $userId;
    }
}