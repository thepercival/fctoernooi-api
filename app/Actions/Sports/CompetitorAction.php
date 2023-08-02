<?php

declare(strict_types=1);

namespace App\Actions\Sports;

use App\Actions\Action;
use App\ImageService;
use App\Response\ErrorResponse;
use App\Response\ForbiddenResponse as ForbiddenResponse;
use FCToernooi\Competitor;
use FCToernooi\Competitor\Repository as CompetitorRepository;
use Sports\Structure\Repository as StructureRepository;
use FCToernooi\Role;
use FCToernooi\Tournament\Registration\Repository as RegistrationRepository;
use FCToernooi\Tournament;
use FCToernooi\User;
use JMS\Serializer\DeserializationContext;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\SerializerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Selective\Config\Configuration;
use Sports\Availability\Checker as AvailabilityChecker;
use FCToernooi\Tournament\Registration\State as RegistrationState;

final class CompetitorAction extends Action
{
    public function __construct(
        LoggerInterface $logger,
        SerializerInterface $serializer,
        protected CompetitorRepository $competitorRepos,
        protected RegistrationRepository $registrationRepos,
        protected StructureRepository $structureRepos,
        private ImageService $imageService,
        private Configuration $config
    ) {
        parent::__construct($logger, $serializer);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param array<string, int|string> $args
     * @return Response
     */
    public function fetchOne(Request $request, Response $response, array $args): Response
    {
        try {
            /** @var Tournament $tournament */
            $tournament = $request->getAttribute('tournament');

            /** @var User $user */
            $user = $request->getAttribute('user');

            $competitor = $this->getCompetitorFromInput((int)$args['competitorId'], $tournament);

            $serGroups = $this->getSerializationGroup($tournament, $user, $request->getQueryParams());
            $context = SerializationContext::create()->setGroups($serGroups);

            $json = $this->serializer->serialize($competitor, 'json', $context);
            return $this->respondWithJson($response, $json);
        } catch (\Exception $exception) {
            return new ErrorResponse($exception->getMessage(), 400, $this->logger);
        }
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param array<string, int|string> $args
     * @return Response
     */
    public function fetch(Request $request, Response $response, array $args): Response
    {
        try {
            /** @var Tournament $tournament */
            $tournament = $request->getAttribute('tournament');

            /** @var User $user */
            $user = $request->getAttribute('user');

            $serGroups = $this->getSerializationGroup($tournament, $user, $request->getQueryParams());
            $context = SerializationContext::create()->setGroups($serGroups);

            $json = $this->serializer->serialize($tournament->getCompetitors(), 'json', $context);
            return $this->respondWithJson($response, $json);
        } catch (\Exception $exception) {
            return new ErrorResponse($exception->getMessage(), 400, $this->logger);
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
            $serGroups = $this->getModifySerializationGroup();
            $deserContext = DeserializationContext::create()->setGroups($serGroups);

            /** @var Competitor $competitor */
            $competitor = $this->serializer->deserialize($this->getRawData($request), Competitor::class, 'json', $deserContext);
            /** @var Tournament $tournament */
            $tournament = $request->getAttribute("tournament");

            $availabilityChecker = new AvailabilityChecker();
            $competitors = array_values($tournament->getCompetitors()->toArray());
            $categoryNr = $competitor->getCategoryNr();
            $availabilityChecker->checkCompetitorName($categoryNr, $competitors, $competitor->getName());
            $availabilityChecker->checkCompetitorStartLocation($competitors, $competitor);

            $newCompetitor = new Competitor(
                $tournament,
                $competitor,
                $competitor->getName()
            );
            $newCompetitor->setEmailaddress($competitor->getEmailaddress());
            $newCompetitor->setTelephone($competitor->getTelephone());
            $newCompetitor->setHasLogo($competitor->getHasLogo());
            $newCompetitor->setInfo($competitor->getInfo());

            $this->competitorRepos->save($newCompetitor);

            $serGroups = $this->getModifySerializationGroup();
            $context = SerializationContext::create()->setGroups($serGroups);


            $json = $this->serializer->serialize($newCompetitor, 'json', $context);
            return $this->respondWithJson($response, $json);
        } catch (\Exception $exception) {
            return new ErrorResponse($exception->getMessage(), 422, $this->logger);
        }
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param array<string, int|string> $args
     * @return Response
     */
    public function addFromRegistration(Request $request, Response $response, array $args): Response
    {
        try {
            /** @var Tournament $tournament */
            $tournament = $request->getAttribute("tournament");

            $registration = $this->registrationRepos->find((int)$args['registrationId']);
            if( $registration === null ) {
                throw new \Exception('de inschrijving kon niet gevonden worden', E_ERROR);
            }

            $availabilityChecker = new AvailabilityChecker();
            $competitors = array_values($tournament->getCompetitors()->toArray());
            $categoryNr = $registration->getCategoryNr();
            $structure = $this->structureRepos->getStructure($tournament->getCompetition());
            $category = $structure->getCategory($categoryNr);
            if( $category === null ) {
                throw new \Exception('de categorie bij nummer '.$categoryNr.' is niet gevonden', E_ERROR);
            }
            $availabilityChecker->checkCompetitorName($categoryNr, $competitors, $registration->getName());
            $startLocation = $availabilityChecker->getFirstAvailableStartLocation($category, $competitors);

            $newCompetitor = new Competitor(
                $tournament,
                $startLocation,
                $registration->getName()
            );
            $newCompetitor->setEmailaddress($registration->getEmailaddress());
            $newCompetitor->setTelephone($registration->getTelephone());
            $newCompetitor->setHasLogo(false);
            $newCompetitor->setInfo($registration->getInfo());

            $this->competitorRepos->save($newCompetitor);

            $registration->setCompetitor($newCompetitor);
            $registration->setState(RegistrationState::Accepted);
            $this->registrationRepos->save($registration);

            $serGroups = $this->getModifySerializationGroup();
            $context = SerializationContext::create()->setGroups($serGroups);

            $json = $this->serializer->serialize($newCompetitor, 'json', $context);
            return $this->respondWithJson($response, $json);
        } catch (\Exception $exception) {
            return new ErrorResponse($exception->getMessage(), 422, $this->logger);
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
            $serGroups = $this->getModifySerializationGroup();
            $deserContext = DeserializationContext::create()->setGroups($serGroups);

            /** @var Competitor $competitorSer */
            $competitorSer = $this->serializer->deserialize($this->getRawData($request), Competitor::class, 'json', $deserContext);

            /** @var Tournament $tournament */
            $tournament = $request->getAttribute("tournament");

            $competitor = $this->getCompetitorFromInput((int)$args["competitorId"], $tournament);

            $availabilityChecker = new AvailabilityChecker();
            $competitors = array_values($tournament->getCompetitors()->toArray());
            $categoryNr = $competitorSer->getCategoryNr();
            $availabilityChecker->checkCompetitorName($categoryNr, $competitors, $competitor->getName(), $competitor);
            $availabilityChecker->checkCompetitorStartLocation($competitors, $competitor, $competitor);

            $competitor->setName($competitorSer->getName());
            $competitor->setEmailaddress($competitorSer->getEmailaddress());
            $competitor->setTelephone($competitorSer->getTelephone());
            $competitor->setRegistered($competitorSer->getRegistered());
            $competitor->setHasLogo($competitorSer->getHasLogo());
            $competitor->setInfo($competitorSer->getInfo());
            $this->competitorRepos->save($competitor);

            $serGroups = $this->getModifySerializationGroup();
            $context = SerializationContext::create()->setGroups($serGroups);

            $json = $this->serializer->serialize($competitor, 'json', $context);
            return $this->respondWithJson($response, $json);
        } catch (\Exception $exception) {
            return new ErrorResponse($exception->getMessage(), 422, $this->logger);
        }
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param array<string, int|string> $args
     * @return Response
     */
    public function swap(Request $request, Response $response, array $args): Response
    {
        try {
            /** @var Tournament $tournament */
            $tournament = $request->getAttribute("tournament");

            $competitorOne = $this->getCompetitorFromInput((int) $args['competitorOneId'], $tournament);
            $competitorTwo = $this->getCompetitorFromInput((int) $args['competitorTwoId'], $tournament);

            $pouleNrTmp = $competitorOne->getPouleNr();
            $placeNrTmp = $competitorOne->getPlaceNr();
            $competitorOne->setPouleNr($competitorTwo->getPouleNr());
            $competitorOne->setPlaceNr($competitorTwo->getPlaceNr());
            $competitorTwo->setPouleNr($pouleNrTmp);
            $competitorTwo->setPlaceNr($placeNrTmp);
            $this->competitorRepos->save($competitorOne);
            $this->competitorRepos->save($competitorTwo);

            return $response->withStatus(200);
        } catch (\Exception $exception) {
            return new ErrorResponse($exception->getMessage(), 422, $this->logger);
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

            $competitor = $this->getCompetitorFromInput((int)$args['competitorId'], $tournament);
            if ($competitor->getTournament() !== $tournament) {
                return new ForbiddenResponse('het toernooi komt niet overeen met het toernooi van de deelnemer');
            }

            $this->competitorRepos->remove($competitor);

            return $response->withStatus(200);
        } catch (\Exception $exception) {
            return new ErrorResponse($exception->getMessage(), 422, $this->logger);
        }
    }
    protected function getCompetitorFromInput(int $id, Tournament $tournament): Competitor
    {
        $competitor = $this->competitorRepos->find($id);
        if ($competitor === null) {
            throw new \Exception('de deelnemer kon niet gevonden worden o.b.v. de invoer', E_ERROR);
        }
        if ($competitor->getTournament() !== $tournament) {
            throw new \Exception('de deelnemer is van een ander toernooi', E_ERROR);
        }
        return $competitor;
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

            $competitor = $this->competitorRepos->find((int)$args['competitorId']);
            if ($competitor === null) {
                throw new \Exception("geen deelnemer met het opgegeven id gevonden", E_ERROR);
            }
            if ($competitor->getTournament() !== $tournament) {
                return new ForbiddenResponse("het toernooi komt niet overeen met het toernooi van de deelnemer");
            }

            $uploadedFiles = $request->getUploadedFiles();
            if (!array_key_exists("logostream", $uploadedFiles)) {
                throw new \Exception("geen goede upload gedaan, probeer opnieuw", E_ERROR);
            }

            $pathPostfix = $this->config->getString('images.competitors.pathpostfix');
            $this->imageService->processSVG((string)$competitor->getId(), $uploadedFiles["logostream"], $pathPostfix);

            $competitor->setHasLogo(true);
            $this->competitorRepos->save($competitor);

            $serGroups = $this->getModifySerializationGroup();
            $context = SerializationContext::create()->setGroups($serGroups);

            $json = $this->serializer->serialize($competitor, 'json', $context);
            return $this->respondWithJson($response, $json);
        } catch (\Exception $exception) {
            return new ErrorResponse($exception->getMessage(), 422, $this->logger);
        }
    }

    /**
     * @param Tournament $tournament
     * @param User $user
     * @param array $queryParams
     * @return list<string>
     */
    private function getSerializationGroup(Tournament $tournament, User $user, array $queryParams): array {
        $serGroups = ['Default'];
        $tournamentUser = $tournament->getUser($user);
        if( $tournamentUser && $tournamentUser->hasARole(Role::ADMIN ) ) {
            if (array_key_exists('privacy', $queryParams) && $queryParams['privacy'] === 'true') {
                return $this->getModifySerializationGroup();
            }
        }
        return $serGroups;
    }

    /**
     * @return list<string>
     */
    private function getModifySerializationGroup(): array {
        return ['Default','privacy'];
    }
}
