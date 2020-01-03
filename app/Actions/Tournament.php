<?php
declare(strict_types=1);

namespace App\Actions;

use App\Export\Excel\Spreadsheet as FCToernooiSpreadsheet;
use App\Export\Pdf\Document as PdfDocument;
use App\Export\TournamentConfig;
use App\Response\ErrorResponse;
use FCToernooi\Role;
use FCToernooi\Role\Repository as RoleRepository;
use FCToernooi\Tournament as TournamentBase;
use FCToernooi\Tournament\Service as TournamentService;
use FCToernooi\Tournament\StructureOptions as TournamentStructureOptions;
use FCToernooi\User;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpBadRequestException;
use FCToernooi\Tournament\Repository as TournamentRepository;
use App\Exceptions\DomainRecordNotFoundException;
use Psr\Log\LoggerInterface;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\DeserializationContext;
use Voetbal\Planning\Service as PlanningService;
use Voetbal\Competitor\Service as CompetitorService;
use Voetbal\Round\Number\PlanningCreator as RoundNumberPlanningCreator;
use Voetbal\Structure\Service as StructureService;
use Voetbal\Structure\Repository as StructureRepository;
use JMS\Serializer\SerializerInterface;
use App\Copiers\TournamentCopier;
use App\Copiers\StructureCopier;
use Voetbal\Round\Number\PlanningCreator;

class Tournament extends Action
{
    /**
     * @var TournamentRepository
     */
    protected $tournamentRepos;
    /**
     * @var TournamentCopier
     */
    protected $tournamentCopier;
    /**
     * @var RoleRepository
     */
    protected $roleRepos;
    /**
     * @var StructureRepository
     */
    protected $structureRepos;
    /**
     * @var PlanningCreator
     */
    protected $planningCreator;

    public function __construct(
        LoggerInterface $logger,
        SerializerInterface $serializer,
        TournamentRepository $tournamentRepos,
        TournamentCopier $tournamentCopier,
        RoleRepository $roleRepos,
        StructureRepository $structureRepos,
        PlanningCreator $planningCreator )
    {
        parent::__construct($logger,$serializer);
        $this->tournamentRepos = $tournamentRepos;
        $this->tournamentCopier = $tournamentCopier;
        $this->roleRepos = $roleRepos;
        $this->structureRepos = $structureRepos;
        $this->planningCreator = $planningCreator;
    }

    public function fetchOnePublic( Request $request, Response $response, $args ): Response
    {
        return $this->fetchOneHelper($request, $response, $args );
    }

    public function fetchOne( Request $request, Response $response, $args ): Response
    {
        return $this->fetchOneHelper($request, $response, $args );
    }

    protected function fetchOneHelper( Request $request, Response $response, $args, User $user = null )
    {
        $user = $request->getAttribute( "user" );
        try {
            /** @var \FCToernooi\Tournament $tournament */
            $tournament = $request->getAttribute( "tournament" );
            $json = $this->serializer->serialize( $tournament, 'json', $this->getSerializationContext($tournament, $user) );
            return $this->respondWithJson($response, $json);
        }
        catch( \Exception $e ){
            return new ErrorResponse($e->getMessage(), 422);
        }
    }

    protected function getDeserializationContext( User $user = null ) {
        $serGroups = ['Default'];

        if ($user !== null ) {
            $serGroups[] = 'privacy';
        }
        return DeserializationContext::create()->setGroups($serGroups);
    }

    protected function getSerializationContext( TournamentBase $tournament, User $user = null ) {
        $serGroups = ['Default'];

        if ($user !== null && $tournament->hasRole($user, Role::ADMIN)) {
            $serGroups[] = 'privacy';
        }
        if ($user !== null && $tournament->hasRole($user, Role::ALL)) {
            $serGroups[] = 'roles';
        }
        return SerializationContext::create()->setGroups($serGroups);
    }

    public function getUserRefereeId( Request $request, Response $response, $args ): Response
    {
        try {
            /** @var \FCToernooi\Tournament $tournament */
            $tournament = $request->getAttribute("tournament");
            $user = $request->getAttribute("user");

            $refereeId = 0;
            if (strlen( $user->getEmailaddress() ) > 0 ) {
                $referee = $tournament->getReferee( $user->getEmailaddress() );
                if( $referee !== null ) {
                    $refereeId = $referee->getId();
                }
            }

            $json = $this->serializer->serialize( $refereeId, 'json' );
            return $this->respondWithJson($response, $json);
        }
        catch( \Exception $e ){
            return new ErrorResponse($e->getMessage(), 422);
        }
    }

