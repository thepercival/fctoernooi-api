<?php

declare(strict_types=1);

namespace App\Actions;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Exception;
use FCToernooi\Role;
use FCToernooi\Tournament;
use FCToernooi\TournamentUser;
use FCToernooi\User;
use JMS\Serializer\SerializerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Slim\Exception\HttpException;

/**
 * @api
 */
final class TournamentUserAction extends Action
{
    private EntityRepository $tournamentUserRepos;

    public function __construct(
        LoggerInterface $logger,
        SerializerInterface $serializer,
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct($logger, $serializer);

        $metaData = $entityManager->getClassMetadata(TournamentUser::class);
        $this->tournamentUserRepos = new EntityRepository($entityManager, $metaData);
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
            $tournament = $request->getAttribute('tournament');

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

            $role = (int)$args['role'];
            if ($role <= 0) {
                throw new Exception('de rol is ongeldig', E_ERROR);
            }

            $roles = $tournamentUser->getRoles() |+ $role;
            $tournamentUser->setRoles($roles);
            $this->entityManager->persist($tournamentUser);
            $this->entityManager->flush();

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
    public function removeRole(Request $request, Response $response, array $args): Response
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
                    'je hebt geen rechten om een gebruiker van een ander toernooi aan te passen',
                    E_ERROR
                );
            }

            $role = (int)$args['role'];
            if ($role <= 0) {
                throw new Exception('de rol is ongeldig', E_ERROR);
            }
            $roles = $tournamentUser->getRoles();
            if( ($roles & $role) === $role) {
                $roles -= $role;
            }
            $tournamentUser->setRoles($roles);
            $this->entityManager->persist($tournamentUser);
            $this->entityManager->flush();

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

            $this->entityManager->remove($tournamentUser);
            $this->entityManager->flush();

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
