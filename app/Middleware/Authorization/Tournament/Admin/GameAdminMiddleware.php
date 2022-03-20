<?php

declare(strict_types=1);

namespace App\Middleware\Authorization\Tournament\Admin;

use App\Middleware\Authorization\Tournament\AdminMiddleware as AuthorizationTournamentAdminMiddleware;
use FCToernooi\Role;
use FCToernooi\TournamentUser;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteContext;
use Sports\Game\Against as AgainstGame;
use Sports\Game\Against\Repository as AgainstGameRepository;
use Sports\Game\Together as TogetherGame;
use Sports\Game\Together\Repository as TogetherGameRepository;

class GameAdminMiddleware extends AuthorizationTournamentAdminMiddleware
{
    public function __construct(
        protected TogetherGameRepository $togetherGameRepos,
        protected AgainstGameRepository $againstGameRepos,
    ) {
    }

    protected function isTournamentUserAuthorized(Request $request, TournamentUser $tournamentUser): void
    {
        if ($tournamentUser->hasRoles(Role::GAMERESULTADMIN)) {
            return;
        }
        if ($tournamentUser->hasRoles(Role::REFEREE) === false) {
            throw new \Exception(
                'je bent geen ' . Role::getName(Role::REFEREE) .
                ' of ' . Role::getName(Role::GAMERESULTADMIN) .
                ' voor dit toernooi',
                E_ERROR
            );
        }
        $referee = $this->getGame($request)->getReferee();
        if ($referee === null) {
            throw new \Exception('bij de wedstrijd is geen scheidsrechter gevonden', E_ERROR);
        }
        if ($referee->getEmailaddress() !== $tournamentUser->getUser()->getEmailaddress()) {
            throw new \Exception('voor deze wedstrijd ben je geen ' . Role::getName(Role::REFEREE), E_ERROR);
        }
    }

    protected function getGame(Request $request): AgainstGame|TogetherGame
    {
        $gameId = $this->getGameId($request);
        if ($gameId === null) {
            throw new \Exception('de wedstrijd is niet gevonden', E_ERROR);
        }
        $repos = $this->isAgainst($request) ? $this->againstGameRepos : $this->togetherGameRepos;
        $game = $repos->find($gameId);
        if ($game === null) {
            throw new \Exception('de wedstrijd is niet gevonden', E_ERROR);
        }
        return $game;
    }

    protected function getGameId(Request $request): ?int
    {
        $routeContext = RouteContext::fromRequest($request);
        $routingResults = $routeContext->getRoutingResults();
        $args = $routingResults->getRouteArguments();
        if (!array_key_exists('gameId', $args)) {
            return null;
        }
        return (int)$args['gameId'];
    }

    protected function isAgainst(Request $request): bool
    {
        $routeContext = RouteContext::fromRequest($request);
        $routingResults = $routeContext->getRoutingResults();
        return mb_strpos($routingResults->getUri(), 'against') !== false;
    }
}
