<?php

declare(strict_types=1);

namespace App\Actions\Tournament;

use App\Actions\Action;
use App\Repositories\TournamentInvitationRepository as TournamentInvitationRepository;
use Doctrine\ORM\EntityManagerInterface;
use FCToernooi\Auth\SyncService;
use FCToernooi\Tournament;
use FCToernooi\Tournament\Invitation as TournamentInvitation;
use JMS\Serializer\SerializerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Slim\Exception\HttpException;

/**
 * @api
 */
final class InvitationAction extends Action
{
    public function __construct(
        LoggerInterface $logger,
        SerializerInterface $serializer,
        private TournamentInvitationRepository $invitationRepos,
        private SyncService $syncService,
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct($logger, $serializer);
    }

    /**
     * @psalm-suppress UnusedParam
     * @param Request $request
     * @param Response $response
     * @param array<string, int|string> $args
     * @return Response
     */
    public function fetch(Request $request, Response $response, array $args): Response
    {
        try {
            $tournament = $request->getAttribute("tournament");

            $invitations = $this->invitationRepos->findBy(["tournament" => $tournament]);

            $json = $this->serializer->serialize($invitations, 'json');
            return $this->respondWithJson($response, $json);
        } catch (\Exception $exception) {
            throw new HttpException($request, $exception->getMessage(), 422);
        }
    }

    /**
     * @psalm-suppress UnusedParam
     * @param Request $request
     * @param Response $response
     * @param array<string, int|string> $args
     * @return Response
     */
    public function add(Request $request, Response $response, array $args): Response
    {
        try {
            /** @var Tournament $tournament */
            $tournament = $request->getAttribute("tournament");

            /** @var TournamentInvitation $invitationSer */
            $invitationSer = $this->serializer->deserialize(
                $this->getRawData($request),
                TournamentInvitation::class,
                'json'
            );

            $authorization = $this->syncService->add(
                $tournament,
                $invitationSer->getRoles(),
                $invitationSer->getEmailaddress(),
                true
            );

            $json = $this->serializer->serialize($authorization, 'json');
            return $this->respondWithJson($response, $json);
        } catch (\Exception $exception) {
            throw new HttpException($request, $exception->getMessage(), 422);
        }
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param array<string, int|string> $args
     * @return Response
     */
    public function addRole(Request $request, Response $response, array $args): Response
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
                    "je hebt geen rechten om een uitnodiging van een ander toernooi aan te passen",
                    E_ERROR
                );
            }
            $role = (int)$args['role'];
            if ($role <= 0) {
                throw new \Exception('de rol is ongeldig', E_ERROR);
            }

            $roles = $invitation->getRoles() |+ $role;
            $invitation->setRoles($roles);
            $this->entityManager->persist($invitation);
            $this->entityManager->flush();

            $json = $this->serializer->serialize($invitation, 'json');
            return $this->respondWithJson($response, $json);
        } catch (\Exception $exception) {
            throw new HttpException($request, $exception->getMessage(), 422);
        }
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param array<string, int|string> $args
     * @return Response
     */
    public function removeRole(Request $request, Response $response, array $args): Response
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
                    "je hebt geen rechten om een uitnodiging van een ander toernooi aan te passen",
                    E_ERROR
                );
            }

            $role = (int)$args['role'];
            if ($role <= 0) {
                throw new \Exception('de rol is ongeldig', E_ERROR);
            }
            $roles = $invitation->getRoles();
            if( ($roles & $role) === $role) {
                $roles -= $role;
            }
            $invitation->setRoles($roles);
            $this->entityManager->persist($invitation);
            $this->entityManager->flush();

            $json = $this->serializer->serialize($invitation, 'json');
            return $this->respondWithJson($response, $json);
        } catch (\Exception $exception) {
            throw new HttpException($request, $exception->getMessage(), 422);
        }
    }

//    /**
//     * @param Request $request
//     * @param Response $response
//     * @param array<string, int|string> $args
//     * @return Response
//     */
//    public function edit(Request $request, Response $response, array $args): Response
//    {
//        try {
//            /** @var Tournament $tournament */
//            $tournament = $request->getAttribute("tournament");
//
//            /** @var TournamentInvitation $invitationSer */
//            $invitationSer = $this->serializer->deserialize(
//                $this->getRawData($request),
//                TournamentInvitation::class,
//                'json'
//            );
//            $invitation = $this->invitationRepos->find((int)$args['invitationId']);
//            if ($invitation === null) {
//                throw new \Exception("geen uitnodiging met het opgegeven id gevonden", E_ERROR);
//            }
//            if ($invitation->getTournament() !== $tournament) {
//                throw new \Exception(
//                    "je hebt geen rechten om een uitnodiging van een ander toernooi aan te passen",
//                    E_ERROR
//                );
//            }
//            $invitation->setRoles($invitationSer->getRoles());
//            $this->entityManager->persist($invitation);
//            $this->entityManager->flush();
//
//            $json = $this->serializer->serialize($invitation, 'json');
//            return $this->respondWithJson($response, $json);
//        } catch (\Exception $exception) {
//            throw new HttpException($request, $exception->getMessage(), 422);
//        }
//    }

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

            $this->entityManager->remove($invitation);
            $this->entityManager->flush();

            return $response->withStatus(200);
        } catch (\Exception $exception) {
            throw new HttpException($request, $exception->getMessage(), 422);
        }
    }
}
