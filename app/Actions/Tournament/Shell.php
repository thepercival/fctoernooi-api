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
use Psr\Log\LoggerInterface;

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
     * @var EntityManager
     */
    protected $em;

    public function __construct(
        LoggerInterface $logger,
        SerializerInterface $serializer,
        TournamentRepository $tournamentRepos,
        AuthService $authService,
        EntityManager $em
    ) {
        parent::__construct($logger,$serializer);
        $this->tournamentRepos = $tournamentRepos;
        $this->authService = $authService;
        $this->serializer = $serializer;
        $this->em = $em;
    }

    public function fetchPublic(Request $request, Response $response, $args): Response
    {
        return $this->fetchPublicHelper($request, $response, $request->getAttribute("user"));
    }

//    public function fetch( Request $request, Response $response, $args ): Response
//    {
//        return $this->fetchPublicHelper( $request, $response, $request->getAttribute("user") );
//    }

    protected function fetchPublicHelper(Request $request, Response $response, User $user = null): Response
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
                $endDateTime = \DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s.u\Z', $queryParams["endDate"]);
            }
            if ($endDateTime === false) {
                $endDateTime = null;
            }

            $shells = [];
            $public = true;
            $tournamentsByDates = $this->tournamentRepos->findByFilter($name, $startDateTime, $endDateTime, $public);
            foreach ($tournamentsByDates as $tournament) {
                $shells[] = new TournamentShell($tournament, $user);
            }

            $json = $this->serializer->serialize($shells, 'json');
            return $this->respondWithJson($response, $json);
        } catch (\Exception $e) {
            return new ErrorResponse($e->getMessage(), 422);
        }
    }


    public function fetchMine(Request $request, Response $response, $args): Response
    {
        try {
            $shells = $this->getMyShells($request->getAttribute("user"));
            $json = $this->serializer->serialize($shells, 'json');
            return $this->respondWithJson($response, $json);
        } catch (\Exception $e) {
            return new ErrorResponse($e->getMessage(), 422);
        }
    }

    /**
     * @param User $user
     * @return array|TournamentShell[]
     */
    public function getMyShells(User $user): array
    {
        $shells = [];
        $tournamentsByRole = $this->tournamentRepos->findByPermissions($user, Role::ADMIN);
        foreach ($tournamentsByRole as $tournament) {
            $shells[] = new TournamentShell($tournament, $user);
        }
        return $shells;
    }
}