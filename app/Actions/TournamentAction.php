<?php

declare(strict_types=1);

namespace App\Actions;

use App\Copiers\TournamentCopier;
use App\QueueService;
use App\QueueService\Planning as PlanningQueueService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Memcached;
use FCToernooi\CacheService;
use FCToernooi\CreditAction\Repository as CreditActionRepository;
use FCToernooi\Recess;
use FCToernooi\Role;
use FCToernooi\Tournament;
use FCToernooi\Tournament\Repository as TournamentRepository;
use FCToernooi\Tournament\StartEditMode;
use FCToernooi\TournamentUser;
use FCToernooi\User;
use JMS\Serializer\DeserializationContext;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\SerializerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Selective\Config\Configuration;
use Slim\Exception\HttpException;
use Sports\Competition\Service as CompetitionService;
use Sports\Competition\Validator as CompetitionValidator;
use Sports\Round\Number\PlanningCreator;
use Sports\Structure\Copier as StructureCopier;
use Sports\Structure\Repository as StructureRepository;
use Sports\Structure\Validator as StructureValidator;
use stdClass;

final class TournamentAction extends Action
{
    private CacheService $cacheService;

    public function __construct(
        LoggerInterface $logger,
        SerializerInterface $serializer,
        private TournamentRepository $tournamentRepos,
        private CreditActionRepository $creditActionRepos,
        private TournamentCopier $tournamentCopier,
        private StructureCopier $structureCopier,
        private StructureRepository $structureRepos,
        private EntityManagerInterface $entityManager,
        private PlanningCreator $planningCreator,
        Memcached $memcached,
        private Configuration $config
    ) {
        parent::__construct($logger, $serializer);

        $this->cacheService = new CacheService($memcached, $config->getString('namespace'));
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
                    'json',
                    $this->getSerializationContext($tournament, $user)
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
        $serGroups = ['Default', 'noReference'];

        if ($user !== null) {
            $serGroups[] = 'privacy';
        }
        return DeserializationContext::create()->setGroups($serGroups);
    }

    // admin
//    object authmatrix

    // roleadmin: provides fctoernooi\user::emailaddress, logisch

    // privacy: Sports\Competition\Referee::emailaddress
    protected function getSerializationContext(Tournament $tournament, User $user = null): SerializationContext
    {
        $serGroups = ['Default', 'noReference'];
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
        return SerializationContext::create()->setGroups($serGroups);
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
                $tournamentSer->getCompetition()->getStartDateTime(),
                $user
            );
            if ($tournament->getUsers()->count() === 0) {
                throw new \Exception('er zijn geen gebruikers gevonden voor het nieuwe toernooi', E_ERROR);
            }
            $this->tournamentRepos->customPersist($tournament, true);
// @TODO CDK PAYMENT
//            $this->creditActionRepos->removeCreateTournamentCredits($user);

            $serializationContext = $this->getSerializationContext($tournament, $user);
            $json = $this->serializer->serialize($tournament, 'json', $serializationContext);
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
            $tournament->getCompetition()->getLeague()->setName($tournamentSer->getName());
            $this->tournamentRepos->customPersist($tournament, true);
            $serializationContext = $this->getSerializationContext($tournament, $user);

            $json = $this->serializer->serialize($tournament, 'json', $serializationContext);
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
            $structureValidator->checkValidity(
                $newTournament->getCompetition(),
                $newStructure,
                $newTournament->getPlaceRanges()
            );

            $this->structureRepos->add($newStructure);

            $this->planningCreator->addFrom(
                new PlanningQueueService($this->config->getArray('queue')),
                $newStructure->getFirstRoundNumber(),
                $newTournament->createRecessPeriods(),
                QueueService::MAX_PRIORITY
            );

            // $this->creditActionRepos->removeCreateTournamentCredits($user);

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
}
