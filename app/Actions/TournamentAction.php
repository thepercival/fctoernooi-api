<?php

declare(strict_types=1);

namespace App\Actions;

use App\Copiers\TournamentCopier;
use App\GuzzleClient;
use App\ImageService;
use App\Response\ErrorResponse;
use Sports\Competition\Sport\FromToMapper;
use Sports\Competition\Sport\FromToMapStrategy;
use Sports\Qualify\Rule\Creator as QualifyRuleCreator;
use Sports\Poule\Horizontal\Creator as HorizontalPouleCreator;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use FCToernooi\CacheService;
use FCToernooi\CreditAction\Repository as CreditActionRepository;
use FCToernooi\Planning\PlanningWriter;
use FCToernooi\Recess;
use FCToernooi\Role;
use FCToernooi\Tournament;
use FCToernooi\Tournament\Repository as TournamentRepository;
use FCToernooi\Tournament\Rule as TournamentRule;
use FCToernooi\Tournament\Rule\Repository as TournamentRuleRepository;
use FCToernooi\Tournament\StartEditMode;
use FCToernooi\TournamentUser;
use FCToernooi\User;
use JMS\Serializer\DeserializationContext;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\SerializerInterface;
use Memcached;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Selective\Config\Configuration;
use Slim\Exception\HttpException;
use Sports\Competition\Service as CompetitionService;
use Sports\Competition\Validator as CompetitionValidator;
use Sports\Structure\Copier as StructureCopier;
use Sports\Structure\Repository as StructureRepository;
use Sports\Structure\Validator as StructureValidator;
use stdClass;

final class TournamentAction extends Action
{
    private CacheService $cacheService;
    private ImageService $imageService;
    private GuzzleClient $planningClient;
    private PlanningWriter $planningWriter;

    public function __construct(
        LoggerInterface $logger,
        SerializerInterface $serializer,
        private TournamentRepository $tournamentRepos,

        private TournamentRuleRepository $ruleRepos,
        private CreditActionRepository $creditActionRepos,
        private TournamentCopier $tournamentCopier,
        private StructureRepository $structureRepos,
        private EntityManagerInterface $entityManager,
        Memcached $memcached,
        private Configuration $config
    ) {
        parent::__construct($logger, $serializer);

        $this->cacheService = new CacheService($memcached, $config->getString('namespace'));
        $this->imageService =  new ImageService($this->config, $logger);
        $this->planningClient = new GuzzleClient(
            $config->getString('scheduler.url'),
            $config->getString('scheduler.apikey'),
            $this->cacheService, $serializer, $logger
        );
        $this->planningWriter = new PlanningWriter($this->cacheService, $this->entityManager, $logger);
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
        try {
            /** @var User|null $user */
            $user = $request->getAttribute('user');

            $tournamentId = (int)$args['tournamentId'];
            $json = $this->cacheService->getTournament($tournamentId);
            if ($json === false || $this->config->getString('environment') === 'development') {
                $tournament = $this->tournamentRepos->find($tournamentId);
                if ($tournament === null) {
                    throw new \Exception('unknownn tournamentId', E_ERROR);
                }
                $json = $this->serializer->serialize(
                    $tournament,
                    'json'
                );
                $this->cacheService->setTournament($tournamentId, $json);
            }
            return $this->respondWithJson($response, $json);
        } catch (Exception $exception) {
            throw new HttpException($request, $exception->getMessage(), 400);
        }
    }

    protected function getDeserializationContext(User $user = null): DeserializationContext
    {
        $serGroups = ['Default'];
        if ($user !== null) {
            $serGroups[] = 'privacy';
        }
        return DeserializationContext::create()->setGroups($serGroups);
    }

    // admin
//    object authmatrix

    // roleadmin: provides fctoernooi\user::emailaddress, logisch

    // privacy: Sports\Competition\Referee::emailaddress


//        if ($user !== null) {
//            $tournamentUser = $tournament->getUser($user);
//            if ($tournamentUser !== null) {
//                $serGroups[] = 'users';
//                if ($tournamentUser->hasRoles(Role::ADMIN)) {
//                    $serGroups[] = 'privacy';
//                }
//                if ($tournamentUser->hasRoles(Role::ROLEADMIN)) {
//                    $serGroups[] = 'roleadmin';
//                }
//            }
//        }

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
            $tournamentSer->setIntro('Welkom bij ons toernooi! Hieronder staan de regels. De onderwerpen kun je met het menu, onderaan het scherm, opvragen.');
            if (!$user->getValidated()
                && $user->getValidateIn() < CreditActionRepository::NR_OF_CREDITS_PER_TOURNAMENT) {
                throw new \Exception('je moet eerst je account validateren', E_ERROR);
            }
            if ($user->getNrOfCredits() < 1) {
                throw new \Exception('je hebt geen credits meer om toernooien aan te maken', E_ERROR);
            }
            new TournamentUser($tournamentSer, $user, Role::ADMIN + Role::GAMERESULTADMIN + Role::ROLEADMIN);

