<?php
/**
 * Created by PhpStorm.
 * User: coen
 * Date: 6-10-17
 * Time: 11:40
 */

namespace App\Action;

use Doctrine\ORM\EntityManager;
use Slim\Http\Request;
use Slim\Http\Response;
use JMS\Serializer\Serializer;
use FCToernooi\User\Repository as UserRepository;
use FCToernooi\Tournament\Service as TournamentService;
use FCToernooi\Tournament\Repository as TournamentRepository;
use FCToernooi\Role;
use Voetbal\League;
use Voetbal\Structure\Repository as StructureRepository;
use Voetbal\Structure\Service as StructureService;
use Voetbal\Planning\Service as PlanningService;
use Voetbal\Game\Repository as GameRepository;
use Voetbal\Competitor\Service as CompetitorService;
use FCToernooi\Tournament as TournamentBase;
use FCToernooi\User;
use FCToernooi\Token;
use JMS\Serializer\SerializationContext;
use App\Export\TournamentConfig;
use App\Export\Pdf\Document as PdfDocument;
use App\Export\Excel\Spreadsheet as FCToernooiSpreadsheet;

final class Tournament
{
    /**
     * @var Serializer
     */
    protected $serializer;
    /**
     * @var Token
     */
    protected $token;
    /**
     * @var EntityManager
     */
    protected $em;
    /**
     * @var TournamentService
     */
    private $service;
    /**
     * @var TournamentRepository
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
     * @var StructureRepository
     */
    private $structureReposistory;
    /**
     * @var GameRepository
     */
    private $gameRepository;
    /**
     * @var CompetitorService
     */
    private $competitorService;

    use AuthTrait;

    public function __construct(
        TournamentService $service,
        TournamentRepository $repos,
        UserRepository $userRepository,
        StructureService $structureService,
        StructureRepository $structureRepository,
        GameRepository $gameRepository,
        CompetitorService $competitorService,
        Serializer $serializer,
        Token $token,
        EntityManager $em
    )
    {
        $this->service = $service;
        $this->repos = $repos;
        $this->userRepository = $userRepository;
        $this->structureService = $structureService;
        $this->structureReposistory = $structureRepository;
        $this->gameRepository = $gameRepository;
        $this->competitorService = $competitorService;
        $this->serializer = $serializer;
        $this->token = $token;
        $this->em = $em;
    }

    public function fetchOnePublic($request, $response, $args)
    {
        return $this->fetchOneHelper($request, $response, $args, null);
    }

    protected function fetchOneHelper($request, $response, $args, $user)
    {
        $sErrorMessage = null;
        try {
            /** @var \FCToernooi\Tournament|null $tournament */
            $tournament = $this->repos->find($args['id']);
            if ($tournament === null) {
                throw new \Exception("geen toernooi met het opgegeven id gevonden", E_ERROR);
            }
            return $response
                ->withHeader('Content-Type', 'application/json;charset=utf-8')
                ->write($this->serializer->serialize( $tournament, 'json', $this->getSerializationContext($tournament, $user)));
            ;
        }
        catch( \Exception $e ){
            $sErrorMessage = $e->getMessage();
        }
        return $response->withStatus(422)->write( $sErrorMessage);
    }

    protected function getSerializationContext( TournamentBase $tournament, User $user = null ) {
        $serGroups = ['Default'];

        if ( $user === null && $this->token->isPopulated() ) {
            $user = $this->userRepository->find($this->token->getUserId());
        }
        if ($user !== null && $tournament->hasRole($user, Role::ADMIN)) {
            $serGroups[] = 'privacy';
        }
        if ($user !== null && $tournament->hasRole($user, Role::ALL)) {
            $serGroups[] = 'roles';
        }
        return SerializationContext::create()->setGroups($serGroups);
    }

    public function fetchOne($request, $response, $args)
    {
        $user = null;
        if ( $this->token->isPopulated() ) {
            $user = $this->userRepository->find($this->token->getUserId());
        }
        return $this->fetchOneHelper($request, $response, $args, $user);
    }

