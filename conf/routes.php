<?php
declare(strict_types=1);

use App\Actions\Tournament as TournamentAction;
use App\Actions\Tournament\Shell as TournamentShellAction;
use App\Actions\Auth as AuthAction;
use App\Actions\User as UserAction;
use App\Actions\Sponsor as SponsorAction;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;
use Slim\Interfaces\RouteCollectorProxyInterface as Group;

return function (App $app) {
    // Routes
// deze verplaatsen naar fctoernooi
  //   $app->any('/voetbal/{resourceType}[/{id}]', VoetbalApp\Action\Slim\Handler::class );

    $app->group('/auth', function ( Group $group ) {
        $group->post('/register', AuthAction::class . ':register');
        $group->post('/login', AuthAction::class . ':login');
        $group->post('/validatetoken', AuthAction::class . 'validateToken');
        /*$group->post('/auth/activate', 'AuthAction::class . ':activate');*/
        $group->post('/passwordreset', AuthAction::class . ':passwordreset');
        $group->post('/passwordchange', AuthAction::class . ':passwordchange');
    });

    $app->group('/users', function ( Group $group ) {
        $group->get('', UserAction::class . ':fetch');
        $group->get('/{id}', UserAction::class . ':fetchOne');
    });

    $app->group('/tournaments', function ( Group $group )  {
        $group->post('', TournamentAction::class . ':add');
        $group->get('/{id}', TournamentAction::class . ':fetchOne');
        $group->put('/{id}', TournamentAction::class . ':edit');
        $group->delete('/{id}', TournamentAction::class . ':remove');
        $group->post('/syncrefereeroles/{id}', TournamentAction::class . ':syncRefereeRoles');
        $group->post('/sendrequestoldstructure/{id}', TournamentAction::class . ':sendRequestOldStructure');
        $group->get('/userrefereeid/{id}', TournamentAction::class . ':getUserRefereeId');
        $group->get('/export/{id}', TournamentAction::class . ':export');
        $group->post('/copy/{id}', TournamentAction::class . ':copy');
    });

    $app->group('/tournamentspublic', function ( Group $group ) {
        $group->get('', TournamentShellAction::class . ':fetch');             // @TODO #DEPRECATED
        $group->get('/{id}', TournamentShellAction::class . ':fetchOnePublic');
        $group->get('/pdf/{id}', TournamentShellAction::class . ':fetchPdf');       // @TODO #DEPRECATED
    });

    $app->get('/tournamentshells', TournamentShellAction::class . ':fetch');
    $app->get('/tournamentshellswithroles', TournamentShellAction::class . ':fetchWithRoles');

//    $app->group('/roles', function ( Group $group ) {
//        $group->get('', 'App\Action\Role\User:fetch');
//        $group->get('/{id}', 'App\Action\Role:fetchOne');
//    });

    $app->group('/sponsors', function ( Group $group ) {
        $group->post('', SponsorAction::class . ':add');
        $group->get('', SponsorAction::class . ':fetch');
        $group->get('/{id}', SponsorAction::class . ':fetchOne');
        $group->put('/{id}', SponsorAction::class . ':edit');
        $group->delete('/{id}', SponsorAction::class . ':remove');

        $group->post('/upload/', SponsorAction::class . ':upload');
    });
};