<?php
/**
 * Created by PhpStorm.
 * User: coen
 * Date: 28-3-18
 * Time: 20:31
 */

namespace App\Middleware;

use FCToernooi\Tournament;
use FCToernooi\User\Repository as UserRepos;
use FCToernooi\Tournament\Repository as TournamentRepos;
use FCToernooi\Tournament\Service as TournamentService;
use FCToernooi\Auth\Token as AuthToken;
use FCToernooi\User;
use App\Response\Forbidden as ForbiddenResponse;
use Voetbal\Service as VoetbalService;
use FCToernooi\Role;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

class AuthenticationMiddleware
{
    /**
     * @var UserRepos
     */
    protected $userRepos;
    /**
     * @var TournamentRepos
     */
    protected $tournamentRepos;
    /**
     * @var TournamentService
     */
    protected $tournamentService;
    /**
     * @var VoetbalService
     */
    protected $voetbalService;

    public function __construct(
        UserRepos $userRepos,
        TournamentRepos $tournamentRepos,
        TournamentService $tournamentService,
        VoetbalService $voetbalService
    ) {
        $this->userRepos = $userRepos;
        $this->tournamentRepos = $tournamentRepos;
        $this->tournamentService = $tournamentService;
        $this->voetbalService = $voetbalService;
    }

    public function __invoke(Request $request, RequestHandler $handler): Response
    {
        // $request = $request->withAttribute('foo2', 'bar2');
        $token = $request->getAttribute('token');
        if ($token === null || $token->isPopulated() !== true) {
            return $handler->handle($request);
        }

        // THIS SHOULD WORK ALSO CHECK TOKEN WITH DEBUGSESSIONS if (in_array("delete", $token["scope"])) {


        if (substr($request->getUri()->getPath(), 0, 12) === "/tournaments"
            || substr($request->getUri()->getPath(), 0, 9) === "/sponsors"
            || substr($request->getUri()->getPath(), 0, 5) === "/auth"
            /*|| substr($request->getUri()->getPath(), 0, 30) === "/voetbal/planning/hasbeenfound"*/
        )
        {
            return $handler->handle($request);
        }

        return new ForbiddenResponse("routeInfo is deprecated", 401);
        $args = $request->getAttribute('routeInfo')[2];
        if (array_key_exists('resourceType', $args) === false) {
            return new ForbiddenResponse("niet geautoriseerd, het pad kan niet bepaalt worden", 401);
        }
        $resourceType = $args['resourceType'];

        $user = $this->getUser();
        if ($user === null) {
            return new ForbiddenResponse("gebruiker kan niet gevonden worden", 401);
        }
        $id = (is_array($args) && array_key_exists('id', $args) && ctype_digit($args['id'])) ? (int)$args['id'] : null;
        if (!$this->authorized($user, $resourceType, $request->getMethod(), $request->getQueryParams(), $id)) {
            return new ForbiddenResponse("geen autorisatie voor actie gevonden", 401);
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

    protected function authorized(User $user, string $resourceType, string $method, array $queryParams, int $id = null)
    {
        // for $resourceType === 'structures' ->add/edit need to check in the action if round->competition === competitionSend
        if ($resourceType === 'competitors') {
            return $this->competitorActionAuthorized($user, $method, $queryParams);
        } elseif ($resourceType === 'places') {
            return $this->placeActionAuthorized($user, $method, $queryParams, $id);
        } elseif ($resourceType === 'games') {
            return $this->gameActionAuthorized($user, $method, $queryParams, $id);
        } elseif ($resourceType === 'sports') {
            return true;
        } elseif ($resourceType === 'fields' || $resourceType === 'planning' || $resourceType === 'referees'
            || $resourceType === 'structures' || $resourceType === 'sportconfigs' || $resourceType === 'planningconfigs'
            || $resourceType === 'sportscoreconfigs'
        ) {
            return $this->otherActionAuthorized($user, $queryParams);
        }
        return false;
    }

    protected function competitorActionAuthorized(User $user, string $method, array $queryParams)
    {
        if (array_key_exists("associationid", $queryParams) !== true) {
            return false;
        }
        if ($method !== 'POST' && $method !== 'PUT') {
            return false;
        }
        $assRepos = $this->voetbalService->getRepository(\Voetbal\Association::class);
        $association = $assRepos->find($queryParams["associationid"]);
        if ($association === null) {
            return false;
        }
        if( $this->tournamentService->mayUserChangeCompetitor( $user, $association ) === false ) {
            return false;
        }

        return true;
    }

    protected function placeActionAuthorized(User $user, string $method, array $queryParams, int $id = null)
    {
        if ($method !== 'PUT') {
            return false;
        }
        return $this->otherActionAuthorized($user, $queryParams);
    }

    protected function gameActionAuthorized(User $user, string $method, array $queryParams, int $id = null)
    {
        if ($method !== 'PUT') {
            return false;
        }
        if (array_key_exists("competitionid", $queryParams) !== true) {
            return false;
        }
        $tournament = $this->tournamentRepos->findOneBy(["competition" => $queryParams["competitionid"]]);
        if ($tournament === null) {
            return false;
        }
        if ($tournament->hasRole($user, Role::GAMERESULTADMIN)) {
            return true;
        }
        if (!$tournament->hasRole($user, Role::REFEREE)) {
            return false;
        }
        $gameRepos = $this->voetbalService->getRepository(\Voetbal\Game::class);
        $game = $gameRepos->find($id);
        if ($game === null || $game->getReferee() === null ) {
            return false;
        }
        if( $game->getReferee()->getEmailaddress() === $user->getEmailaddress() ) {
            return true;
        }
        return false;
    }

    protected function otherActionAuthorized(User $user, array $queryParams): bool
    {
        if (array_key_exists("competitionid", $queryParams) !== true) {
            return false;
        }
        $tournament = $this->tournamentRepos->findOneBy(["competition" => $queryParams["competitionid"]]);
        if ($tournament === null) {
            return false;
        }
        if (!$tournament->hasRole($user, Role::ADMIN)) {
            return false;
        }
        return true;
    }
}