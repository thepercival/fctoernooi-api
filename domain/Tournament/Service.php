<?php
/**
 * Created by PhpStorm.
 * User: coen
 * Date: 1-10-17
 * Time: 21:41
 */

namespace FCToernooi\Tournament;

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
use FCToernooi\User\Repository as UserRepository;
use FCToernooi\Role\Service as RoleService;
use FCToernooi\Role;
use League\Period\Period;
use FCToernooi\Tournament\BreakX;

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
     * @var UserRepository
     */
    protected $userRepos;


    /**
     * Service constructor.
     * @param \Voetbal\Service $voetbalService
     * @param Repository $tournamentRepos
     * @param RoleRepository $roleRepos
     * @param UserRepository $userRepos
     */
    public function __construct(
        \Voetbal\Service $voetbalService,
        TournamentRepository $tournamentRepos,
        RoleRepository $roleRepos,
        UserRepository $userRepos
    )
    {
        $this->voetbalService = $voetbalService;
        $this->repos = $tournamentRepos;
        $this->roleRepos = $roleRepos;
        $this->userRepos = $userRepos;
    }

    /**
     * @param Tournament $tournamentSer
     * @param User $user
     * @return null
     * @throws \Exception
     */
    public function createFromSerialized( Tournament $tournamentSer, User $user )
    {
        $competitionSer = $tournamentSer->getCompetition();

        $getAssociation = function($name) use( $user ) {
            $associationRepos = $this->voetbalService->getRepository(Association::class);
            $association = $associationRepos->findOneBy( array( 'name' => $name ) );
            if( $association === null ){
                $assService = $this->voetbalService->getService( Association::class );
                $association = $assService->create( $name );
            }
            return $association;
        };
        $association = $getAssociation( $this->getAssociationNameFromUserId( $user->getId() ) );

        $leagueSer = $competitionSer->getLeague();
        $leagueService = $this->voetbalService->getService( League::class );
        $league = $leagueService->create( $leagueSer->getName(), $leagueSer->getSport(), $association );

        // check season, per jaar een seizoen, als seizoen niet bestaat, dan aanmaken
        $getSeason = function($year) {
            $seasonRepos = $this->voetbalService->getRepository( Season::class );
            $season = $seasonRepos->findOneBy( array('name' => $year ) );
            if( $season === null ){
                $seasonService = $this->voetbalService->getService( Season::class );
                $period = new Period( new \DateTimeImmutable($year."-01-01"), new \DateTimeImmutable($year."-12-31") );
                $season = $seasonService->create( $year, $period );
            }
            return $season;
        };
        $season = $getSeason( $competitionSer->getStartDateTime()->format("Y") );

        $competitionService = $this->voetbalService->getService(Competition::class);
        $competition = $competitionService->create($league, $season, $competitionSer->getStartDateTime() );

        $createFieldsAndReferees = function($fieldsSer, $refereesSer) use( $competition ) {
            $fieldService = $this->voetbalService->getService( Field::class );
            foreach( $fieldsSer as $fieldSet ) {
                $fieldService->create( $fieldSet->getNumber(), $fieldSet->getName(), $competition );
            }
            $refereeService = $this->voetbalService->getService( Referee::class );
            foreach( $refereesSer as $referesSer ) {
                $refereeService->create( $referesSer->getInitials(), $referesSer->getName(), $competition );
            }
        };
        $createFieldsAndReferees( $competitionSer->getFields(), $competitionSer->getReferees() );

        $tournament = new Tournament( $competition );
        $tournament->setBreakDuration( 0 );

        $roleService = new RoleService( $this->roleRepos );
        $roleService->create( $tournament, $user, Role::ALL );

        return $tournament;
    }

    /**
     * @param Tournament $tournament
     * @param \DateTimeImmutable $dateTime
     * @param $name
     * @return Tournament
     */
    public function changeBasics( Tournament $tournament, \DateTimeImmutable $dateTime, string $name, BreakX $break = null)
    {
        $competitionService = $this->voetbalService->getService(Competition::class);
        $competition = $tournament->getCompetition();
        $competitionService->changeStartDateTime( $competition, $dateTime );

        $leagueService = $this->voetbalService->getService(League::class);
        $league = $tournament->getCompetition()->getLeague();
        $leagueService->changeBasics( $league, $name, null );

        $tournament->setBreak( $break );

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
        $roleValues = Role::STRUCTUREADMIN;
        $tournaments = $this->repos->findByPermissions($user, $roleValues);
        foreach ($tournaments as $tournament) {
            if ($tournament->getCompetition()->getLeague()->getAssociation() === $association) {
                return true;
            }
        }
        return false;
    }

    public function syncRefereeRoles( Tournament $tournament = null, User $user = null ): array
    {
        if( $tournament === null && $user === null ) {
            throw new \Exception("toernooi en gebruiker kunnen niet allebei leeg zijn om scheidsrechter-rollen te synchroniseren", E_ERROR );
        }

        $rolesRet = [];
        $em = $this->roleRepos->getEM();

        // remove referee roles
        {
            $params = ['value' => Role::REFEREE];
            if( $user !== null ) {
                $params['user'] = $user;
            } else if( $tournament !== null ) {
                $params['tournament'] = $tournament;
            }
            $refereeRoles = $this->roleRepos->findBy( $params );
            foreach( $refereeRoles as $refereeRole ) {
                $em->remove( $refereeRole );
            }
        }

        // add referee roles
        if( $user !== null ) {
            $tournaments = $this->repos->findByEmailaddress( $user->getEmailaddress() );
            foreach( $tournaments as $tournament ) {
                $refereeRole = new Role( $tournament, $user);
                $refereeRole->setValue(Role::REFEREE);
                $em->save( $refereeRole );
                $rolesRet[] = $refereeRole;
            }

        } else if( $tournament !== null ) {
            $referees = $tournament->getCompetition()->getReferees();
            foreach( $referees as $referee ) {
                if( strlen( $referee->getEmailaddress() ) === 0 ) {
                    continue;
                }
                $user = $this->userRepos->findOneBy( ['emailaddress' => $referee->getEmailaddress()] );
                if( $user === null ) {
                    continue;
                }
                $refereeRole = new Role( $tournament, $user);
                $refereeRole->setValue(Role::REFEREE);
                $em->save( $refereeRole );
            }
            $rolesRet = $tournament->getRoles()->toArray();
        }
        $em->flush();
        return $rolesRet;
    }

    public function getReferee( Tournament $tournament, string $emailaddress )
    {
        $referees = $tournament->getCompetition()->getReferees();
        foreach( $referees as $referee ) {
            if( $referee->getEmailaddress() === $emailaddress ) {
                return $referee;
            }
        }
        return null;
    }


}