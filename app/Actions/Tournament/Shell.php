<?php
/**
 * Created by PhpStorm.
 * User: coen
 * Date: 10-1-19
 * Time: 11:46
 */

namespace App\Actions\Tournament;

use Doctrine\ORM\EntityManager;
use JMS\Serializer\SerializerInterface;
use App\Response\ErrorResponse;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use FCToernooi\Tournament\Repository as TournamentRepository;
use FCToernooi\Role;
use FCToernooi\User;
use FCToernooi\Auth\Service as AuthService;
use App\Actions\Action;
use FCToernooi\Tournament\Shell as TournamentShell;

final class Shell extends Action
{
    /**
     * @var TournamentRepository
     */
    private $tournamentRepos;
    /**
     * @var AuthService
     */
    private $authService;
    /**
     * @var SerializerInterface
     */
    protected $serializer;
    /**
     * @var EntityManager
     */
    protected $em;

    public function __construct(
        TournamentRepository $tournamentRepos,
        AuthService $authService,
        SerializerInterface $serializer,
        EntityManager $em
    ) {
        $this->tournamentRepos = $tournamentRepos;
        $this->authService = $authService;
        $this->serializer = $serializer;
        $this->em = $em;
    }

    public function fetch( Request $request, Response $response, $args ): Response
    {
        return $this->fetchHelper($request, $response, $args, true);
    }

    public function fetchWithRoles( Request $request, Response $response, $args ): Response
    {
        $user = $this->authService->getUser( $request );
        return $this->fetchHelper($request, $response, $args, null, $user);
    }

    public function fetchHelper( Request $request, Response $response, $args, bool $public = null, User $user = null ): Response
    {
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
                $startDateTime = \DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s.u\Z', $queryParams["endDate"]);
            }
            if ($endDateTime === false) {
                $endDateTime = null;
            }

            $withRoles = null;
            if (array_key_exists("withRoles", $queryParams) && strlen($queryParams["withRoles"]) > 0) {
                $withRoles = filter_var( $queryParams["withRoles"], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
            }
            $shells = [];
            {
                if ($user !== null && $withRoles === true) {
                    $tournamentsByRole = $this->tournamentRepos->findByPermissions($user, Role::ADMIN);
                    foreach ($tournamentsByRole as $tournament) {
                        $shells[] = new TournamentShell($tournament, $user);
                    }
                } else {
                    $tournamentsByDates = $this->tournamentRepos->findByFilter($name, $startDateTime, $endDateTime, $public);
                    foreach ($tournamentsByDates as $tournament) {
                        $shells[] = new TournamentShell($tournament, $user);
                    }
                }
            }
            $json = $this->serializer->serialize( $shells, 'json' );
            return $this->respondWithJson($response, $json);
        } catch (\Exception $e) {
            return new ErrorResponse($e->getMessage(), 422);
        }
    }
}