<?php
/**
 * Created by PhpStorm.
 * User: coen
 * Date: 6-10-17
 * Time: 11:40
 */

namespace App\Action;

use Slim\ServerRequestInterface;
use JMS\Serializer\Serializer;
use FCToernooi\User\Repository as UserRepository;
use FCToernooi\Tournament\Service as TournamentService;
use FCToernooi\Tournament\Repository as TournamentRepository;
use FCToernooi\Role;
use Voetbal\Structure\Service as StructureService;
use Voetbal\Planning\Service as PlanningService;
use FCToernooi\Token;

final class Tournament
{
    /**
     * @var TournamentService
     */
    private $service;

    /**
     * @var TournamentRepos
     */
    private $repos;

    /**
     * @var UserRepository
     */
    private $userRepository;

    /**
     * @var StructureService
     */
    private $structureService;

    /**
     * @var PlanningService
     */
    private $planningService;

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
        TournamentService $service,
        TournamentRepository $repos,
        UserRepository $userRepository,
        StructureService $structureService,
        PlanningService $planningService,
        Serializer $serializer,
        Token $token
    )
    {
        $this->service = $service;
        $this->repos = $repos;
        $this->userRepository = $userRepository;
        $this->structureService = $structureService;
        $this->planningService = $planningService;
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
            $startDateTime = \DateTimeImmutable::createFromFormat ( 'Y-m-d\TH:i:s.u\Z', $request->getParam('startDateTime') );
            if ( $startDateTime === false ){ $startDateTime = null; }
            $endDateTime = \DateTimeImmutable::createFromFormat ( 'Y-m-d\TH:i:s.u\Z', $request->getParam('endDateTime') );
            if ( $endDateTime === false ){ $endDateTime = null; }

            $tournaments = $this->repos->findByPeriod(
                $startDateTime, $endDateTime
            );
            return $response
                ->withHeader('Content-Type', 'application/json;charset=utf-8')
                ->write($this->serializer->serialize( $tournaments, 'json'));
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
            $tournament = $this->repos->find($args['id']);
            if (!$tournament) {
                throw new \Exception("geen toernooi met het opgegeven id gevonden", E_ERROR);
            }
            return $response
                ->withHeader('Content-Type', 'application/json;charset=utf-8')
                ->write($this->serializer->serialize( $tournament, 'json'));
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
            /** @var \FCToernooi\Tournament $tournament */
            $tournament = $this->serializer->deserialize( json_encode($request->getParsedBody()), 'FCToernooi\Tournament', 'json');

            $user = $this->checkAuth( $this->token, $this->userRepository );
            $tournament = $this->service->create( $tournament, $user );

            return $response
                ->withStatus(201)
                ->withHeader('Content-Type', 'application/json;charset=utf-8')
                ->write($this->serializer->serialize( $tournament, 'json'));
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
            /** @var \FCToernooi\Tournament $tournament */
            $tournamentSer = $this->serializer->deserialize( json_encode($request->getParsedBody()), 'FCToernooi\Tournament', 'json');

            $tournament = $this->repos->find( $tournamentSer->getId() );
            if ( $tournament === null ){
                return $response->withStatus(404)->write("het te wijzigen toernooi kon niet gevonden worden" );
            }

            $user = $this->checkAuth( $this->token, $this->userRepository, $tournament );

            $dateTime = $tournamentSer->getCompetition()->getStartDateTime();
            $name = $tournamentSer->getCompetition()->getLeague()->getName();

            $tournament = $this->service->changeBasics( $tournament, $dateTime, $name );

            return $response
                ->withStatus(200)
                ->withHeader('Content-Type', 'application/json;charset=utf-8')
                ->write($this->serializer->serialize( $tournament, 'json'));
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
            /** @var \FCToernooi\Tournament $tournament */
            $tournament = $this->repos->find( $args['id'] );

            if ( $tournament === null ){
                return $response->withStatus(404)->write("het te verwijderen toernooi kon niet gevonden worden" );
            }

            $user = $this->checkAuth( $this->token, $this->userRepository, $tournament );

            $this->service->remove( $tournament );

            return $response->withStatus(200);
        }
        catch( \Exception $e ){
            $errorMessage = $e->getMessage();
        }
        return $response->withStatus(404)->write('het toernooi is niet verwijdered : ' . $errorMessage );
    }

    public function fetchPdf($request, $response, $args)
    {
        $sErrorMessage = null;
        try {
            $tournament = $this->repos->find((int)$args['id']);
            if (!$tournament) {
                throw new \Exception("geen toernooi met het opgegeven id gevonden", E_ERROR);
            }
            // verplaatsen naar ?
            $pdf = new \FCToernooi\Pdf\Document\ScheduledGames( $tournament, $this->structureService, $this->planningService );
            $vtData = $pdf->render();

            return $response
                ->withHeader('Cache-Control', 'must-revalidate')
                ->withHeader('Pragma', 'public')
                ->withHeader('Content-Disposition', 'inline; filename="wedstrijdbrieven.pdf";')
                ->withHeader('Content-Type', 'application/pdf;charset=utf-8')
                ->withHeader('Content-Length', strlen( $vtData ))
                ->write($vtData);
            ;
        }
        catch( \Exception $e ){
            $sErrorMessage = $e->getMessage();
        }
        return $response->withStatus(422)->write( $sErrorMessage);
    }

    /*
            protected function sentEmailActivation( $user )
            {
                $activatehash = hash ( "sha256", $user["email"] . $this->settings["auth"]["activationsecret"] );
                // echo $activatehash;

                $sMessage =
                    "<div style=\"font-size:20px;\">FC Toernooi</div>"."<br>".
                    "<br>".
                    "Hallo ".$user["name"].","."<br>"."<br>".
                    "Bedankt voor het registreren bij FC Toernooi.<br>"."<br>".
                    'Klik op <a href="'.$this->settings["www"]["url"].'activate?activationkey='.$activatehash.'&email='.rawurlencode( $user["email"] ).'">deze link</a> om je emailadres te bevestigen en je account te activeren.<br>'."<br>".
                    'Wensen, klachten of vragen kunt u met de <a href="https://github.com/thepercival/fctoernooi/issues">deze link</a> bewerkstellingen.<br>'."<br>".
                    "Veel plezier met het gebruiken van FC Toernooi<br>"."<br>".
                    "groeten van FC Toernooi"
                ;

                $mail = new \PHPMailer;
                $mail->isSMTP();
                $mail->Host = $this->settings["email"]["smtpserver"];
                $mail->setFrom( $this->settings["email"]["from"], $this->settings["email"]["fromname"] );
                $mail->addAddress( $user["email"] );
                $mail->addReplyTo( $this->settings["email"]["from"], $this->settings["email"]["fromname"] );
                $mail->isHTML(true);
                $mail->Subject = "FC Toernooi registratiegegevens";
                $mail->Body    = $sMessage;
                if(!$mail->send()) {
                    throw new \Exception("de activatie email kan niet worden verzonden");
                }
            }*/
}