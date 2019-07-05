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
use Voetbal\Sport\Repository as SportRepository ;
use FCToernooi\Role;
use Voetbal\Sport\Config as SportConfig;
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
     * @var SportRepository
     */
    protected $sportRepos;
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
     * * @param SportRepository $sportRepos
     * @param RoleRepository $roleRepos
     * @param UserRepository $userRepos
     */
    public function __construct(
        \Voetbal\Service $voetbalService,
        TournamentRepository $tournamentRepos,
        SportRepository $sportRepos,
        RoleRepository $roleRepos,
        UserRepository $userRepos
    )
    {
        $this->voetbalService = $voetbalService;
        $this->repos = $tournamentRepos;
        $this->sportRepos = $sportRepos;
        $this->roleRepos = $roleRepos;
        $this->userRepos = $userRepos;
    }

    /**
     * @param Tournament $tournamentSer
     * @param User $user
     * @return Tournament
     * @throws \Exception
     */
    public function createFromSerialized( Tournament $tournamentSer, User $user ): Tournament
    {
        $competitionSer = $tournamentSer->getCompetition();

        $association = $this->createAssociationFromUserIdAndDateTime( $user->getId() );

        $leagueSer = $competitionSer->getLeague();
        $league = new League( $association, $leagueSer->getName() );
        $league->setSportDep( 'voetbal' ); // DEPRECATED

        // check season, per jaar een seizoen, als seizoen niet bestaat, dan aanmaken
        $getSeason = function( int $year) {
            $seasonRepos = $this->voetbalService->getRepository( Season::class );
            $season = $seasonRepos->findOneBy( array('name' => $year ) );
            if( $season === null ){
                $seasonService = $this->voetbalService->getService( Season::class );
                $period = new Period( new \DateTimeImmutable($year."-01-01"), new \DateTimeImmutable(($year+1)."-01-01") );
                $season = $seasonService->create( $year, $period );
            }
            return $season;
        };
        $season = $getSeason( (int) $competitionSer->getStartDateTime()->format("Y") );

        $competitionService = $this->voetbalService->getService(Competition::class);
        $ruleSet = $competitionSer->getRuleSet();
        $competition = $competitionService->create($league, $season, $ruleSet, $competitionSer->getStartDateTime() );

        // add serialized fields and referees to source-competition
        $createFieldsAndReferees = function($sportConfigsSer, $fieldsSer, $refereesSer) use( $competition ) {
            foreach( $sportConfigsSer as $sportConfigSer ) {
                $sport = $this->sportRepos->find( $sportConfigSer->getSport()->getId() );
                $sportConfig = new SportConfig( $sport, $competition );
                $sportConfig->setName( $sportConfigSer->getName() );
            }
            foreach( $fieldsSer as $fieldSer ) {
                $field = new Field( $competition, $fieldSer->getNumber() );
                $field->setName( $fieldSer->getName() );
            }
            foreach( $refereesSer as $refereeSer ) {
                $referee = new Referee( $competition, $refereeSer->getInitials() );
                $referee->setName( $refereeSer->getName() );
                $referee->setEmailaddress( $refereeSer->getEmailaddress() );
                $referee->setInfo( $refereeSer->getInfo() );
            }
        };
        $createFieldsAndReferees( $competitionSer->getSportConfigs(), $competitionSer->getFields(), $competitionSer->getReferees() );

        $tournament = new Tournament( $competition );
        $tournament->setBreakDuration( 0 );
        $public = is_bool( $tournamentSer->getPublic() ) ? $tournamentSer->getPublic() : true;
        $tournament->setPublic( $public );

        $roleService = new RoleService( $this->roleRepos );
        $roleService->create( $tournament, $user, Role::ALL );

        return $tournament;
    }

    /**
     * @param Tournament $tournament
     * @param \DateTimeImmutable $dateTime
     * @param string $name
     * @param BreakX|null $break
     * @return Tournament
     * @throws \Exception
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

    protected function createAssociationFromUserIdAndDateTime( $userId ): Association {
        $dateTime = new \DateTimeImmutable();
        return new Association($userId . '-' . $dateTime->getTimestamp());
    }

    public function mayUserChangeCompetitor( User $user, Association  $association )
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

    public function syncRefereeRoles( Tournament $tournament ): array
    {
        $em = $this->roleRepos->getEM();

        // remove referee roles
        {
            $params = ['value' => Role::REFEREE, 'tournament' => $tournament];
            $refereeRoles = $this->roleRepos->findBy( $params );
            foreach( $refereeRoles as $refereeRole ) {
                $em->remove( $refereeRole );
            }
        }
        $em->flush();

        // add referee roles
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
            $em->persist( $refereeRole );
        }
        $rolesRet = $tournament->getRoles()->toArray();

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

    /**
     * echt nieuwe aanmaken via service, bestaande toernooi deserialising en dan weer opslaan
     *
     * @param Tournament $tournament
     * @param User $user
     * @param \DateTimeImmutable $startDateTime
     * @return Tournament
     * @throws \Exception
     */
    public function copy(Tournament $tournament, User $user, \DateTimeImmutable $startDateTime): Tournament
    {
        $newTournament = $this->createFromSerialized( $tournament, $user );
        $newTournament->getCompetition()->setStartDateTime( $startDateTime );
        return $newTournament;
    }
}