    public function syncRefereeRoles( Request $request, Response $response, $args ): Response
    {
        try {
            /** @var \FCToernooi\Tournament $tournament */
            $tournament = $request->getAttribute("tournament");
            $roles = $this->roleRepos->syncRefereeRoles( $tournament );

            $json = $this->serializer->serialize( true, 'json' );
            return $this->respondWithJson($response, $json);
        }
        catch( \Exception $e ){
            return new ErrorResponse($e->getMessage(), 422);
        }
    }

    public function sendRequestOldStructure( Request $request, Response $response, $args ): Response
    {
        try {
            /** @var \FCToernooi\Tournament $tournament */
            $tournament = $request->getAttribute("tournament");

            $this->sendEmailOldStructure( $tournament );
            $json = $this->serializer->serialize( true, 'json' );
            return $this->respondWithJson($response, $json);
        }
        catch( \Exception $e ){
            return new ErrorResponse($e->getMessage(), 422);
        }
    }


    public function add( Request $request, Response $response, $args ): Response
    {
        try {
            $user = $request->getAttribute("user");

            $deserializationContext = $this->getDeserializationContext($user);
            $tournamentSer = $this->serializer->deserialize( $this->getRawData(), 'FCToernooi\Tournament', 'json', $deserializationContext);

            $tournament = $this->tournamentCopier->copy( $tournamentSer, $tournamentSer->getCompetition()->getStartDateTime(), $user );
            $this->tournamentRepos->customPersist($tournament, true);
            $serializationContext = $this->getSerializationContext($tournament, $user);
            $json = $this->serializer->serialize( $tournament, 'json', $serializationContext );
            return $this->respondWithJson($response, $json);
        }
        catch( \Exception $e ){
            return new ErrorResponse($e->getMessage(), 422);
        }
    }

