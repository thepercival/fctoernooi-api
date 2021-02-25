<?php

declare(strict_types=1);

namespace App\Actions;

use App\Export\Excel\Spreadsheet as FCToernooiSpreadsheet;
use App\Export\Pdf\Document as PdfDocument;
use App\Export\TournamentConfig;
use App\QueueService;
use App\Response\ErrorResponse;
use App\TmpService;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use FCToernooi\Role;
use FCToernooi\Tournament;
use FCToernooi\Tournament\Service as TournamentService;
use FCToernooi\LockerRoom;
use FCToernooi\TournamentUser;
use FCToernooi\User;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use FCToernooi\Tournament\Repository as TournamentRepository;
use App\Mailer;
use GuzzleHttp\Psr7\LazyOpenStream;
use Psr\Log\LoggerInterface;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\DeserializationContext;
use stdClass;
use Sports\Structure\Repository as StructureRepository;
use FCToernooi\LockerRoom\Repository as LockerRoomRepistory;
use JMS\Serializer\SerializerInterface;
use App\Copiers\TournamentCopier;
use Sports\Structure\Copier as StructureCopier;
use Sports\Round\Number\PlanningCreator;
use Selective\Config\Configuration;
use Sports\Structure\Validator as StructureValidator;

class TournamentAction extends Action
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
     * @var StructureRepository
     */
    protected $structureRepos;
    /**
     * @var LockerRoomRepistory
     */
    protected $lockerRoomRepos;
    /**
     * @var PlanningCreator
     */
    protected $planningCreator;
    /**
     * @var Mailer
     */
    protected $mailer;
    /**
     * @var Configuration
     */
    protected $config;

    public function __construct(
        LoggerInterface $logger,
        SerializerInterface $serializer,
        TournamentRepository $tournamentRepos,
        TournamentCopier $tournamentCopier,
        StructureRepository $structureRepos,
        LockerRoomRepistory $lockerRoomRepos,
        PlanningCreator $planningCreator,
        Mailer $mailer,
        Configuration $config
    ) {
        parent::__construct($logger, $serializer);
        $this->tournamentRepos = $tournamentRepos;
        $this->tournamentCopier = $tournamentCopier;
        $this->structureRepos = $structureRepos;
        $this->lockerRoomRepos = $lockerRoomRepos;
        $this->planningCreator = $planningCreator;
        $this->mailer = $mailer;
        $this->config = $config;
    }

    public function fetchOne(Request $request, Response $response, $args): Response
    {
        return $this->fetchOneHelper($request, $response, $args);
    }

    protected function fetchOneHelper(Request $request, Response $response, $args, User $user = null)
    {
        $user = $request->getAttribute("user");
        try {
            /** @var Tournament $tournament */
            $tournament = $request->getAttribute("tournament");
            $json = $this->serializer->serialize(
                $tournament,
                'json',
                $this->getSerializationContext($tournament, $user)
            );
            return $this->respondWithJson($response, $json);
        } catch (\Exception $exception) {
            return new ErrorResponse($exception->getMessage(), 400);
        }
    }

    protected function getDeserializationContext(User $user = null)
    {
        $serGroups = ['Default','noReference'];

        if ($user !== null) {
            $serGroups[] = 'privacy';
        }
        return DeserializationContext::create()->setGroups($serGroups);
    }

    protected function getSerializationContext(Tournament $tournament, User $user = null)
    {
        $serGroups = ['Default','noReference'];
        if ($user !== null) {
            $tournamentUser = $tournament->getUser($user);
            if ($tournamentUser !== null) {
                $serGroups[] = 'users';
                if ($tournamentUser->hasRoles(Role::ADMIN)) {
                    $serGroups[] = 'privacy';
                }
                if ($tournamentUser->hasRoles(Role::ROLEADMIN)) {
                    $serGroups[] = 'roleadmin';
                }
            }
        }
        return SerializationContext::create()->setGroups($serGroups);
    }

    public function getUserRefereeId(Request $request, Response $response, $args): Response
    {
        try {
            /** @var Tournament $tournament */
            $tournament = $request->getAttribute("tournament");
            $user = $request->getAttribute("user");

            $refereeId = 0;
            if (strlen($user->getEmailaddress()) > 0) {
                $referee = $tournament->getReferee($user->getEmailaddress());
                if ($referee !== null) {
                    $refereeId = $referee->getId();
                }
            }

            $json = $this->serializer->serialize($refereeId, 'json');
            return $this->respondWithJson($response, $json);
        } catch (\Exception $exception) {
            return new ErrorResponse($exception->getMessage(), 422);
        }
    }


    public function add(Request $request, Response $response, $args): Response
    {
        try {
            $user = $request->getAttribute("user");

            $deserializationContext = $this->getDeserializationContext($user);
            /** @var Tournament $tournamentSer */
            $tournamentSer = $this->serializer->deserialize(
                $this->getRawData(),
                Tournament::class,
                'json',
                $deserializationContext
            );

            $tournamentSer->setUsers(new ArrayCollection());
            $creator = new TournamentUser($tournamentSer, $user, Role::ADMIN + Role::GAMERESULTADMIN + Role::ROLEADMIN);
// var_dump($tournamentSer->getCompetition()); die();
            $tournament = $this->tournamentCopier->copy(
                $tournamentSer,
                $tournamentSer->getCompetition()->getStartDateTime(),
                $user
            );
            $tournament->setCreatedDateTime(new DateTimeImmutable());
            $this->tournamentRepos->customPersist($tournament, true);
            $serializationContext = $this->getSerializationContext($tournament, $user);
            $json = $this->serializer->serialize($tournament, 'json', $serializationContext);
            return $this->respondWithJson($response, $json);
        } catch (\Exception $exception) {
            return new ErrorResponse($exception->getMessage(), 422);
        }
    }

    public function edit(Request $request, Response $response, $args): Response
    {
        try {
            /** @var Tournament $tournamentSer */
            $tournamentSer = $this->serializer->deserialize($this->getRawData(), Tournament::class, 'json');
            /** @var Tournament $tournament */
            $tournament = $request->getAttribute("tournament");

            $user = $request->getAttribute("user");

            $dateTime = $tournamentSer->getCompetition()->getStartDateTime();
            $ruleSet = $tournamentSer->getCompetition()->getRankingRuleSet();
            $name = $tournamentSer->getCompetition()->getLeague()->getName();
            $tournamentService = new TournamentService();
            $tournament = $tournamentService->changeBasics(
                $tournament,
                $dateTime,
                $ruleSet,
                $tournamentSer->getBreak()
            );
            $tournament->setPublic($tournamentSer->getPublic());
            $tournament->getCompetition()->getLeague()->setName($name);
            $this->tournamentRepos->customPersist($tournament, true);
            $serializationContext = $this->getSerializationContext($tournament, $user);

            $json = $this->serializer->serialize($tournament, 'json', $serializationContext);
            return $this->respondWithJson($response, $json);
        } catch (\Exception $exception) {
            return new ErrorResponse($exception->getMessage(), 422);
        }
    }

    public function remove(Request $request, Response $response, $args): Response
    {
        try {
            /** @var Tournament $tournament */
            $tournament = $request->getAttribute("tournament");

            $this->tournamentRepos->remove($tournament);

            return $response->withStatus(200);
        } catch (\Exception $exception) {
            return new ErrorResponse('het toernooi is niet verwijdered : ' . $exception->getMessage(), 404);
        }
    }

    public function copy(Request $request, Response $response, $args): Response
    {
        $em = $this->tournamentRepos->getEM();
        $conn = $em->getConnection();
        $conn->beginTransaction();
        try {
            /** @var Tournament $tournament */
            $tournament = $request->getAttribute("tournament");

            $user = $request->getAttribute("user");

            /** @var stdClass $copyData */
            $copyData = $this->getFormData($request);
            if (property_exists($copyData, "startdatetime") === false) {
                throw new \Exception("er is geen nieuwe startdatum-tijd opgegeven");
            }

            $competition = $tournament->getCompetition();

//            if ( $this->structureRepos->hasStructure( $competition )  ) {
//                throw new \Exception("er kan voor deze competitie geen indeling worden aangemaakt, omdat deze al bestaan", E_ERROR);
//            }

            $startDateTime = DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s.u\Z', $copyData->startdatetime);

            $newTournament = $this->tournamentCopier->copy($tournament, $startDateTime, $user);
            $newTournament->setCreatedDateTime(new DateTimeImmutable());
            $this->tournamentRepos->customPersist($newTournament, true);

            $structure = $this->structureRepos->getStructure($competition);

            $structureCopier = new StructureCopier($newTournament->getCompetition() );
            $newStructure = $structureCopier->copy($structure);

            $structureValidator = new StructureValidator();
            $structureValidator->checkValidity($newTournament->getCompetition(), $newStructure);

            $this->structureRepos->add($newStructure);

            $this->planningCreator->addFrom(
                new QueueService($this->config->getArray('queue')),
                $newStructure->getFirstRoundNumber(),
                $newTournament->getBreak()
            );

            $conn->commit();

            $json = $this->serializer->serialize($newTournament->getId(), 'json');
            return $this->respondWithJson($response, $json);
        } catch (\Exception $exception) {
            $conn->rollBack();
            return new ErrorResponse($exception->getMessage(), 422);
        }
    }

    public function exportGenerateHash(Request $request, Response $response, $args): Response
    {
        try {
            /** @var \FCToernooi\Tournament $tournament */
            $tournament = $request->getAttribute("tournament");

            $hash = $this->getExportHash($tournament->getId());
            return $this->respondWithJson($response, json_encode(["hash" => $hash]));
        } catch (\Exception $exception) {
            return new ErrorResponse($exception->getMessage(), 422);
        }
    }

    protected function getExportHash(int $tournamentId): string
    {
        $decoded = $tournamentId . getenv('EXPORT_SECRET') . $tournamentId;
        return hash("sha1", $decoded);
    }

    public function export(Request $request, Response $response, $args): Response
    {
        try {
            /** @var Tournament $tournament */
            $tournament = $request->getAttribute("tournament");

            $queryParams = $request->getQueryParams();
            if (array_key_exists("hash", $queryParams) === false) {
                throw new \Exception("de link om het toernooi te exporteren is niet correct", E_ERROR);
            }
            if ($queryParams["hash"] !== $this->getExportHash($tournament->getId())) {
                throw new \Exception("de link om het toernooi te exporteren is niet correct", E_ERROR);
            }

            $queryParams = $request->getQueryParams();
            if (array_key_exists("type", $queryParams) === false) {
                throw new \Exception("kies een type export(pdf/excel)", E_ERROR);
            }

            $type = filter_var($queryParams["type"], FILTER_SANITIZE_STRING);
            $type = $type === "pdf" ? Tournament::EXPORTED_PDF : Tournament::EXPORTED_EXCEL;
            $config = $this->getExportConfig($queryParams);

            $tournament->setExported($tournament->getExported() | $type);
            $this->tournamentRepos->save($tournament);

            if ($type === Tournament::EXPORTED_PDF) {
                return $this->writePdf($response, $config, $tournament, $this->config->getString("www.wwwurl"));
            } else {
                return $this->writeExcel($response, $config, $tournament, $this->config->getString("www.wwwurl"));
            }
        } catch (\Exception $exception) {
            return new ErrorResponse($exception->getMessage(), 422);
        }
    }

    protected function getExportConfig(array $queryParams): TournamentConfig
    {
        $getParam = function (string $param) use ($queryParams): bool {
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
            $getParam("qrcode"),
            $getParam("lockerrooms")
        );
        if ($exportConfig->allOptionsOff()) {
            throw new \Exception("kies minimaal 1 exportoptie", E_ERROR);
        }
        return $exportConfig;
    }

    protected function writePdf(Response $response, TournamentConfig $config, Tournament $tournament, string $url)
    {
        $fileName = $this->getFileName($config);
        $structure = $this->structureRepos->getStructure($tournament->getCompetition());

        $pdf = new PdfDocument($tournament, $structure, $config, $url);
        $vtData = $pdf->render();

        $response->getBody()->write($vtData);
        return $response
            ->withHeader('Cache-Control', 'must-revalidate')
            ->withHeader('Pragma', 'public')
            ->withHeader('Content-Disposition', 'inline; filename="' . $fileName . '.pdf";')
            ->withHeader(
                'Content-Type',
                'application/pdf;charset=utf-8'
            )
            ->withHeader('Content-Length', '' . strlen($vtData));
    }

    protected function writeExcel(Response $response, TournamentConfig $config, Tournament $tournament, string $url)
    {
        $fileName = $this->getFileName($config);
        $structure = $this->structureRepos->getStructure($tournament->getCompetition());

        $spreadsheet = new FCToernooiSpreadsheet($tournament, $structure, $config, $url);
        $spreadsheet->fillContents();

        $excelWriter = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');

        $tmpService = new TmpService();
        $excelFileName = $tmpService->getPath(['worksheets'], 'toernooi-' . $tournament->getId() . '.xlsx');
        $excelWriter->save($excelFileName);

        // For Excel2007 and above .xlsx files
        $response = $response->withHeader('Cache-Control', 'must-revalidate');
        $response = $response->withHeader('Pragma', 'public');
        $response = $response->withHeader('Content-Disposition', 'attachment; filename="' . $fileName . '.xlsx";');
        $response = $response->withHeader(
            'Content-Type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet;charset=utf-8'
        );

        $newStream = new LazyOpenStream($excelFileName, 'r');
        return $response->withBody($newStream);
    }

    protected function getFileName(TournamentConfig $exportConfig): string
    {
        if ($exportConfig->hasOnly(TournamentConfig::GAMENOTES)) {
            return "wedstrijdbrieven";
        } elseif ($exportConfig->hasOnly(TournamentConfig::STRUCTURE)) {
            return "opzet-en-indeling";
        } elseif ($exportConfig->hasOnly(TournamentConfig::RULES)) {
            return "reglementen";
        } elseif ($exportConfig->hasOnly(TournamentConfig::GAMESPERPOULE)) {
            return "wedstrijden-per-poule";
        } elseif ($exportConfig->hasOnly(TournamentConfig::GAMESPERFIELD)) {
            return "wedstrijden-per-veld";
        } elseif ($exportConfig->hasOnly(TournamentConfig::PLANNING)) {
            return "wedstrijdplanning";
        } elseif ($exportConfig->hasOnly(TournamentConfig::PIVOTTABLES)) {
            return "poule-draaitabellen";
        } elseif ($exportConfig->hasOnly(TournamentConfig::QRCODE)) {
            return "qrcode-en-link";
        }
        return "toernooi";
    }


    /*
    protected function sentEmailActivation( $user )
    {
        $activatehash = hash ( "sha256", $user["email"] . $this->settings["auth"]["activationsecret"] );
        // echo $activatehash;

        $body =
            "<div style=\"font-size:20px;\">FC Toernooi</div>"."<br>".
            "<br>".
            "Hallo ".$user["name"].","."<br>"."<br>".
            "Bedankt voor het registreren bij FC Toernooi.<br>"."<br>".
            'Klik op <a href="'.$this->settings["www"]["url"].'activate?activationkey='.$activatehash.'&email='.rawurlencode( $user["email"] ).'">deze link</a> om je emailadres te bevestigen en je account te activeren.<br>'."<br>".
            'Wensen, klachten of vragen kunt u met de <a href="https://github.com/thepercival/fctoernooi/issues">deze link</a> bewerkstellingen.<br>'."<br>".
            "Veel plezier met het gebruiken van FC Toernooi<br>"."<br>".
            "groeten van FC Toernooi"
        ;
        $mail->addReplyTo( $this->settings["email"]["from"], $this->settings["email"]["fromname"] );
        $subject = "FC Toernooi registratiegegevens";
        this->mailer->send( $subject, $body, $user["email"] );
    }*/
}
