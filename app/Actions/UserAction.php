<?php

declare(strict_types=1);

namespace App\Actions;

use App\Response\ErrorResponse;
use Exception;
use FCToernooi\Auth\SyncService as AuthSyncService;
use FCToernooi\User;
use FCToernooi\User\Repository as UserRepository;
use JMS\Serializer\DeserializationContext;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\SerializerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;

final class UserAction extends Action
{
    public function __construct(
        LoggerInterface $logger,
        SerializerInterface $serializer,
        private UserRepository $userRepos,
        private AuthSyncService $syncService
    ) {
        parent::__construct($logger, $serializer);
    }

    protected function getDeserializationContext(): DeserializationContext
    {
        $serGroups = ['Default'];
        return DeserializationContext::create()->setGroups($serGroups);
    }

    protected function getSerializationContext(): SerializationContext
    {
        $serGroups = ['Default', 'self'];
        return SerializationContext::create()->setGroups($serGroups);
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
            /** @var User $user */
            $user = $request->getAttribute('user');

            if ($user->getId() !== (int)$args['userId']) {
                throw new Exception('de ingelogde gebruiker en de op te halen gebruiker zijn verschillend', E_ERROR);
            }
            $json = $this->serializer->serialize($user, 'json', $this->getSerializationContext());
            return $this->respondWithJson($response, $json);
        } catch (Exception $exception) {
            return new ErrorResponse($exception->getMessage(), 400);
        }
    }

//    /**
//     * @param Request $request
//     * @param Response $response
//     * @param array<string, int|string> $args
//     * @return Response
//     */
//    public function fetchEmailaddress(Request $request, Response $response, array $args): Response
//    {
//        try {
//            /** @var User $user */
//            $user = $request->getAttribute('user');
//
//            if ($user->getId() !== (int)$args['userId']) {
//                throw new Exception('de ingelogde gebruiker en de op te halen gebruiker zijn verschillend', E_ERROR);
//            }
//            $json = $this->serializer->serialize($user, 'json', $this->getSerializationContext());
//            return $this->respondWithJson($response, $json);
//        } catch (Exception $exception) {
//            return new ErrorResponse($exception->getMessage(), 400);
//        }
//    }

    /**
     * @param Request $request
     * @param Response $response
     * @param array<string, int|string> $args
     * @return Response
     */
    public function edit(Request $request, Response $response, array $args): Response
    {
        try {
            /** @var User $userAuth */
            $userAuth = $request->getAttribute('user');

            /** @var User $userSer */
            $userSer = $this->serializer->deserialize(
                $this->getRawData($request),
                User::class,
                'json',
                $this->getDeserializationContext()
            );

            if ($userAuth->getId() !== $userSer->getId()) {
                throw new Exception('de ingelogde gebruiker en de aan te passen gebruiker zijn verschillend', E_ERROR);
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
            /** @var User $userAuth */
            $userAuth = $request->getAttribute('user');

            $user = $this->userRepos->find((int)$args['userId']);
            if ($user === null || $userAuth->getId() !== $user->getId()) {
                throw new Exception(
                    'de ingelogde gebruiker en de te verwijderen gebruiker zijn verschillend',
                    E_ERROR
                );
            }

            $this->syncService->revertTournamentUsers($userAuth);

            $this->userRepos->remove($user);
            return $response->withStatus(200);
        } catch (Exception $exception) {
            return new ErrorResponse($exception->getMessage(), 422);
        }
    }
}
