<?php
/**
 * Created by PhpStorm.
 * User: coen
 * Date: 28-3-18
 * Time: 20:31
 */

namespace App\Middleware;

use Slim\Routing\RouteContext;
use FCToernooi\Tournament;
use FCToernooi\User\Repository as UserRepository;
use FCToernooi\Tournament\Repository as TournamentRepository;
use Voetbal\Game\Repository as GameRepository;
use FCToernooi\Tournament\Service as TournamentService;
use FCToernooi\Auth\Token as AuthToken;
use FCToernooi\User;
use App\Response\ForbiddenResponse as ForbiddenResponse;
use FCToernooi\Role;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

class AuthenticationMiddleware implements MiddlewareInterface
{
    /**
     * @var TournamentService
     */
    protected $tournamentService;
    /**
     * @var UserRepository
     */
    protected $userRepos;
    /**
     * @var TournamentRepository
     */
    protected $tournamentRepos;
    /**
     * @var GameRepository
     */
    protected $gameRepos;

    public function __construct(
        TournamentService $tournamentService,
        UserRepository $userRepos,
        TournamentRepository $tournamentRepos,
        GameRepository $gameRepos
    ) {
        $this->userRepos = $userRepos;
        $this->tournamentRepos = $tournamentRepos;
        $this->tournamentService = $tournamentService;
        $this->gameRepos = $gameRepos;
    }

    public function process(Request $request, RequestHandler $handler): Response
    {
        if ($request->getMethod() === "OPTIONS") {
            return $handler->handle($request);
        }

        $user = null;
        {
            $token = $request->getAttribute('token');
            if ($token !== null && $token->isPopulated()) {
                $user = $this->getUser($token);
                if ($user === null) {
                    return new ForbiddenResponse("de ingelogde gebruikers kon niet gevonden worden");
                }
                $request = $request->withAttribute("user", $user);
            }
        }

        $tournament = $request->getAttribute("tournament");
        if ($tournament === null) {
            return $handler->handle($request);
        }

        if (!$this->isAuthorized($tournament, $request->getMethod(), $request, $user)) {
            return new ForbiddenResponse("je hebt geen rechten voor deze pagina");
        }

        return $handler->handle($request);
    }

    protected function getUser( AuthToken $token ): ?User
    {
        if ($token->getUserId() === null) {
            return null;
        }
        return $this->userRepos->find($token->getUserId());
    }

    protected function isAuthorized(Tournament $tournament, string $method, Request $request, User $user = null): bool
    {
        $routeContext = RouteContext::fromRequest($request);
        $routingResults = $routeContext->getRoutingResults();
        $args = $routingResults->getRouteArguments();

        $isAuthorized = function () use ($tournament, $user, $args) {
            if (array_key_exists("gameId", $args)) {
                return $this->isAuthorizedForGame($user, $tournament, (int)$args["gameId"]);
            }
            return $tournament->hasRole($user, Role::ADMIN);
        };
        if (!$tournament->getPublic()) {
            if ($routeContext->getRoute()->getName() === "tournament-export") { // export is check by hash
                return true;
            }
            return $user !== null && $isAuthorized();
        }
        return $method === "GET" || $isAuthorized();
    }

        // for $resourceType === 'structures' ->add/edit need to check in the action if round->competition === competitionSend
//        if ($resourceType === 'competitors') {
//            return $this->competitorActionAuthorized($user, $method, $queryParams);
//        } elseif ($resourceType === 'places') {
//            return $this->placeActionAuthorized($user, $method, $queryParams, $id);
//        } elseif ($resourceType === 'games') {
//            return $this->gameActionAuthorized($user, $method, $queryParams, $id);
//        } elseif ($resourceType === 'sports') {
//            return true;
//        } elseif ($resourceType === 'fields' || $resourceType === 'planning' || $resourceType === 'referees'
//            || $resourceType === 'structures' || $resourceType === 'sportconfigs' || $resourceType === 'planningconfigs'
//            || $resourceType === 'sportscoreconfigs'
//        ) {
//            return $this->otherActionAuthorized($user, $queryParams);
//        }
//        return false;


    protected function isAuthorizedForGame(User $user, Tournament $tournament, int $gameId ): bool
    {
        if ($tournament->hasRole($user, Role::GAMERESULTADMIN + Role::ADMIN )) {
            return true;
        }
        if (!$tournament->hasRole($user, Role::REFEREE)) {
            return false;
        }
        $game = $this->gameRepos->find( $gameId );
        if ($game === null || $game->getReferee() === null ) {
            return false;
        }
        if( $game->getReferee()->getEmailaddress() === $user->getEmailaddress() ) {
            return true;
        }
        return false;
    }


//    protected function competitorActionAuthorized(User $user, string $method, array $queryParams)
//    {
//        if (array_key_exists("associationid", $queryParams) !== true) {
//            return false;
//        }
//        if ($method !== 'POST' && $method !== 'PUT') {
//            return false;
//        }
//        $assRepos = $this->voetbalService->getRepository(\Voetbal\Association::class);
//        $association = $assRepos->find($queryParams["associationid"]);
//        if ($association === null) {
//            return false;
//        }
//        if( $this->mayUserChangeCompetitor( $user, $association ) === false ) {
//            return false;
//        }
//
//        return true;
//    }

//    protected function mayUserChangeCompetitor( User $user, Association $association )
//    {
//        $roleValues = Role::STRUCTUREADMIN;
//        $tournaments = $this->tournamentRepos->findByPermissions($user, $roleValues);
//        foreach ($tournaments as $tournament) {
//            if ($tournament->getCompetition()->getLeague()->getAssociation() === $association) {
//                return true;
//            }
//        }
//        return false;
//    }

//    protected function placeActionAuthorized(User $user, string $method, array $queryParams, int $id = null)
//    {
//        if ($method !== 'PUT') {
//            return false;
//        }
//        return $this->otherActionAuthorized($user, $queryParams);
//    }


//    protected function otherActionAuthorized(User $user, array $queryParams): bool
//    {
//        if (array_key_exists("competitionid", $queryParams) !== true) {
//            return false;
//        }
//        $tournament = $this->tournamentRepos->findOneBy(["competition" => $queryParams["competitionid"]]);
//        if ($tournament === null) {
//            return false;
//        }
//        if (!$tournament->hasRole($user, Role::ADMIN)) {
//            return false;
//        }
//        return true;
//    }
}