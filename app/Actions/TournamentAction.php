<?php

declare(strict_types=1);

namespace App\Actions;

use App\Copiers\TournamentCopier;
use App\Export\Pdf\Document as PdfDocument;
use App\QueueService;
use App\Response\ErrorResponse;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use FCToernooi\Role;
use FCToernooi\Tournament;
use FCToernooi\Tournament\ExportConfig;
use FCToernooi\Tournament\Repository as TournamentRepository;
use FCToernooi\TournamentUser;
use FCToernooi\User;
use JMS\Serializer\DeserializationContext;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\SerializerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Selective\Config\Configuration;
use Sports\Competition\Service as CompetitionService;
use Sports\Competition\Validator as CompetitionValidator;
use Sports\Round\Number\PlanningCreator;
use Sports\Structure\Copier as StructureCopier;
use Sports\Structure\Repository as StructureRepository;
use Sports\Structure\Validator as StructureValidator;
use stdClass;

final class TournamentAction extends Action
{
    protected string $exportSecret;

    public function __construct(
        LoggerInterface $logger,
        SerializerInterface $serializer,
        private TournamentRepository $tournamentRepos,
        private TournamentCopier $tournamentCopier,
        private StructureCopier $structureCopier,
        private StructureRepository $structureRepos,
        private EntityManagerInterface $entityManager,
        private PlanningCreator $planningCreator,
        private Configuration $config
    ) {
        parent::__construct($logger, $serializer);
        $this->exportSecret = $config->getString('renderer.export_secret');
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param array<string, int|string> $args
     * @return Response
     */
    public function fetchOne(Request $request, Response $response, array $args): Response
    {
        return $this->fetchOneHelper($request, $response, $args);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param array<string, int|string> $args
     * @param User|null $user
     * @return Response
     */
    public function fetchOneHelper(Request $request, Response $response, array $args, User $user = null): Response
    {
        /** @var User|null $user */
        $user = $request->getAttribute('user');
        try {
            /** @var Tournament $tournament */
            $tournament = $request->getAttribute('tournament');
//            $r = \Doctrine\DBAL\Types\Type::getTypesMap();
//            $x = $tournament->getCompetition()->getStartDateTime();
            $json = $this->serializer->serialize(
                $tournament,
                'json',
                $this->getSerializationContext($tournament, $user)
            );
            return $this->respondWithJson($response, $json);
        } catch (Exception $exception) {
            return new ErrorResponse($exception->getMessage(), 400);
        }
    }

    protected function getDeserializationContext(User $user = null): DeserializationContext
    {
        $serGroups = ['Default','noReference'];

        if ($user !== null) {
            $serGroups[] = 'privacy';
        }
        return DeserializationContext::create()->setGroups($serGroups);
    }

    protected function getSerializationContext(Tournament $tournament, User $user = null): SerializationContext
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

    /**
     * @param Request $request
     * @param Response $response
     * @param array<string, int|string> $args
     * @return Response
     */
    public function getUserRefereeId(Request $request, Response $response, array $args): Response
    {
        try {
            /** @var Tournament $tournament */
            $tournament = $request->getAttribute('tournament');
            /** @var User $user */
            $user = $request->getAttribute('user');

            $refereeId = 0;
            if (strlen($user->getEmailaddress()) > 0) {
                $referee = $tournament->getReferee($user->getEmailaddress());
                if ($referee !== null) {
                    $refereeId = $referee->getId();
                }
            }

            $json = $this->serializer->serialize($refereeId, 'json');
            return $this->respondWithJson($response, $json);
        } catch (Exception $exception) {
            return new ErrorResponse($exception->getMessage(), 422);
        }
    }


    /**
     * @param Request $request
     * @param Response $response
     * @param array<string, int|string> $args
     * @return Response
     */
    public function add(Request $request, Response $response, array $args): Response
    {
        try {
            /** @var User $user */
            $user = $request->getAttribute('user');

            $deserializationContext = $this->getDeserializationContext($user);
            /** @var Tournament $tournamentSer */
            $tournamentSer = $this->serializer->deserialize(
                $this->getRawData($request),
                Tournament::class,
                'json',
                $deserializationContext
            );

            // $tournamentSer->setUsers(new ArrayCollection());
            $creator = new TournamentUser($tournamentSer, $user, Role::ADMIN + Role::GAMERESULTADMIN + Role::ROLEADMIN);
            // var_dump($tournamentSer->getCompetition()); die();
            $tournament = $this->tournamentCopier->copy(
                $tournamentSer,
                $tournamentSer->getCompetition()->getStartDateTime(),
                $user
            );
            if ($tournament->getUsers()->count() === 0) {
                throw new \Exception('er zijn geen gebruikers gevonden voor het nieuwe toernooi', E_ERROR);
            }
            $this->tournamentRepos->customPersist($tournament, true);
            $serializationContext = $this->getSerializationContext($tournament, $user);
            $json = $this->serializer->serialize($tournament, 'json', $serializationContext);
            return $this->respondWithJson($response, $json);
        } catch (Exception $exception) {
            return new ErrorResponse($exception->getMessage(), 422);
        }
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param array<string, int|string> $args
     * @return Response
     */
    public function edit(Request $request, Response $response, array $args): Response
    {
        try {
            /** @var Tournament $tournamentSer */
            $tournamentSer = $this->serializer->deserialize($this->getRawData($request), Tournament::class, 'json');
            /** @var Tournament $tournament */
            $tournament = $request->getAttribute('tournament');
            /** @var User $user */
            $user = $request->getAttribute('user');

            $dateTime = $tournamentSer->getCompetition()->getStartDateTime();
            $ruleSet = $tournamentSer->getCompetition()->getAgainstRuleSet();
            $name = $tournamentSer->getCompetition()->getLeague()->getName();

            $competitionService = new CompetitionService();
            $competition = $tournament->getCompetition();
            $competitionService->changeStartDateTime($competition, $dateTime);
            $competition->setAgainstRuleSet($ruleSet);
            $tournament->setBreak($tournamentSer->getBreak());
            $tournament->setPublic($tournamentSer->getPublic());
            $tournament->getCompetition()->getLeague()->setName($name);
            $this->tournamentRepos->customPersist($tournament, true);
            $serializationContext = $this->getSerializationContext($tournament, $user);

            $json = $this->serializer->serialize($tournament, 'json', $serializationContext);
            return $this->respondWithJson($response, $json);
        } catch (Exception $exception) {
            return new ErrorResponse($exception->getMessage(), 422);
        }
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param array<string, int|string> $args
     * @return Response
     */
    public function remove(Request $request, Response $response, array $args): Response
    {
        try {
            /** @var Tournament $tournament */
            $tournament = $request->getAttribute('tournament');

            $this->tournamentRepos->remove($tournament);

            return $response->withStatus(200);
        } catch (Exception $exception) {
            return new ErrorResponse('het toernooi is niet verwijdered : ' . $exception->getMessage(), 404);
        }
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param array<string, int|string> $args
     * @return Response
     */
    public function copy(Request $request, Response $response, array $args): Response
    {
        $conn = $this->entityManager->getConnection();
        $conn->beginTransaction();
        try {
            /** @var Tournament $tournament */
            $tournament = $request->getAttribute('tournament');
            /** @var User $user */
            $user = $request->getAttribute('user');

            /** @var stdClass $copyData */
            $copyData = $this->getFormData($request);
            if (property_exists($copyData, 'startdatetime') === false) {
                throw new Exception('er is geen nieuwe startdatum-tijd opgegeven', E_ERROR);
            }

            $competition = $tournament->getCompetition();

//            if ( $this->structureRepos->hasStructure( $competition )  ) {
//                throw new \Exception("er kan voor deze competitie geen indeling worden aangemaakt, omdat deze al bestaan", E_ERROR);
//            }

            $startDateTime = DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s.u\Z', $copyData->startdatetime);
            if ($startDateTime === false) {
                throw new Exception('no input for startdatetime', E_ERROR);
            }

            $newTournament = $this->tournamentCopier->copy($tournament, $startDateTime, $user);
            $this->tournamentRepos->customPersist($newTournament, true);

            $structure = $this->structureRepos->getStructure($competition);

            $newStructure = $this->structureCopier->copy($structure, $newTournament->getCompetition());

            $competitionValidator = new CompetitionValidator();
            $competitionValidator->checkValidity($newTournament->getCompetition());

            $structureValidator = new StructureValidator();
            $structureValidator->checkValidity($newTournament->getCompetition(), $newStructure, $newTournament->getPlaceRanges());

            $this->structureRepos->add($newStructure);

            $this->planningCreator->addFrom(
                new QueueService($this->config->getArray('queue')),
                $newStructure->getFirstRoundNumber(),
                $newTournament->getBreak(),
                QueueService::MAX_PRIORITY
            );

            $conn->commit();

            $json = $this->serializer->serialize($newTournament->getId(), 'json');
            return $this->respondWithJson($response, $json);
        } catch (Exception $exception) {
            $conn->rollBack();
            return new ErrorResponse($exception->getMessage(), 422);
        }
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param array<string, int|string> $args
     * @return Response
     */
    public function exportGenerateHash(Request $request, Response $response, array $args): Response
    {
        try {
            /** @var Tournament $tournament */
            $tournament = $request->getAttribute('tournament');

            $hash = $this->getExportHash((int)$tournament->getId());
            $json = json_encode(['hash' => $hash]);
            return $this->respondWithJson($response, $json === false ? '' : $json);
        } catch (Exception $exception) {
            return new ErrorResponse($exception->getMessage(), 422);
        }
    }

    protected function getExportHash(int $tournamentId): string
    {
        $decoded = $tournamentId . $this->exportSecret . $tournamentId;
        return hash('sha1', $decoded);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param array<string, int|string> $args
     * @return Response
     */
    public function export(Request $request, Response $response, array $args): Response
    {
        try {
            /** @var Tournament $tournament */
            $tournament = $request->getAttribute('tournament');

            $queryParams = $request->getQueryParams();
            if (!isset($queryParams['hash'])) {
                throw new Exception('de link om het toernooi te exporteren is niet correct', E_ERROR);
            }
            if ($queryParams['hash'] !== $this->getExportHash((int)$tournament->getId())) {
                throw new Exception('de link om het toernooi te exporteren is niet correct', E_ERROR);
            }

            $queryParams = $request->getQueryParams();
            if (!isset($queryParams['format'])) {
                throw new Exception('kies een  exportformaat(pdf/excel)', E_ERROR);
            }

            $format = (int)$queryParams['format'];

            $tournament->setExported($tournament->getExported() | $format);
            $this->tournamentRepos->save($tournament);

            //if ($format === ExportFormat::Pdf) {
            $subjects = $this->getExportSubjects($queryParams);
            return $this->writePdf($response, $subjects, $tournament, $this->config->getString('www.wwwurl'));
            // } /*else {
            //    return $this->writeExcel($response, $format, $tournament, $this->config->getString('www.wwwurl'));
            //}
        } catch (Exception $exception) {
            return new ErrorResponse($exception->getMessage(), 422);
        }
    }

    /**
     * @param array<string, int|string> $queryParams
     * @return int
     * @throws Exception
     */
    protected function getExportSubjects(array $queryParams): int
    {
        $subjects = 0;
        if (isset($queryParams['subjects'])) {
            $subjects = (int)$queryParams['subjects'];
        }
        if ($subjects === 0) {
            throw new Exception('kies minimaal 1 exportoptie', E_ERROR);
        }
        return $subjects;
    }

    protected function writePdf(Response $response, int $subjects, Tournament $tournament, string $url): Response
    {
        $fileName = $this->getFileName($subjects);
        $structure = $this->structureRepos->getStructure($tournament->getCompetition());

        $pdf = new PdfDocument($tournament, $structure, $subjects, $url);
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

//    protected function writeExcel(Response $response, int $subjects, Tournament $tournament, string $url): Response
//    {
//        $fileName = $this->getFileName($subjects);
//        $structure = $this->structureRepos->getStructure($tournament->getCompetition());
//
//        $spreadsheet = new FCToernooiSpreadsheet($tournament, $structure, $subjects, $url);
//        $spreadsheet->fillContents();
//
//        $excelWriter = IOFactory::createWriter($spreadsheet, 'Xlsx');
//
//        $tmpService = new TmpService();
//        $excelFileName = $tmpService->getPath(['worksheets'], 'toernooi-' . ((string)$tournament->getId()) . '.xlsx');
//        $excelWriter->save($excelFileName);
//
//        // For Excel2007 and above .xlsx files
//        $response = $response->withHeader('Cache-Control', 'must-revalidate');
//        $response = $response->withHeader('Pragma', 'public');
//        $response = $response->withHeader('Content-Disposition', 'attachment; filename="' . $fileName . '.xlsx";');
//        $response = $response->withHeader(
//            'Content-Type',
//            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet;charset=utf-8'
//        );
//
//        $newStream = new LazyOpenStream($excelFileName, 'r');
//        return $response->withBody($newStream);
//    }

    protected function getFileName(int $exportSubjects): string
    {
        switch ($exportSubjects) {
            case ExportConfig::GameNotes:
                return 'wedstrijdbrieven';
            case ExportConfig::Structure:
                return 'opzet-en-indeling';
            case ExportConfig::GamesPerPoule:
                return 'wedstrijden-per-poule';
            case ExportConfig::GamesPerField:
                return 'wedstrijden-per-veld';
            case ExportConfig::Planning:
                return 'wedstrijdplanning';
            case ExportConfig::PoulePivotTables:
                return 'poule-draaitabellen';
            case ExportConfig::QrCode:
                return 'qrcode-en-link';
            default:
                return 'toernooi';
        }
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