    public function edit( Request $request, Response $response, $args ): Response
    {
        try {
            /** @var \FCToernooi\Tournament $tournamentSer */
            $tournamentSer = $this->serializer->deserialize( $this->getRawData() , 'FCToernooi\Tournament', 'json');
            /** @var \FCToernooi\Tournament $tournament */
            $tournament = $request->getAttribute("tournament");

            $user = $request->getAttribute("user");

            $dateTime = $tournamentSer->getCompetition()->getStartDateTime();
            $name = $tournamentSer->getCompetition()->getLeague()->getName();
            $tournamentService = new TournamentService();
            $tournament = $tournamentService->changeBasics( $tournament, $dateTime, $tournamentSer->getBreak() );
            $tournament->setPublic( $tournamentSer->getPublic() );
            $tournament->getCompetition()->getLeague()->setName($name);
            $this->tournamentRepos->customPersist($tournament, true);
            $serializationContext = $this->getSerializationContext($tournament, $user);

            $json = $this->serializer->serialize( $tournament, 'json', $serializationContext );
            return $this->respondWithJson($response, $json);
        }
        catch( \Exception $e ){
            return new ErrorResponse($e->getMessage(), 422);
        }
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param array $args
     * @return mixed
     */
    public function remove( Request $request, Response $response, $args ): Response
    {
        try{
            /** @var \FCToernooi\Tournament $tournament */
            $tournament = $request->getAttribute("tournament");
            $this->tournamentRepos->remove( $tournament );
            return $response->withStatus(200);
        }
        catch( \Exception $e ){
            return new ErrorResponse('het toernooi is niet verwijdered : ' . $e->getMessage(), 404);
        }
    }

    public function copy( Request $request, Response $response, $args ): Response
    {
        $em = $this->tournamentRepos->getEM();
        $conn = $em->getConnection();
        $conn->beginTransaction();
        try {
            /** @var \FCToernooi\Tournament $tournament */
            $tournament = $request->getAttribute("tournament");

            $user = $request->getAttribute("user");

            $copyData = $this->getFormData( $request );
            if( property_exists( $copyData, "startdatetime" ) === false ) {
                throw new \Exception( "er is geen nieuwe startdatum-tijd opgegeven");
            }

            $competition = $tournament->getCompetition();

//            if ( $this->structureRepos->hasStructure( $competition )  ) {
//                throw new \Exception("er kan voor deze competitie geen indeling worden aangemaakt, omdat deze al bestaan", E_ERROR);
//            }

            $startDateTime = \DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s.u\Z', $copyData->startdatetime );

            $newTournament = $this->tournamentCopier->copy( $tournament, $startDateTime, $user );
            $this->tournamentRepos->customPersist($newTournament, true);

            $structure = $this->structureRepos->getStructure( $competition );
            $competitorService = new CompetitorService();
            $newCompetitors = $competitorService->createCompetitorsFromRound( $structure->getRootRound(), $newTournament->getCompetition()->getLeague()->getAssociation() );
            foreach( $newCompetitors as $newCompetitor ) {
                $em->persist($newCompetitor);
            }

            // $structureService = new StructureService( new TournamentStructureOptions() );
            $structureCopier = new StructureCopier( $newTournament->getCompetition() );
            $newStructure = $structureCopier->copy( $structure );
            $this->structureRepos->add($newStructure);

            $this->planningCreator->create( $newStructure->getFirstRoundNumber(), $newTournament->getBreak() );

            $conn->commit();

            $json = $this->serializer->serialize( $newTournament->getId(), 'json' );
            return $this->respondWithJson($response, $json);
        }
        catch( \Exception $e ){
            $conn->rollBack();
            return new ErrorResponse( $e->getMessage(), 422);
        }
    }

    public function export( Request $request, Response $response, $args ): Response
    {
        try {
            /** @var \FCToernooi\Tournament $tournament */
            $tournament = $request->getAttribute("tournament");

            $queryParams = $request->getQueryParams();
            if( array_key_exists("type", $queryParams) === false ) {
                throw new \Exception("kies een type export(pdf/excel)", E_ERROR);
            }

            $type = filter_var($queryParams["type"], FILTER_SANITIZE_STRING);
            $type = $type === "pdf" ? TournamentBase::EXPORTED_PDF : TournamentBase::EXPORTED_EXCEL;
            $config = $this->getExportConfig( $queryParams );

            $tournament->setExported( $tournament->getExported() | $type );
            $this->tournamentRepos->save($tournament);

            if( $type === TournamentBase::EXPORTED_PDF ) {
                return $this->writePdf($response, $config, $tournament );
            } else {
                return $this->writeExcel($response, $config, $tournament );
            }
        }
        catch( \Exception $e ){
            return new ErrorResponse( $e->getMessage(), 422);
        }
    }

    protected function getExportConfig( array $queryParams ): TournamentConfig
    {
        $getParam = function( string $param ) use ( $queryParams ): bool {
            if (array_key_exists($param, $queryParams) && strlen($queryParams[$param]) > 0) {
                return filter_var($queryParams[$param], FILTER_VALIDATE_BOOLEAN);
            }
            return false;
        };
        $exportConfig = new TournamentConfig(
            $getParam("gamenotes"),
            $getParam("structure"),
            $getParam("rules"),
            $getParam("gamesperpoule"),
            $getParam("gamesperfield"),
            $getParam("planning"),
            $getParam("poulepivottables"),
            $getParam("qrcode")
        );
        if( $exportConfig->allOptionsOff() ) {
            throw new \Exception("kies minimaal 1 exportoptie", E_ERROR);
        }
        return $exportConfig;
    }

    protected function writePdf($response, $config, $tournament ){
        $fileName = $this->getFileName( $config);
        $structure = $this->structureRepos->getStructure( $tournament->getCompetition() );

        $pdf = new PdfDocument( $tournament, $structure, $config );
        $vtData = $pdf->render();

        $response->getBody()->write($vtData);
        return $response
            ->withHeader('Cache-Control', 'must-revalidate')
            ->withHeader('Pragma', 'public')
            ->withHeader('Content-Disposition', 'inline; filename="'.$fileName.'.pdf";')
            ->withHeader('Content-Type', 'application/pdf;charset=utf-8')
            ->withHeader('Content-Length', strlen( $vtData ));
    }

    protected function writeExcel($response, $config, $tournament ){
        $fileName = $this->getFileName( $config);
        $structure = $this->structureRepos->getStructure( $tournament->getCompetition() );

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