            $tournament = $this->tournamentCopier->copy(
                $tournamentSer,
                null,
                $tournamentSer->getCompetition()->getStartDateTime(),
                $user
            );
            if ($tournament->getUsers()->count() === 0) {
                throw new \Exception('er zijn geen gebruikers gevonden voor het nieuwe toernooi', E_ERROR);
            }
            $this->tournamentRepos->customPersist($tournament, true);
// @TODO CDK PAYMENT
//            $this->creditActionRepos->removeCreateTournamentCredits($user);

            $ruleText = 'sportiviteit en respect zijn de uitgangspunten van dit toernooi';
            $rule = new TournamentRule($tournament, $ruleText);
            $this->ruleRepos->save($rule, true );

            $json = $this->serializer->serialize($tournament, 'json');
            return $this->respondWithJson($response, $json);
        } catch (Exception $exception) {
            throw new HttpException($request, $exception->getMessage(), 422);
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

            $competitionService = new CompetitionService();
            $competition = $tournament->getCompetition();
            $diff = $competitionService->changeStartDateTime($competition, $dateTime);
            if ($diff !== null) {
                if ($tournament->getStartEditMode() === StartEditMode::EditLongTerm) {
                    $tournament->setStartEditMode(StartEditMode::EditShortTerm);
                } else if ($tournament->getStartEditMode() === StartEditMode::EditShortTerm) {
                    $tournament->setStartEditMode(StartEditMode::ReadOnly);
                }
                else {
                    throw new HttpException($request, 'de startdatum kan niet meer gewijzigd worden, maak een nieuwe editie van het toernooi aan', 422);
                }
            };
            $competition->setAgainstRuleSet($ruleSet);
            foreach ($tournamentSer->getRecesses() as $recessSer) {
                new Recess($tournament, $recessSer->getName(), $recessSer->getPeriod());
            }
            $tournament->setPublic($tournamentSer->getPublic());
            $tournament->setLocation($tournamentSer->getLocation());
            $tournament->setIntro($tournamentSer->getIntro());
            $serTheme = $tournamentSer->getTheme();
            if($serTheme !== null) {
                foreach( $serTheme as $key => $value ){
                    $tournament->setTheme($key, $value);
                }
            }
            $tournament->getCompetition()->getLeague()->setName($tournamentSer->getName());
            $this->tournamentRepos->customPersist($tournament, true);

            $json = $this->serializer->serialize($tournament, 'json');
            return $this->respondWithJson($response, $json);
        } catch (Exception $exception) {
            throw new HttpException($request, $exception->getMessage(), 422);
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

            foreach( $tournament->getSponsors() as $sponsor) {
                $logoExtension = $sponsor->getLogoExtension();
                if( $logoExtension !== null ) {
                    $this->imageService->removeImages($sponsor, $logoExtension);
                }
            }
            foreach( $tournament->getCompetitors() as $competitor) {
                $logoExtension = $competitor->getLogoExtension();
                if( $logoExtension !== null ) {
                    $this->imageService->removeImages($competitor, $logoExtension);
                }
            }

            $this->tournamentRepos->remove($tournament);

            return $response->withStatus(200);
        } catch (Exception $exception) {
            throw new HttpException($request, 'het toernooi is niet verwijdered : ' . $exception->getMessage(), 404);
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

            if (!$user->getValidated() && $user->getValidateIn(
                ) < CreditActionRepository::NR_OF_CREDITS_PER_TOURNAMENT) {
                throw new \Exception('je moet eerst je account validateren', E_ERROR);
            }
            if ($user->getNrOfCredits() < 1) {
                throw new \Exception('je hebt geen credits meer om toernooien aan te maken', E_ERROR);
            }

            if (!$tournament->getExample() ) {
                $tournamentUser = $tournament->getUser($user);
                if( $tournamentUser === null || !$tournamentUser->hasARole(Role::ADMIN) ) {
                    throw new \Exception('je hebt geen rechten om het toernooi te kopieren', E_ERROR);
                }
            }

            /** @var stdClass $copyData */
            $copyData = $this->getFormData($request);
            if (property_exists($copyData, 'startDate') === false) {
                throw new Exception('er is geen nieuwe startdatum-tijd opgegeven', E_ERROR);
            }
            if (property_exists($copyData, 'name') === false) {
                throw new Exception('er is geen naam opgegeven', E_ERROR);
            }
            $processCompetitors = property_exists($copyData, 'name') && $copyData->competitors == true;
            $competition = $tournament->getCompetition();

//            if ( $this->structureRepos->hasStructure( $competition )  ) {
//                throw new \Exception("er kan voor deze competitie geen indeling worden aangemaakt, omdat deze al bestaan", E_ERROR);
//            }

            $startDateTime = DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s.u\Z', $copyData->startDate);
            if ($startDateTime === false) {
                throw new Exception('no input for startdatetime', E_ERROR);
            }

            $newTournament = $this->tournamentCopier->copy($tournament, $copyData->name, $startDateTime, $user);
            $newTournament->setLogoExtension($tournament->getLogoExtension());
            $theme = $tournament->getTheme();
            if($theme !== null) {
                foreach( $theme as $key => $value ){
                    $newTournament->setTheme($key, $value);
                }
            }
            $this->tournamentRepos->customPersist($newTournament, true);
            if ($tournament->getLogoExtension() !== null) {
                $this->imageService->copyImages($tournament, $newTournament);
            }

            $this->tournamentCopier->copyAndSaveSettings($tournament, $newTournament);

            $structure = $this->structureRepos->getStructure($competition);

            $fromToMapper = new FromToMapper(
                array_values( $competition->getSports()->toArray() ),
                array_values( $newTournament->getCompetition()->getSports()->toArray() ),
                FromToMapStrategy::ByProperties
            );

            $structureCopier = new StructureCopier(
                new HorizontalPouleCreator(),
                new QualifyRuleCreator(),
                $fromToMapper
            );
            $newStructure = $structureCopier->copy($structure, $newTournament->getCompetition());

            $competitionValidator = new CompetitionValidator();
            $competitionValidator->checkValidity($newTournament->getCompetition());

            $structureValidator = new StructureValidator();
            $structureValidator->checkValidity(
                $newTournament->getCompetition(),
                $newStructure,
                $newTournament->getPlaceRanges()
            );

            $this->structureRepos->add($newStructure);

            if( $processCompetitors ) {
                $this->tournamentCopier->copyAndSaveCompetitors(
                    $tournament, $newTournament, $newStructure, $this->imageService);
            }

            $this->tournamentCopier->copyAndSaveLockerRooms($tournament, $newTournament);

            $this->tournamentCopier->copyAndSaveSponsors($tournament, $newTournament, $this->imageService);

            $this->tournamentCopier->copyAndSaveRules($tournament, $newTournament);

            // $this->creditActionRepos->removeCreateTournamentCredits($user);

            $roundNumbersWithPlanning = $this->planningClient->getRoundNumbersWithPlanning(
                $newTournament->getCompetition(), $newStructure->getRoundNumbers(), false );
            $this->planningWriter->write($tournament, $roundNumbersWithPlanning);

            $conn->commit();

            $json = $this->serializer->serialize($newTournament->getId(), 'json');
            return $this->respondWithJson($response, $json);
        } catch (Exception $exception) {
            $conn->rollBack();
            throw new HttpException($request, $exception->getMessage(), 422);
        }
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
            throw new HttpException($request, $exception->getMessage(), 422);
        }
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param array<string, int|string> $args
     * @return Response
     */
    public function upload(Request $request, Response $response, array $args): Response
    {
        try {
            /** @var Tournament $tournament */
            $tournament = $request->getAttribute("tournament");

            $uploadedFiles = $request->getUploadedFiles();
            if (!array_key_exists("logostream", $uploadedFiles)) {
                $logoExtension = $tournament->getLogoExtension();
                if( $logoExtension !== null ) {
                    $this->imageService->removeImages($tournament, $logoExtension);
                }
                $extension = null;
            } else {
                $extension = $this->imageService->processUploadedImage($tournament, $uploadedFiles["logostream"]);
            }

            $tournament->setLogoExtension($extension);
            $this->tournamentRepos->save($tournament);

            $json = $this->serializer->serialize($tournament, 'json');
            return $this->respondWithJson($response, $json);
        } catch (\Exception $exception) {
            return new ErrorResponse($exception->getMessage(), 422, $this->logger);
        }
    }
}
