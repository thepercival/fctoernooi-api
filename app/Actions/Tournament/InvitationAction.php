<?php
/**
 * Created by PhpStorm.
 * User: coen
 * Date: 10-1-19
 * Time: 11:46
 */

namespace App\Actions\Tournament;

use Doctrine\ORM\EntityManager;
use Exception;
use FCToernooi\Tournament\Invitation as TournamentInvitation;
use FCToernooi\Tournament;
use FCToernooi\TournamentUser;
use JMS\Serializer\SerializerInterface;
use App\Response\ErrorResponse;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use FCToernooi\Tournament\Repository as TournamentRepository;
use FCToernooi\Tournament\Invitation\Repository as TournamentInvitationRepository;
use App\Actions\Action;
use Psr\Log\LoggerInterface;
use FCToernooi\Auth\SyncService;

final class InvitationAction extends Action
{
    /**
     * @var TournamentRepository
     */
    private $tournamentRepos;
    /**
     * @var TournamentInvitationRepository
     */
    private $invitationRepos;
    /**
     * @var SyncService
     */
    private $syncService;

    public function __construct(
        LoggerInterface $logger,
        SerializerInterface $serializer,
        TournamentRepository $tournamentRepos,
        TournamentInvitationRepository $invitationRepos,
        SyncService $syncService
    ) {
        parent::__construct($logger, $serializer);
        $this->tournamentRepos = $tournamentRepos;
        $this->invitationRepos = $invitationRepos;
        $this->serializer = $serializer;
        $this->syncService = $syncService;
    }

    public function fetch(Request $request, Response $response, $args): Response
    {
        try {
            $tournament = $request->getAttribute("tournament");

            $invitations = $this->invitationRepos->findBy(["tournament" => $tournament]);

            $json = $this->serializer->serialize($invitations, 'json');
            return $this->respondWithJson($response, $json);
        } catch (\Exception $e) {
            return new ErrorResponse($e->getMessage(), 422);
        }
    }

    public function add(Request $request, Response $response, $args): Response
    {
        try {
            /** @var Tournament $tournament */
            $tournament = $request->getAttribute("tournament");

            /** @var TournamentInvitation $invitationSer */
            $invitationSer = $this->serializer->deserialize(
                $this->getRawData(),
                TournamentInvitation::class,
                'json'
            );

            $authorization = $this->syncService->add(
                $tournament,
                $invitationSer->getRoles(),
                $invitationSer->getEmailaddress()
            );


            $json = $this->serializer->serialize($authorization, 'json');
            return $this->respondWithJson($response, $json);
        } catch (\Exception $e) {
            return new ErrorResponse($e->getMessage(), 422);
        }
    }

    public function edit(Request $request, Response $response, $args): Response
    {
        try {
            /** @var Tournament $tournament */
            $tournament = $request->getAttribute("tournament");

            /** @var TournamentInvitation $invitationSer */
            $invitationSer = $this->serializer->deserialize(
                $this->getRawData(),
                TournamentInvitation::class,
                'json'
            );
            $invitation = $this->invitationRepos->find((int)$args['invitationId']);
            if ($invitation === null) {
                throw new \Exception("geen uitnodiging met het opgegeven id gevonden", E_ERROR);
            }
            if ($invitation->getTournament() !== $tournament) {
                throw new \Exception(
                    "je hebt geen rechten om een uitnodiging van een ander toernooi aan te passen",
                    E_ERROR
                );
            }
            $invitation->setRoles($invitationSer->getRoles());
            $this->invitationRepos->save($invitation);

            $json = $this->serializer->serialize($invitation, 'json');
            return $this->respondWithJson($response, $json);
        } catch (\Exception $e) {
            return new ErrorResponse($e->getMessage(), 422);
        }
    }

    public function remove(Request $request, Response $response, $args): Response
    {
        try {
            /** @var Tournament $tournament */
            $tournament = $request->getAttribute("tournament");

            $invitation = $this->invitationRepos->find((int)$args['invitationId']);
            if ($invitation === null) {
                throw new \Exception("geen uitnodiging met het opgegeven id gevonden", E_ERROR);
            }
            if ($invitation->getTournament() !== $tournament) {
                throw new \Exception(
                    "je hebt geen rechten om een uitnodiging van een ander toernooi te verwijderen",
                    E_ERROR
                );
            }

            $this->invitationRepos->remove($invitation);

            return $response->withStatus(200);
        } catch (\Exception $e) {
            return new ErrorResponse($e->getMessage(), 422);
        }
    }
}
