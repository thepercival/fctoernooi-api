<?php
/**
 * Created by PhpStorm.
 * User: coen
 * Date: 22-5-18
 * Time: 12:23
 */

namespace App\Action;

//use Slim\ServerRequestInterface;
use JMS\Serializer\Serializer;
use FCToernooi\User\Repository as UserRepository;
use FCToernooi\Sponsor\Service as SponsorService;
use FCToernooi\Sponsor\Repository as SponsorRepository;
use FCToernooi\Tournament\Repository as TournamentRepository;
use FCToernooi\Token;

final class Sponsor
{
    /**
     * @var SponsorService
     */
    private $service;
    /**
     * @var SponsorRepos
     */
    private $repos;
    /**
     * @var UserRepository
     */
    private $userRepos;
    /**
     * @var TournamentRepository
     */
    private $tournamentRepos;
    /**
     * @var Serializer
     */
    protected $serializer;

    /**
     * @var Token
     */
    protected $token;

    use AuthTrait;

    public function __construct(
        SponsorService $service,
        SponsorRepository $repos,
        TournamentRepository $tournamentRepos,
        UserRepository $userRepository,
        Serializer $serializer,
        Token $token
    )
    {
        $this->service = $service;
        $this->repos = $repos;
        $this->tournamentRepos = $tournamentRepos;
        $this->userRepos = $userRepository;
        $this->serializer = $serializer;
        $this->token = $token;
    }

    /**
     * startdatetime, enddatetime, id, userid
     *
     * @param $request
     * @param $response
     * @param $args
     * @return mixed
     */
    public function fetch($request, $response, $args)
    {
        $sErrorMessage = null;
        try {
            $tournamentId = (int)$request->getParam("tournamentid");
            $tournament = $this->tournamentRepos->find($tournamentId);
            if (!$tournament) {
                throw new \Exception("geen toernooi met het opgegeven id gevonden", E_ERROR);
            }
            return $response
                ->withHeader('Content-Type', 'application/json;charset=utf-8')
                ->write($this->serializer->serialize( $tournament->getSponsors(), 'json'));
            ;
        }
        catch( \Exception $e ){
            $sErrorMessage = $e->getMessage();
        }
        return $response->withStatus(422)->write( $sErrorMessage);
    }

    public function fetchOne($request, $response, $args)
    {
        $sErrorMessage = null;
        try {
            $tournamentId = (int)$request->getParam("tournamentid");
            $tournament = $this->tournamentRepos->find($tournamentId);
            if (!$tournament) {
                throw new \Exception("geen toernooi met het opgegeven id gevonden", E_ERROR);
            }

            $sponsor = $this->repos->find($args['id']);
            if (!$sponsor) {
                throw new \Exception("geen sponsor met het opgegeven id gevonden", E_ERROR);
            }
            return $response
                ->withHeader('Content-Type', 'application/json;charset=utf-8')
                ->write($this->serializer->serialize( $sponsor, 'json'));
            ;
        }
        catch( \Exception $e ){
            $sErrorMessage = $e->getMessage();
        }
        return $response->withStatus(422)->write( $sErrorMessage);
    }

    public function add( $request, $response, $args)
    {
        $sErrorMessage = null;
        try {
            $tournamentId = (int)$request->getParam("tournamentid");
            /** @var \FCToernooi\Tournament $tournament */
            $tournament = $this->tournamentRepos->find($tournamentId);
            if (!$tournament) {
                throw new \Exception("geen toernooi met het opgegeven id gevonden", E_ERROR);
            }

            $user = $this->checkAuth( $this->token, $this->userRepos );

            /** @var \FCToernooi\Sponsor $sponsorSer */
            $sponsorSer = $this->serializer->deserialize( json_encode($request->getParsedBody()), 'FCToernooi\Sponsor', 'json');

            $sponsor = $this->service->create( $tournament, $sponsorSer->getName(), $sponsorSer->getUrl() );

            return $response
                ->withStatus(201)
                ->withHeader('Content-Type', 'application/json;charset=utf-8')
                ->write($this->serializer->serialize( $sponsor, 'json'));
            ;
        }
        catch( \Exception $e ){
            $sErrorMessage = $e->getMessage();
        }
        return $response->withStatus(422 )->write( $sErrorMessage );
    }

    public function edit( $request, $response, $args)
    {
        $sErrorMessage = null;
        try {
            $tournamentId = (int)$request->getParam("tournamentid");
            /** @var \FCToernooi\Tournament $tournament */
            $tournament = $this->tournamentRepos->find($tournamentId);
            if (!$tournament) {
                throw new \Exception("geen toernooi met het opgegeven id gevonden", E_ERROR);
            }

            $user = $this->checkAuth( $this->token, $this->userRepos, $tournament );

            /** @var \FCToernooi\Sponsor $sponsorSer */
            $sponsorSer = $this->serializer->deserialize( json_encode($request->getParsedBody()), 'FCToernooi\Sponsor', 'json');

            $sponsor = $this->repos->find( $sponsorSer->getId() );
            if ( $sponsor === null ){
                return $response->withStatus(404)->write( "de te wijzigen sponsor kon niet gevonden worden" );
            }

            $sponsor = $this->service->changeBasics( $sponsor, $sponsorSer->getName(), $sponsorSer->getUrl() );

            return $response
                ->withStatus(200)
                ->withHeader('Content-Type', 'application/json;charset=utf-8')
                ->write($this->serializer->serialize( $sponsor, 'json'));
            ;
        }
        catch( \Exception $e ){
            $sErrorMessage = $e->getMessage();
        }
        return $response->withStatus(422)->write( $sErrorMessage );
    }

    public function remove( $request, $response, $args)
    {
        $errorMessage = null;
        try{
            $tournamentId = (int)$request->getParam("tournamentid");
            /** @var \FCToernooi\Tournament $tournament */
            $tournament = $this->tournamentRepos->find($tournamentId);
            if (!$tournament) {
                throw new \Exception("geen toernooi met het opgegeven id gevonden", E_ERROR);
            }

            $user = $this->checkAuth( $this->token, $this->userRepos, $tournament );

            /** @var \FCToernooi\Sponsor $sponsor */
            $sponsor = $this->repos->find( $args['id'] );
            if ( $sponsor === null ){
                return $response->withStatus(404)->write("de te verwijderen sponsor kon niet gevonden worden" );
            }
            $this->service->remove( $sponsor );

            return $response->withStatus(200);
        }
        catch( \Exception $e ){
            $errorMessage = $e->getMessage();
        }
        return $response->withStatus(404)->write('de sponsor is niet verwijdered : ' . $errorMessage );
    }
}