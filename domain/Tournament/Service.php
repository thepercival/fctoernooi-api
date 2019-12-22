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
use Voetbal\Sport\Repository as SportRepository;
use Voetbal\Season\Repository as SeasonRepository;
use Voetbal\League\Repository as LeagueRepository;
use Voetbal\Competition\Repository as CompetitionRepository;
use Voetbal\Competition\Service as CompetitionService;
use FCToernooi\Role;
use Voetbal\Sport\Config as SportConfig;
use Voetbal\Sport\Config\Service as SportConfigService;
use League\Period\Period;

class Service
{
    /**
     * @var TournamentRepository
     */
    protected $tournamentRepos;
    /**
     * @var RoleRepository
     */
    protected $roleRepos;
    /**
     * @var UserRepository
     */
    protected $userRepos;
    /**
     * @var SportRepository
     */
    protected $sportRepos;
    /**
     * @var SeasonRepository
     */
    protected $seasonRepos;
    /**
     * @var LeagueRepository
     */
    protected $leagueRepos;
    /**
     * @var CompetitionRepository
     */
    protected $competitionRepos;


    public function __construct(
        TournamentRepository $tournamentRepos,
        RoleRepository $roleRepos,
        UserRepository $userRepos,
        SportRepository $sportRepos,
        SeasonRepository $seasonRepos,
        LeagueRepository $leagueRepos,
        CompetitionRepository $competitionRepos
    )
    {
        $this->tournamentRepos = $tournamentRepos;
        $this->roleRepos = $roleRepos;
        $this->userRepos = $userRepos;
        $this->sportRepos = $sportRepos;
        $this->seasonRepos = $seasonRepos;
        $this->leagueRepos = $leagueRepos;
        $this->competitionRepos = $competitionRepos;
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

        $season = $this->seasonRepos->findOneBy( array('name' => '9999' ) );

        $ruleSet = $competitionSer->getRuleSet();
        $competitionService = new CompetitionService();
        $competition = $competitionService->create($league, $season, $ruleSet, $competitionSer->getStartDateTime() );

        // add serialized fields and referees to source-competition
        $sportConfigService = new SportConfigService();
        $createFieldsAndReferees = function($sportConfigsSer, $fieldsSer, $refereesSer) use( $competition, $sportConfigService ) {
            foreach( $sportConfigsSer as $sportConfigSer ) {
                $sport = $this->sportRepos->find( $sportConfigSer->getSportIdSer() );
                $sportConfigService->copy( $sportConfigSer, $competition, $sport );
            }
            foreach( $fieldsSer as $fieldSer ) {
                $field = new Field( $competition, $fieldSer->getNumber() );
                $field->setName( $fieldSer->getName() );
                $sport = $this->sportRepos->find( $fieldSer->getSportIdSer() );
                $field->setSport( $sport );
            }
            foreach( $refereesSer as $refereeSer ) {
                $referee = new Referee( $competition, $refereeSer->getRank() );
                $referee->setInitials( $refereeSer->getInitials() );
                $referee->setName( $refereeSer->getName() );
                $referee->setEmailaddress( $refereeSer->getEmailaddress() );
                $referee->setInfo( $refereeSer->getInfo() );
            }
        };
        $createFieldsAndReferees( $competitionSer->getSportConfigs(), $competitionSer->getFields(), $competitionSer->getReferees() );

        $tournament = new Tournament( $competition );
        $tournament->setBreakDuration( 0 );
        $public = $tournamentSer->getPublic() !== null ? $tournamentSer->getPublic() : true;
        $tournament->setPublic( $public );

        $roleService = new RoleService( $this->roleRepos );
        $roleService->create( $tournament, $user, Role::ALL );

        return $tournament;
    }

    /**
     * @param Tournament $tournament
     * @param \DateTimeImmutable $dateTime
     * @param Period|null $period
     * @return Tournament
     * @throws \Exception
     */
    public function changeBasics( Tournament $tournament, \DateTimeImmutable $dateTime, Period $period = null)
    {
        $competitionService = new CompetitionService();
        $competition = $tournament->getCompetition();
        $competitionService->changeStartDateTime( $competition, $dateTime );

        $tournament->setBreak( $period );

        return $tournament;
    }

    /**
     * @param Tournament $tournament
     */
    public function remove( Tournament $tournament )
    {
        return $this->leagueRepos->remove( $tournament->getCompetition()->getLeague() );
    }

    protected function createAssociationFromUserIdAndDateTime( $userId ): Association {
        $dateTime = new \DateTimeImmutable();
        return new Association($userId . '-' . $dateTime->getTimestamp());
    }

    public function mayUserChangeCompetitor( User $user, Association  $association )
    {
        $roleValues = Role::STRUCTUREADMIN;
        $tournaments = $this->tournamentRepos->findByPermissions($user, $roleValues);
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