    public function getUserRefereeId($request, $response, $args)
    {
        $sErrorMessage = null;
        try {
            /** @var \FCToernooi\Tournament|null $tournament */
            $tournament = $this->repos->find($args['id']);
            if ($tournament === null) {
                throw new \Exception("geen toernooi met het opgegeven id gevonden", E_ERROR);
            }
            $user = $this->checkAuth( $this->token, $this->userRepository );

            $refereeId = 0;
            if (strlen( $user->getEmailaddress() ) > 0 ) {
                $referee = $this->service->getReferee( $tournament, $user->getEmailaddress() );
                if( $referee !== null ) {
                    $refereeId = $referee->getId();
                }
            }

            return $response
                ->withHeader('Content-Type', 'application/json;charset=utf-8')
                ->write($this->serializer->serialize( $refereeId, 'json'));
            ;
        }
        catch( \Exception $e ){
            $sErrorMessage = $e->getMessage();
        }
        return $response->withStatus(422)->write( $sErrorMessage);
    }

    public function syncRefereeRoles($request, $response, $args)
    {
        try {
            /** @var \FCToernooi\Tournament|null $tournament */
            $tournament = $this->repos->find($args['id']);
            if ($tournament === null) {
                throw new \Exception("geen toernooi met het opgegeven id gevonden", E_ERROR);
            }
            $this->checkAuth( $this->token, $this->userRepository );

            $roles = $this->service->syncRefereeRoles( $tournament );

            return $response
                ->withStatus(201)
                ->withHeader('Content-Type', 'application/json;charset=utf-8')
                ->write($this->serializer->serialize( true, 'json' ));
            ;
        }
        catch( \Exception $e ){
            return $response->withStatus(422 )->write( $e->getMessage() );
        }
    }

    public function sendRequestOldStructure($request, $response, $args)
    {
        try {
            /** @var \FCToernooi\Tournament|null $tournament */
            $tournament = $this->repos->find($args['id']);
            if ($tournament === null) {
                throw new \Exception("geen toernooi met het opgegeven id gevonden", E_ERROR);
            }
            $this->sendEmailOldStructure( $tournament );
            return $response
                ->withStatus(201)
                ->withHeader('Content-Type', 'application/json;charset=utf-8')
                ->write($this->serializer->serialize( true, 'json' ));
            ;
        }
        catch( \Exception $e ){
            return $response->withStatus(422 )->write( $e->getMessage() );
        }
    }


    public function add( $request, $response, $args)
    {
        try {
            /** @var \FCToernooi\Tournament $tournament */
            $tournamentSer = $this->serializer->deserialize( json_encode($request->getParsedBody()), 'FCToernooi\Tournament', 'json');

            $user = $this->checkAuth( $this->token, $this->userRepository );
            $tournament = $this->service->createFromSerialized( $tournamentSer, $user );
            $this->repos->customPersist($tournament, true);
            $serializationContext = $this->getSerializationContext($tournament, $user);
            return $response
                ->withStatus(201)
                ->withHeader('Content-Type', 'application/json;charset=utf-8')
                ->write($this->serializer->serialize( $tournament, 'json', $serializationContext));
            ;
        }
        catch( \Exception $e ){
            return $response->withStatus(422 )->write( urldecode($e->getMessage()) );
        }
    }

