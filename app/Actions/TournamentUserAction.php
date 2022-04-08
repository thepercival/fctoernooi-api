<?php

declare(strict_types=1);

namespace App\Actions;

use Exception;
use FCToernooi\Role;
use FCToernooi\Tournament;
use FCToernooi\TournamentUser;
use FCToernooi\TournamentUser\Repository as TournamentUserRepository;
use FCToernooi\User;
use JMS\Serializer\SerializerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Slim\Exception\HttpException;

final class TournamentUserAction extends Action
{
    public function __construct(
        LoggerInterface $logger,
        SerializerInterface $serializer,
        private TournamentUserRepository $tournamentUserRepos
    ) {
        parent::__construct($logger, $serializer);
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
            /** @var Tournament $tournament */
            $tournament = $request->getAttribute('tournament');

            /** @var TournamentUser $tournamentUserSer */
            $tournamentUserSer = $this->serializer->deserialize(
                $this->getRawData($request),
                TournamentUser::class,
                'json'
            );

            $tournamentUser = $this->tournamentUserRepos->find((int)$args['tournamentUserId']);
            if ($tournamentUser === null) {
                throw new Exception('geen gebruiker met het opgegeven id gevonden', E_ERROR);
            }
            if ($tournamentUser->getTournament() !== $tournament) {
                throw new Exception(
                    'je hebt geen rechten om een gebruiker van een ander toernooi aan te passen',
                    E_ERROR
                );
            }
            $tournamentUser->setRoles($tournamentUserSer->getRoles());
            $this->tournamentUserRepos->save($tournamentUser);

            $json = $this->serializer->serialize($tournamentUser, 'json');
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

            $tournamentUser = $this->tournamentUserRepos->find((int)$args['tournamentUserId']);
            if ($tournamentUser === null) {
                throw new Exception('geen gebruiker met het opgegeven id gevonden', E_ERROR);
            }
            if ($tournamentUser->getTournament() !== $tournament) {
                throw new Exception(
                    'je hebt geen rechten om een gebruiker van een ander toernooi te verwijderen',
                    E_ERROR
                );
            }

            $this->tournamentUserRepos->remove($tournamentUser);

            return $response->withStatus(200);
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
    public function getEmailaddress(Request $request, Response $response, array $args): Response
    {
        try {
            /** @var Tournament $tournament */
            $tournament = $request->getAttribute('tournament');
            /** @var User $user */
            $user = $request->getAttribute('user');

            $roleAdmin = $tournament->getUser($user);
            if ($roleAdmin === null || !$roleAdmin->hasRoles(Role::ROLEADMIN)) {
                throw new \Exception('no permission to get emailaddress', E_ERROR);
            }

            $tournamentUser = $this->tournamentUserRepos->find($args['tournamentUserId']);
            if ($tournamentUser === null) {
                throw new \Exception('no user could be found', E_ERROR);
            }

            $emailaddress = $tournamentUser->getUser()->getEmailaddress();
            $json = $this->serializer->serialize($emailaddress, 'json');
            return $this->respondWithJson($response, $json);
        } catch (Exception $exception) {
            throw new HttpException($request, $exception->getMessage(), 422);
        }
    }
}
