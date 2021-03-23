<?php

declare(strict_types=1);

namespace App\Actions\Tournament;

use Doctrine\ORM\EntityManager;
use JMS\Serializer\SerializerInterface;
use App\Response\ErrorResponse;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use FCToernooi\Tournament\Repository as TournamentRepository;
use FCToernooi\TournamentUser;
use FCToernooi\User;
use FCToernooi\Auth\Service as AuthService;
use App\Actions\Action;
use FCToernooi\Tournament\Shell as Shell;
use Psr\Log\LoggerInterface;

final class ShellAction extends Action
{
    public function __construct(
        LoggerInterface $logger,
        SerializerInterface $serializer,
        private TournamentRepository $tournamentRepos,
        private AuthService $authService,
        private EntityManager $em
    ) {
        parent::__construct($logger, $serializer);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param array<string, int|string> $args
     * @return Response
     */
    public function fetchPublic(Request $request, Response $response, array $args): Response
    {
        $user = $request->getAttribute("user");
        try {
            $queryParams = $request->getQueryParams();

            $name = null;
            if (array_key_exists("name", $queryParams) && strlen($queryParams["name"]) > 0) {
                $name = $queryParams["name"];
            }

            $startDateTime = null;
            if (array_key_exists("startDate", $queryParams) && strlen($queryParams["startDate"]) > 0) {
                $startDateTime = \DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s.u\Z', $queryParams["startDate"]);
            }
            if ($startDateTime === false) {
                $startDateTime = null;
            }

            $endDateTime = null;
            if (array_key_exists("endDate", $queryParams) && strlen($queryParams["endDate"]) > 0) {
                $endDateTime = \DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s.u\Z', $queryParams["endDate"]);
            }
            if ($endDateTime === false) {
                $endDateTime = null;
            }

            $shells = [];
            $public = true;
            $tournamentsByDates = $this->tournamentRepos->findByFilter($name, $startDateTime, $endDateTime, $public);
            foreach ($tournamentsByDates as $tournament) {
                $shells[] = new Shell($tournament, $user);
            }

            $json = $this->serializer->serialize($shells, 'json');
            return $this->respondWithJson($response, $json);
        } catch (\Exception $exception) {
            return new ErrorResponse($exception->getMessage(), 422);
        }
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param array<string, int|string> $args
     * @return Response
     */
    public function fetchWithRole(Request $request, Response $response, array $args): Response
    {
        try {
            $queryParams = $request->getQueryParams();
            $roles = 0;
            if (array_key_exists("roles", $queryParams) && strlen($queryParams["roles"]) > 0) {
                $roles = (int)$queryParams["roles"];
            }

            $shells = [];
            $user = $request->getAttribute("user");
            $tournamentsByRole = $this->tournamentRepos->findByRoles($user, $roles);
            foreach ($tournamentsByRole as $tournament) {
                $shells[] = new Shell($tournament, $user);
            }
            $json = $this->serializer->serialize($shells, 'json');
            return $this->respondWithJson($response, $json);
        } catch (\Exception $exception) {
            return new ErrorResponse($exception->getMessage(), 422);
        }
    }
}