    public function edit( $request, $response, $args)
    {
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
            $tournament = $this->service->changeBasics( $tournament, $dateTime, $tournamentSer->getBreakStartDateTime() );
            $tournament->setPublic( $tournamentSer->getPublic() );
            $tournament->getCompetition()->getLeague()->setName($name);
            $this->repos->customPersist($tournament, true);
            $serializationContext = $this->getSerializationContext($tournament, $user);

            return $response
                ->withStatus(200)
                ->withHeader('Content-Type', 'application/json;charset=utf-8')
                ->write($this->serializer->serialize( $tournament, 'json', $serializationContext ));
            ;
        }
        catch( \Exception $e ){
            return $response->withStatus(422)->write( $e->getMessage() );
        }
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param array $args
     * @return mixed
     */
    public function remove( Request $request, Response $response, array $args)
    {
        $errorMessage = null;
        try{
            /** @var \FCToernooi\Tournament|null $tournament */
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

    /**
     * echt nieuwe aanmaken via service
     * bestaande toernooi deserialising en dan weer opslaan
     *
     * @param Request $request
     * @param Response $response
     * @param array $args
     * @return mixed
     */
    public function copy(Request $request, Response $response, array $args)
    {
        $this->em->getConnection()->beginTransaction();
        try {
            /** @var \FCToernooi\Tournament|null $tournament */
            $tournament = $this->repos->find($args['id']);
            if ($tournament === null) {
                throw new \Exception("geen toernooi met het opgegeven id gevonden", E_ERROR);
            }
            /** @var \FCToernooi\User $user */
            $user = $this->checkAuth( $this->token, $this->userRepository, $tournament );

            $startDateTime = \DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s.u\Z', $request->getParam('startdatetime'));
            $newTournament = $this->service->copy( $tournament, $user, $startDateTime);
            $this->repos->customPersist($newTournament, true);

            $structure = $this->structureReposistory->getStructure( $tournament->getCompetition() );
            $newCompetitors = $this->competitorService->createCompetitorsFromRound( $structure->getRootRound(), $newTournament->getCompetition()->getLeague()->getAssociation() );
            foreach( $newCompetitors as $newCompetitor ) {
                $this->em->persist($newCompetitor);
            }

            $newStructure = $this->structureService->copy( $structure, $newTournament->getCompetition() );

            $this->competitorService->assignCompetitors( $newStructure, $newCompetitors );

            $this->structureReposistory->customPersist($newStructure);

            $planningService = new PlanningService();
            $games = $planningService->createGames( $newStructure->getFirstRoundNumber()->getPlanningInput()->getBestPlanning() );
            foreach( $games as $game ) {
                $this->em->persist($game);
            }
            $this->em->flush();

            $this->em->getConnection()->commit();
            return $response
                ->withHeader('Content-Type', 'application/json;charset=utf-8')
                ->write($this->serializer->serialize( $newTournament->getId(), 'json'))
                ;
            ;
        }
        catch( \Exception $e ){
            $this->em->getConnection()->rollBack();
            return $response->withStatus(422)->write( $e->getMessage());
        }
    }

    public function export($request, $response, $args)
    {
        try {
            $tournament = $this->repos->find((int)$args['id']);
            if (!$tournament) {
                throw new \Exception("geen toernooi met het opgegeven id gevonden", E_ERROR);
            }

            $type = filter_var($request->getParam("type"), FILTER_SANITIZE_STRING);
            $type = $type === "pdf" ? TournamentBase::EXPORTED_PDF : TournamentBase::EXPORTED_EXCEL;
            $config = $this->getExportConfig( $request );

            $tournament->setExported( $tournament->getExported() | $type );
            $this->em->persist($tournament);
            $this->em->flush();

            if( $type === TournamentBase::EXPORTED_PDF ) {
                return $this->writePdf($response, $config, $tournament );
            } else {
                return $this->writeExcel($response, $config, $tournament );
            }
        }
        catch( \Exception $e ){
            return $response->withStatus(422)->write($e->getMessage());
        }
    }

    protected function getExportConfig( $request ): TournamentConfig
    {
        $exportConfig = new TournamentConfig(
            filter_var($request->getParam("gamenotes"), FILTER_VALIDATE_BOOLEAN),
            filter_var($request->getParam("structure"), FILTER_VALIDATE_BOOLEAN),
            filter_var($request->getParam("rules"), FILTER_VALIDATE_BOOLEAN),
            filter_var($request->getParam("gamesperpoule"), FILTER_VALIDATE_BOOLEAN),
            filter_var($request->getParam("gamesperfield"), FILTER_VALIDATE_BOOLEAN),
            filter_var($request->getParam("planning"), FILTER_VALIDATE_BOOLEAN),
            filter_var($request->getParam("poulepivottables"), FILTER_VALIDATE_BOOLEAN),
            filter_var($request->getParam("qrcode"), FILTER_VALIDATE_BOOLEAN)
        );
        if( $exportConfig->allOptionsOff() ) {
            throw new \Exception("kies minimaal 1 exportoptie", E_ERROR);
        }
        return $exportConfig;
    }

    protected function writePdf($response, $config, $tournament ){
        $fileName = $this->getFileName( $config);
        $structure = $this->structureReposistory->getStructure( $tournament->getCompetition() );

        $pdf = new PdfDocument( $tournament, $structure, $config );
        $vtData = $pdf->render();

        return $response
            ->withHeader('Cache-Control', 'must-revalidate')
            ->withHeader('Pragma', 'public')
            ->withHeader('Content-Disposition', 'inline; filename="'.$fileName.'.pdf";')
            ->withHeader('Content-Type', 'application/pdf;charset=utf-8')
            ->withHeader('Content-Length', strlen( $vtData ))
            ->write($vtData);
    }

    protected function writeExcel($response, $config, $tournament ){
        $fileName = $this->getFileName( $config);
        $structure = $this->structureReposistory->getStructure( $tournament->getCompetition() );

        $spreadsheet = new FCToernooiSpreadsheet( $tournament, $structure, $config );
        $spreadsheet->fillContents();

        $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');

        return $response
            ->withHeader('Cache-Control', 'must-revalidate')
            ->withHeader('Pragma', 'public')
            ->withHeader('Content-Disposition', 'attachment; filename="'.$fileName.'.xlsx";')
            ->withHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet;charset=utf-8')
            ->write($writer->save('php://output'));
    }

    protected function getFileName(TournamentConfig $exportConfig): string {
        if( $exportConfig->hasOnly(TournamentConfig::GAMENOTES) ) {
            return "wedstrijdbrieven";
        } elseif( $exportConfig->hasOnly(TournamentConfig::STRUCTURE) ) {
            return "structuur-en-indeling";
        } elseif( $exportConfig->hasOnly(TournamentConfig::RULES) ) {
            return "reglementen";
        } elseif( $exportConfig->hasOnly(TournamentConfig::GAMESPERPOULE) ) {
            return "wedstrijden-per-poule";
        } elseif( $exportConfig->hasOnly(TournamentConfig::GAMESPERFIELD) ) {
            return "wedstrijden-per-veld";
        } elseif( $exportConfig->hasOnly(TournamentConfig::PLANNING) ) {
            return "wedstrijdschema";
        } elseif( $exportConfig->hasOnly(TournamentConfig::PIVOTTABLES) ) {
            return "poule-draaitabellen";
        } elseif( $exportConfig->hasOnly(TournamentConfig::QRCODE) ) {
            return "qrcode-en-link";
        }
        return "toernooi";
    }

    protected function sendEmailOldStructure( TournamentBase $tournament )
    {
        $subject = 'omzetten structuur fctoernooi';
        $body = '
            <p>https://www.fctoernooi.nl/toernooi/structure/'.$tournament->getId().'</p>
            <p>
            met vriendelijke groet,
            <br>
            FCToernooi
            </p>';

        $from = "FCToernooi";
        $fromEmail = "noreply@fctoernooi.nl";
        $headers  = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8" . "\r\n";
        $headers .= "From: ".$from." <" . $fromEmail . ">" . "\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion();
        $params = "-r ".$fromEmail;

        if ( !mail( "fctoernooi2018@gmail.com", $subject, $body, $headers, $params) ) {
            // $app->flash("error", "We're having trouble with our mail servers at the moment.  Please try again later, or contact us directly by phone.");
            error_log('Mailer Error!' );
            // $app->halt(500);
        }
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