<?php
/**
 * Created by PhpStorm.
 * User: coen
 * Date: 28-3-18
 * Time: 20:31
 */

namespace App\Middleware\Authorization;

use App\Middleware\AuthorizationMiddleware;
use FCToernooi\Role;
use FCToernooi\TournamentUser;
use Voetbal\Game\Repository as GameRepository;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteContext;

class TournamentGameAdminMiddleware extends AuthorizationMiddleware
{
    /**
     * @var GameRepository
     */
    protected $gameRepos;

    public function __construct(
        GameRepository $gameRepos
    ) {
        $this->gameRepos = $gameRepos;
        parent::__construct(Role::GAMERESULTADMIN + Role::REFEREE);
    }

    protected function isAuthorized(TournamentUser $tournamentUser, Request $request): bool
    {
        if ($tournamentUser->hasRoles(Role::GAMERESULTADMIN)) {
            return true;
        }
        if ($tournamentUser->hasRoles(Role::REFEREE) === false) {
            return false;
        }
        $gameId = $this->getGameId($request);
        if ($gameId === null) {
            return false;
        }
        $game = $this->gameRepos->find($gameId);
        if ($game === null || $game->getReferee() === null) {
            return false;
        }
        return $game->getReferee()->getEmailaddress() === $tournamentUser->getUser()->getEmailaddress();
    }

    protected function getGameId(Request $request): ?int
    {
        $routeContext = RouteContext::fromRequest($request);
        $routingResults = $routeContext->getRoutingResults();
        $args = $routingResults->getRouteArguments();


        if (!array_key_exists("gameId", $args)) {
            return null;
        }
        return (int)$args["gameId"];
    }

//if (!$tournament->getPublic()) {
//if ($routeContext->getRoute()->getName() === "tournament-export") { // export is check by hash
//return true;
//}
//return $user !== null && $isAuthorized();
//}
//return $method === "GET" || $isAuthorized();


}
