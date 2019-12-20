<?php
declare(strict_types=1);

use App\Actions\Tournament as TournamentAction;
use App\Application\Actions\User\ViewUserAction;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;
use Slim\Interfaces\RouteCollectorProxyInterface as Group;

return function (App $app) {
    $app->get('/', function (Request $request, Response $response) {
        $response->getBody()->write('Hello world!');
        return $response;
    });

    $app->group('/tournaments', function (Group $group) {
        $group->get('', TournamentAction::class);
        $group->get('/{id}', TournamentAction::class);
    });
};