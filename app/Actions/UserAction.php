<?php

declare(strict_types=1);

namespace App\Actions;

use App\Response\ErrorResponse;
use FCToernooi\Auth\SyncService as AuthSyncService;
use JMS\Serializer\DeserializationContext;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\SerializerInterface;
use FCToernooi\User;
use Psr\Log\LoggerInterface;
use FCToernooi\User\Repository as UserRepository;
use \Slim\Middleware\JwtAuthentication;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class UserAction extends Action
{
    /**
     * @var UserRepository
     */
    private $userRepos;
    /**
     * @var AuthSyncService
     */
    protected $syncService;


    public function __construct(
        LoggerInterface $logger,
        UserRepository $userRepository,
        AuthSyncService $syncService,
        SerializerInterface $serializer
    ) {
        parent::__construct($logger, $serializer);
        $this->userRepos = $userRepository;
        $this->syncService = $syncService;
        $this->serializer = $serializer;
    }

    protected function getDeserializationContext()
    {
        $serGroups = ['Default', 'roleadmin'];
        return DeserializationContext::create()->setGroups($serGroups);
    }

    protected function getSerializationContext()
    {
        $serGroups = ['Default', 'roleadmin'];
        return SerializationContext::create()->setGroups($serGroups);
    }

    public function fetchOne(Request $request, Response $response, $args): Response
    {
        try {
            /** @var User $user */
            $user = $request->getAttribute("user");

            if ($user->getId() !== (int)$args['userId']) {
                throw new \Exception("de ingelogde gebruiker en de op te halen gebruiker zijn verschillend", E_ERROR);
            }
            $json = $this->serializer->serialize($user, 'json', $this->getSerializationContext());
            return $this->respondWithJson($response, $json);
        } catch (\Exception $exception) {
            return new ErrorResponse($exception->getMessage(), 400);
        }
    }

    public function edit(Request $request, Response $response, $args): Response
    {
        try {
            /** @var User $userAuth */
            $userAuth = $request->getAttribute("user");

            /** @var User $userSer */
            $userSer = $this->serializer->deserialize(
                $this->getRawData(),
                User::class,
                'json',
                $this->getDeserializationContext()
            );

            if ($userAuth->getId() !== $userSer->getId()) {
                throw new \Exception("de ingelogde gebruiker en de aan te passen gebruiker zijn verschillend", E_ERROR);
            }

            $userAuth->setEmailaddress(strtolower(trim($userSer->getEmailaddress())));
            $this->userRepos->save($userAuth);
            return $this->respondWithJson(
                $response,
                $this->serializer->serialize(
                    $userAuth,
                    'json',
                    $this->getSerializationContext()
                )
            );
        } catch (\Exception $exception) {
            return new ErrorResponse($exception->getMessage(), 422);
        }
    }

    public function remove(Request $request, Response $response, $args): Response
    {
        try {
            /** @var User $userAuth */
            $userAuth = $request->getAttribute("user");

            $user = $this->userRepos->find((int)$args['userId']);
            if ($userAuth->getId() !== $user->getId()) {
                throw new \Exception(
                    "de ingelogde gebruiker en de te verwijderen gebruiker zijn verschillend", E_ERROR
                );
            }

            $invitations = $this->syncService->revertTournamentUsers($userAuth);

            $this->userRepos->remove($user);
            return $response->withStatus(200);
        } catch (\Exception $exception) {
            return new ErrorResponse($exception->getMessage(), 422);
        }
    }
}
