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
     * @var Connection
     */
    protected $conn;


    /**
     * Service constructor.
     * @param \Voetbal\Service $voetbalService
     * @param Repository $tournamentRepos
     * @param RoleRepository $roleRepos
     * @param UserRepository $userRepos
     * @param Connection $conn
     */
    public function __construct(
        \Voetbal\Service $voetbalService,
        TournamentRepository $tournamentRepos,
        RoleRepository $roleRepos,
        UserRepository $userRepos,
        Connection $conn
    )
    {
        $this->voetbalService = $voetbalService;
        $this->repos = $tournamentRepos;
        $this->roleRepos = $roleRepos;
        $this->userRepos = $userRepos;
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
            $year = $competitionSer->getStartDateTime()->format("Y");
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
            $tournamentSer->setBreakDuration( 0 );
            $tournament = $this->repos->save($tournamentSer);

            $roleService = new RoleService( $this->roleRepos );
            $roles = $roleService->set( $tournament, $user, Role::ALL );

            $this->conn->commit();

            try {
                $this->mailFirstTimeWelcome( $user, $tournament );
            }
            catch (\Exception $e) {

            }
        } catch (\Exception $e) {
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
    public function changeBasics( Tournament $tournament, \DateTimeImmutable $dateTime, string $name, BreakX $break = null)
    {
        $competitionService = $this->voetbalService->getService(Competition::class);
        $competition = $tournament->getCompetition();
        $competitionService->changeStartDateTime( $competition, $dateTime );

        $leagueService = $this->voetbalService->getService(League::class);
        $league = $tournament->getCompetition()->getLeague();
        $leagueService->changeBasics( $league, $name, null );

        $tournament->setBreak( $break );
        $this->repos->save( $tournament );

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

        $this->conn->beginTransaction();

        $rolesRet = [];
        try {
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
                    $this->roleRepos->remove( $refereeRole );
                }
            }

            // add referee roles
            if( $user !== null ) {
                $tournaments = $this->repos->findByEmailaddress( $user->getEmailaddress() );
                foreach( $tournaments as $tournament ) {
                    $refereeRole = new Role( $tournament, $user);
                    $refereeRole->setValue(Role::REFEREE);
                    $this->roleRepos->save( $refereeRole );
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
                    $this->roleRepos->save( $refereeRole );
                }
                $rolesRet = $tournament->getRoles()->toArray();
            }

            $this->conn->commit();

        } catch (\Exception $e) {
            // Rollback the failed transaction attempt
            $this->conn->rollback();
            throw $e;
        }
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

    protected function mailFirstTimeWelcome( User $user, Tournament $tournament )
    {
        $tournaments = $this->repos->findByPermissions( $user, Role::ADMIN );
        if( count( $tournaments ) > 1 ) {
            return;
        }
        $subject = 'FCToernooi - ' . $tournament->getCompetition()->getName();
        $body = '
        <p>Hallo,</p>
        <p>            
        Als beheerder van <a href="https://www.fctoernooi.nl/">https://www.fctoernooi.nl/</a> zag ik dat je een toernooi hebt aangemaakt op onze website. 
        Mocht je vragen hebben of feedback willen geven, dan horen we dat graag. Beantwoord dan deze email.
        </p>
        <p>            
        Veel plezier met het gebruik van onze website! De handleiding kun je <a href="https://docs.google.com/document/d/1SYeUJa5yvHZzvacMyJ_Xy4MpHWTWRgAh1LYkEA2CFnM/edit?usp=sharing">hier</a> vinden.
        </p>
        <p>
        met vriendelijke groet,
        <br>
        Coen Dunnink<br>
        https://www.fctoernooi.nl/<br>
        06-14363514
        </p>';

        $from = "FCToernooi";
        $fromEmail = "coendunnink@gmail.com";
        $headers  = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8" . "\r\n";
        $headers .= "From: ".$from." <" . $fromEmail . ">" . "\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion();
        $params = "-r ".$fromEmail;

        if ( !mail( $user->getEmailaddress(), $subject, $body, $headers, $params) ) {
            // $app->flash("error", "We're having trouble with our mail servers at the moment.  Please try again later, or contact us directly by phone.");
            error_log('Mailer Error!' );
            // $app->halt(500);
        }
    }
}