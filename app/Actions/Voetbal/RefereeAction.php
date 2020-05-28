<?php
/**
 * Created by PhpStorm.
 * User: coen
 * Date: 14-11-17
 * Time: 14:02
 */

namespace App\Actions\Voetbal;

use App\Response\ErrorResponse;
use App\Response\ForbiddenResponse as ForbiddenResponse;
use FCToernooi\Tournament;
use Psr\Log\LoggerInterface;
use JMS\Serializer\SerializerInterface;
use Voetbal\Competition\Repository as CompetitionRepos;
use Voetbal\Referee as RefereeBase;
use Voetbal\Referee\Repository as RefereeRepository;
use FCToernooi\Auth\SyncService as AuthSyncService;
use FCToernooi\Role;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\DeserializationContext;
use Voetbal\Sport\Repository as SportRepository;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Actions\Action;
use Voetbal\Referee;
use Voetbal\Competition;

final class RefereeAction extends Action
{
    /**
     * @var RefereeRepository
     */
    protected $refereeRepos;
    /**
     * @var SportRepository
     */
    protected $sportRepos;
    /**
     * @var CompetitionRepos
     */
    protected $competitionRepos;
    /**
     * @var AuthSyncService
     */
    protected $authSyncService;

    public function __construct(
        LoggerInterface $logger,
        SerializerInterface $serializer,
        RefereeRepository $refereeRepos,
        SportRepository $sportRepos,
        CompetitionRepos $competitionRepos,
        AuthSyncService $authSyncService
    ) {
        parent::__construct($logger, $serializer);
        $this->refereeRepos = $refereeRepos;
        $this->sportRepos = $sportRepos;
        $this->competitionRepos = $competitionRepos;
        $this->authSyncService = $authSyncService;
    }

    public function add(Request $request, Response $response, $args): Response
    {
        try {
            $serGroups = ['Default', 'privacy'];
            $deserializationContext = DeserializationContext::create();
            $deserializationContext->setGroups($serGroups);

            /** @var \Voetbal\Referee $referee */
            $referee = $this->serializer->deserialize(
                $this->getRawData(),
                'Voetbal\Referee',
                'json',
                $deserializationContext
            );
            /** @var Tournament $tournament */
            $tournament = $request->getAttribute("tournament");

            $competition = $tournament->getCompetition();

            $this->checkInitialsAvailability($competition, $referee->getInitials(), $referee);
            $this->checkEmailaddressAvailability($competition, $referee->getEmailaddress(), $referee);

            $newReferee = new RefereeBase($competition, $referee->getRank());
            $newReferee->setInitials($referee->getInitials());
            $newReferee->setName($referee->getName());
            $newReferee->setEmailaddress($referee->getEmailaddress());
            $newReferee->setInfo($referee->getInfo());

            $this->refereeRepos->save($newReferee);
            $this->authSyncService->add($tournament, Role::REFEREE, $referee->getEmailaddress());

            $serializationContext = SerializationContext::create();
            $serializationContext->setGroups($serGroups);

            $json = $this->serializer->serialize($newReferee, 'json', $serializationContext);
            return $this->respondWithJson($response, $json);
        } catch (\Exception $e) {
            return new ErrorResponse($e->getMessage(), 422);
        }
    }

    public function edit($request, $response, $args)
    {
        try {
            $serGroups = ['Default','privacy'];
            $deserializationContext = DeserializationContext::create();
            $deserializationContext->setGroups($serGroups);

            /** @var \Voetbal\Referee $refereeSer */
            $refereeSer = $this->serializer->deserialize(
                $this->getRawData(),
                'Voetbal\Referee',
                'json',
                $deserializationContext
            );

            /** @var Tournament $tournament */
            $tournament = $request->getAttribute("tournament");

            $competition = $tournament->getCompetition();

            $referee = $this->getRefereeFromInput((int)$args["refereeId"], $competition);

            $this->checkInitialsAvailability($competition, $refereeSer->getInitials(), $referee);
            $this->checkEmailaddressAvailability($competition, $refereeSer->getEmailaddress(), $referee);

            $referee->setRank($refereeSer->getRank());
            $referee->setInitials($refereeSer->getInitials());
            $referee->setName($refereeSer->getName());
            $emailaddressOld = $referee->getEmailaddress();
            $referee->setEmailaddress($refereeSer->getEmailaddress());
            $referee->setInfo($refereeSer->getInfo());

            $this->refereeRepos->save($referee);
            if ($emailaddressOld !== $referee->getEmailaddress()) {
                $this->authSyncService->remove($tournament, Role::REFEREE, $emailaddressOld);
                $this->authSyncService->add($tournament, Role::REFEREE, $referee->getEmailaddress());
            }

            $serializationContext = SerializationContext::create();
            $serializationContext->setGroups($serGroups);

            $json = $this->serializer->serialize($referee, 'json', $serializationContext);
            return $this->respondWithJson($response, $json);
        } catch (\Exception $e) {
            return new ErrorResponse($e->getMessage(), 422);
        }
    }

    protected function checkInitialsAvailability(
        Competition $competition,
        string $initials,
        Referee $refereeToCheck = null
    ) {
        $nonUniqueReferees = $competition->getReferees()->filter(
            function ($refereeIt) use ($initials, $refereeToCheck): bool {
                return $refereeIt->getInitials() === $initials && $refereeToCheck !== $refereeIt;
            }
        );
        if (!$nonUniqueReferees->isEmpty()) {
            throw new \Exception(
                "de scheidsrechter met de initialen " . $initials . " bestaat al",
                E_ERROR
            );
        }
    }

    protected function checkEmailaddressAvailability(
        Competition $competition,
        string $emailaddress,
        Referee $refereeToCheck = null
    ) {
        $nonUniqueReferees = $competition->getReferees()->filter(
            function ($refereeIt) use ($emailaddress, $refereeToCheck): bool {
                return $refereeIt->getEmailaddress() === $emailaddress && $refereeToCheck !== $refereeIt;
            }
        );
        if (!$nonUniqueReferees->isEmpty()) {
            throw new \Exception(
                "de scheidsrechter met het emailadres " . $emailaddress . " bestaat al",
                E_ERROR
            );
        }
    }

    public function remove(Request $request, Response $response, $args): Response
    {
        try {
            /** @var Tournament $tournament */
            $tournament = $request->getAttribute("tournament");

            $competition = $tournament->getCompetition();

            $referee = $this->getRefereeFromInput((int)$args["refereeId"], $competition);

            $competition->getReferees()->removeElement($referee);
            $this->refereeRepos->remove($referee);
            $this->authSyncService->remove($tournament, Role::REFEREE, $referee->getEmailaddress());

            $rank = 1;
            foreach ($competition->getReferees() as $refereeIt) {
                $refereeIt->setRank($rank++);
                $this->refereeRepos->save($refereeIt);
            }

            return $response->withStatus(200);
        } catch (\Exception $e) {
            return new ErrorResponse($e->getMessage(), 422);
        }
    }

    protected function getRefereeFromInput(int $id, Competition $competition): Referee
    {
        $referee = $this->refereeRepos->find($id);
        if ($referee === null) {
            throw new \Exception("de scheidsrechter kon niet gevonden worden o.b.v. de invoer", E_ERROR);
        }
        if ($referee->getCompetition() !== $competition) {
            throw new \Exception(
                "de competitie van de scheidsrechter komt niet overeen met de verstuurde competitie",
                E_ERROR
            );
        }
        return $referee;
    }
}
