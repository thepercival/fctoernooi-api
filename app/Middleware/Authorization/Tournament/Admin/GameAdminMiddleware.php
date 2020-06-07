<?php
/**
 * Created by PhpStorm.
 * User: coen
 * Date: 28-3-18
 * Time: 20:31
 */

namespace App\Middleware\Authorization\Tournament\Admin;

use App\Middleware\Authorization\Tournament\AdminMiddleware as AuthorizationTournamentAdminMiddleware;
use FCToernooi\Role;
use FCToernooi\TournamentUser;
use Voetbal\Game\Repository as GameRepository;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteContext;

class GameAdminMiddleware extends AuthorizationTournamentAdminMiddleware
{
    /**
     * @var GameRepository
     */
    protected $gameRepos;

    public function __construct(GameRepository $gameRepos)
    {
        $this->gameRepos = $gameRepos;
    }

    protected function isTournamentUserAuthorized(Request $request, TournamentUser $tournamentUser)
    {
        if ($tournamentUser->hasRoles(Role::GAMERESULTADMIN)) {
            return;
        }
        if ($tournamentUser->hasRoles(Role::REFEREE) === false) {
            throw new \Exception(
                "je bent geen " . Role::getName(Role::REFEREE) . " of " . Role::getName(
                    Role::GAMERESULTADMIN
                ) . " voor dit toernooi", E_ERROR
            );
        }
        $gameId = $this->getGameId($request);
        if ($gameId === null) {
            throw new \Exception("de wedstrijd is niet gevonden", E_ERROR);
        }
        $game = $this->gameRepos->find($gameId);
        if ($game === null) {
            throw new \Exception("de wedstrijd is niet gevonden", E_ERROR);
        }
        if ($game->getReferee() === null) {
            throw new \Exception("bij de wedstrijd is geen scheidsrechter gevonden", E_ERROR);
        }
        if ($game->getReferee()->getEmailaddress() !== $tournamentUser->getUser()->getEmailaddress()) {
            throw new \Exception("voor deze wedstrijd ben je geen " . Role::getName(Role::REFEREE), E_ERROR);
        }
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
}
