<?php

declare(strict_types=1);

namespace App\Actions\Sports;

use App\Response\ErrorResponse;
use Exception;
use FCToernooi\Tournament;
use Psr\Log\LoggerInterface;
use JMS\Serializer\SerializerInterface;
use Sports\Availability\Checker as AvailabilityChecker;
use Sports\Competition\Repository as CompetitionRepos;
use Sports\Competition\Referee;
use Sports\Competition\Referee\Repository as RefereeRepository;
use FCToernooi\Auth\SyncService as AuthSyncService;
use FCToernooi\Role;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\DeserializationContext;
use Sports\Sport\Repository as SportRepository;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Actions\Action;
use Sports\Competition;
use Sports\Priority\Service as PriorityService;

final class RefereeAction extends Action
{
    public function __construct(
        LoggerInterface $logger,
        SerializerInterface $serializer,
        private RefereeRepository $refereeRepos,
        private SportRepository $sportRepos,
        private CompetitionRepos $competitionRepos,
        private AuthSyncService $authSyncService
    ) {
        parent::__construct($logger, $serializer);
    }

    protected function getDeserializationContext(): DeserializationContext
    {
        return DeserializationContext::create()->setGroups(['Default', 'privacy', 'noReference']);
    }

    protected function getSerializationContext(): SerializationContext
    {
        return SerializationContext::create()->setGroups(['Default', 'noReference']);
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
            /** @var Referee $referee */
            $referee = $this->serializer->deserialize(
                $this->getRawData(),
                Referee::class,
                'json',
                $this->getDeserializationContext()
            );
            /** @var Tournament $tournament */
            $tournament = $request->getAttribute("tournament");

            $competition = $tournament->getCompetition();

            $availabilityChecker = new AvailabilityChecker();
            $availabilityChecker->checkRefereeEmailaddress($competition, $referee->getInitials());
            $availabilityChecker->checkRefereeInitials($competition, $referee->getInitials());

            $newReferee = new Referee($competition, $referee->getInitials());
            $newReferee->setName($referee->getName());
            $newReferee->setEmailaddress($referee->getEmailaddress());
            $newReferee->setInfo($referee->getInfo());

            $this->refereeRepos->save($newReferee);

            $sendMail = false;
            if (array_key_exists("invite", $args)) {
                $sendMail = filter_var($args["invite"], FILTER_VALIDATE_BOOLEAN);
            }
            $this->authSyncService->add($tournament, Role::REFEREE, $referee->getEmailaddress(), $sendMail);

            $json = $this->serializer->serialize($newReferee, 'json', $this->getSerializationContext());
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
            /** @var Referee $refereeSer */
            $refereeSer = $this->serializer->deserialize(
                $this->getRawData(),
                Referee::class,
                'json',
                $this->getDeserializationContext()
            );

            /** @var Tournament $tournament */
            $tournament = $request->getAttribute("tournament");

            $competition = $tournament->getCompetition();

            $referee = $this->getRefereeFromInput((int)$args["refereeId"], $competition);

            $availabilityChecker = new AvailabilityChecker();
            $availabilityChecker->checkRefereeEmailaddress($competition, $refereeSer->getEmailaddress(), $referee);
            $availabilityChecker->checkRefereeInitials($competition, $refereeSer->getInitials(), $referee);
            $availabilityChecker->checkRefereePriority($competition, $refereeSer->getPriority(), $referee);

            $referee->setPriority($refereeSer->getPriority());
            $referee->setInitials($refereeSer->getInitials());
            $referee->setName($refereeSer->getName());
            $emailaddressOld = $referee->getEmailaddress();
            $referee->setEmailaddress($refereeSer->getEmailaddress());
            $referee->setInfo($refereeSer->getInfo());

            $this->refereeRepos->save($referee);

//            $priorityService = new PriorityService( $competition->getReferees() );
//            $changedReferees = $priorityService->getChanged();
//            foreach( $changedReferees as $changedReferee) {
//                $this->refereeRepos->save($changedReferee);
//            }

            if ($emailaddressOld !== $referee->getEmailaddress()) {
                $this->authSyncService->remove($tournament, Role::REFEREE, $emailaddressOld);
                // $this->authSyncService->add($tournament, Role::REFEREE, $referee->getEmailaddress());
            }

            $json = $this->serializer->serialize($referee, 'json', $this->getSerializationContext());
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
    public function priorityUp(Request $request, Response $response, array $args): Response
    {
        try {
            /** @var Tournament $tournament */
            $tournament = $request->getAttribute("tournament");

            $competition = $tournament->getCompetition();

            $referee = $this->getRefereeFromInput((int)$args["refereeId"], $competition);

            $priorityService = new PriorityService($competition->getReferees()->toArray());
            $changedReferees = $priorityService->upgrade($referee);
            foreach ($changedReferees as $changedReferee) {
                $this->refereeRepos->save($changedReferee);
            }

            return $response->withStatus(200);
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
            $tournament = $request->getAttribute("tournament");

            $competition = $tournament->getCompetition();

            $referee = $this->getRefereeFromInput((int)$args["refereeId"], $competition);

            $competition->getReferees()->removeElement($referee);
            $this->refereeRepos->remove($referee);
            $this->authSyncService->remove($tournament, Role::REFEREE, $referee->getEmailaddress());

            $priorityService = new PriorityService($competition->getReferees()->toArray());
            $changedReferees = $priorityService->validate();
            foreach ($changedReferees as $changedReferee) {
                $this->refereeRepos->save($changedReferee);
            }

            return $response->withStatus(200);
        } catch (Exception $exception) {
            return new ErrorResponse($exception->getMessage(), 422);
        }
    }

    protected function getRefereeFromInput(int $id, Competition $competition): Referee
    {
        $referee = $this->refereeRepos->find($id);
        if ($referee === null) {
            throw new Exception("de scheidsrechter kon niet gevonden worden o.b.v. de invoer", E_ERROR);
        }
        if ($referee->getCompetition() !== $competition) {
            throw new Exception(
                "de competitie van de scheidsrechter komt niet overeen met de verstuurde competitie",
                E_ERROR
            );
        }
        return $referee;
    }
}
