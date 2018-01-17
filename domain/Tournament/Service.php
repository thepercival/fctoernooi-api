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
use Voetbal\Competition;
use Voetbal\Season;
use Voetbal\Competitionseason;
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
     * @param Tournament $tournament
     * @param User $user
     * @return Tournament|null
     * @throws \Exception
     */
    public function createFromJSON( Tournament $p_tournament, User $user )
    {
        $this->em->getConnection()->beginTransaction();
        $competitionseason = $p_tournament->getCompetitionseason();
        $tournament = null;
        try {
            $associationName = static::getAssociationNameFromUserId( $user->getId() );
            $associationRepos = $this->voetbalService->getRepository(Association::class);
            $association = $associationRepos->findOneBy( array( 'name' => $associationName ) );
            if( $association === null ){
                $assService = $this->voetbalService->getService( Association::class );
                $association = $assService->create( $associationName );
            }

            // check competition, check als naam niet bestaat
            $competitionRepos = $this->voetbalService->getRepository(Competition::class);
            $competition = $competitionRepos->findOneBy( array('name' => $competitionseason->getCompetition()->getName() ) );
            if ( $competition !== null ){
                throw new \Exception("de competitienaam bestaat al", E_ERROR );
            }
            // $compService = $this->voetbalService->getService( Competition::class );
            // $competition = $compService->create( $name );

            // check season, per jaar een seizoen, als seizoen niet bestaat, dan aanmaken
            $year = date("Y");
            $seasonRepos = $this->voetbalService->getRepository( Season::class );
            $season = $seasonRepos->findOneBy(
                array('name' => $year )
            );
            if( $season === null ){
                $seasonService = $this->voetbalService->getService( Season::class );
                $period = new Period( new \DateTimeImmutable($year."-01-01"), new \DateTimeImmutable($year."-12-31") );
                $season = $seasonService->create( $year, $period );
            }

            // DO POST SERIALIZING!!
            $competitionseason->setAssociation( $association );
            $competitionseason->setSeason( $season );
            $csRepos = $this->voetbalService->getRepository(Competitionseason::class);
            $csRepos->saveFromJSON($competitionseason);

            $tournament = $this->repos->save($p_tournament);

            $tournamentRoleService = new TournamentRoleService( $this->tournamentRoleRepos );
            $tournamentRoles = $tournamentRoleService->set( $tournament, $user, Role::ALL );

//            $nrOfPlaces = $round->getPoulePlaces()->count();
//            if ( $nrOfPlaces < Tournament::MINNROFCOMPETITORS ){
//                throw new \Exception("het minimum aantal deelnemer is " . Tournament::MINNROFCOMPETITORS);
//            }
//            if ( $nrOfPlaces > Tournament::MAXNROFCOMPETITORS ){
//                throw new \Exception("het minimum aantal deelnemer is " . Tournament::MAXNROFCOMPETITORS);
//            }
//
//            $structureService = $this->voetbalService->getService(\Voetbal\Structure::class);
//            $firstRound = $structureService->create( $competitionseason, $round );
//
//            $planningService = $this->voetbalService->getService(\Voetbal\Planning::class);
//            $planningService->create( $firstRound, $competitionseason->getStartDateTime() );

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
     * @param $name
     * @param $startDateTime
     * @return bool
     */
    public function editFromJSON( Tournament $tournament, User $user )
    {
        $csRepos = $this->voetbalService->getRepository(Competitionseason::class);

        $csRepos->editFromJSON($tournament->getCompetitionseason());

//        $competitionseason = $tournament->getCompetitionseason();
//        $csRepos->onPostSerialize( $competitionseason );
//        $competitionseason = $csRepos->merge( $competitionseason );
//        $csRepos->save( $competitionseason );
//        $tournament->setCompetitionseason( $competitionseason );

//        $competition = $tournament->getCompetitionseason()->getCompetition();
//        $competition->setName($name);
//        $competitionRepos = $this->voetbalService->getRepository(Competition::class);
//        $competitionRepos->save($competition);
//
//        $competitionseason = $tournament->getCompetitionseason();
//        $competitionseason->setStartDateTime($startDateTime);
//        $competitionseasonRepos = $this->voetbalService->getRepository(Competitionseason::class);
//        $competitionseasonRepos->save($competitionseason);

        return $tournament;
    }

    /**
     * @param Tournament $tournament
     */
    public function remove( Tournament $tournament )
    {
        $competitionRepos = $this->voetbalService->getRepository(Competition::class);
        return $competitionRepos->remove( $tournament->getCompetitionseason()->getCompetition() );
    }

    public static function getAssociationNameFromUserId( $userId ) {
        $userId = (string) $userId;
        while( strlen($userId) < Association::MIN_LENGTH_NAME ) {
            $userId = "0" . $userId;
        }
        return $userId;
    }
}