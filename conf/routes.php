<?php
declare(strict_types=1);

use App\Actions\Tournament as TournamentAction;
use App\Actions\Tournament\Shell as TournamentShellAction;
use App\Actions\Auth as AuthAction;
use App\Actions\User as UserAction;
use App\Actions\Sponsor as SponsorAction;
use App\Actions\Voetbal\StructureAction;
use App\Actions\Voetbal\PlanningAction;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;
use Slim\Interfaces\RouteCollectorProxyInterface as Group;

return function (App $app) {
    // Routes

    $app->group('/auth', function ( Group $group ) {
        $group->options('/register', AuthAction::class . ':options');
        $group->post('/register', AuthAction::class . ':register');
        $group->options('/login', AuthAction::class . ':options');
        $group->post('/login', AuthAction::class . ':login');
        $group->options('/validatetoken', AuthAction::class . 'options');
        $group->post('/validatetoken', AuthAction::class . ':validateToken');
        /*$group->post('/auth/activate', 'AuthAction::class . ':activate');*/
        $group->options('/passwordreset', AuthAction::class . ':options');
        $group->post('/passwordreset', AuthAction::class . ':passwordreset');
        $group->options('/passwordchange', AuthAction::class . ':options');
        $group->post('/passwordchange', AuthAction::class . ':passwordchange');
    });

    // admin module
//    $app->group('/users', function ( Group $group ) {
//        $group->options('', UserAction::class . ':options');
//        $group->get('', UserAction::class . ':fetch');
//        $group->options('/{id}', UserAction::class . ':options');
//        $group->get('/{id}', UserAction::class . ':fetchOne');
//    });

    //    $app->group('/roles', function ( Group $group ) {
//        $group->get('', 'App\Action\Role\User:fetch');
//        $group->get('/{id}', 'App\Action\Role:fetchOne');
//    });

    $app->group('/tournaments', function ( Group $group )  {
        $group->options('/{id}', TournamentAction::class . ':options');
        $group->get('/{id}', TournamentAction::class . ':fetchOne');
        $group->options('', TournamentAction::class . ':options');
        $group->post('', TournamentAction::class . ':add');
        // $group->options('/{id}', TournamentAction::class . ':options');
        $group->put('/{id}', TournamentAction::class . ':edit');
        // $group->options('/{id}', TournamentAction::class . ':options');
        $group->delete('/{id}', TournamentAction::class . ':remove');
        $group->options('/syncrefereeroles/{id}', TournamentAction::class . ':options');
        $group->post('/syncrefereeroles/{id}', TournamentAction::class . ':syncRefereeRoles');                  // POSTMAN NOT FINISHED
        $group->options('/sendrequestoldstructure/{id}', TournamentAction::class . ':options');
        $group->post('/sendrequestoldstructure/{id}', TournamentAction::class . ':sendRequestOldStructure');    // POSTMAN NOT FINISHED
        $group->options('/userrefereeid/{id}', TournamentAction::class . ':options');
        $group->get('/userrefereeid/{id}', TournamentAction::class . ':getUserRefereeId');                      // POSTMAN NOT FINISHED
        $group->options('/export/{id}', TournamentAction::class . ':options');
        $group->get('/export/{id}', TournamentAction::class . ':export');                                       // POSTMAN NOT FINISHED
        $group->options('/copy/{id}', TournamentAction::class . ':options');
        $group->post('/copy/{id}', TournamentAction::class . ':copy');                                          // POSTMAN NOT FINISHED

        $group->group('/shells', function ( Group $group ) {
            $group->options('/', TournamentShellAction::class . ':options');
            $group->get('/', TournamentShellAction::class . ':fetch');
            $group->options('/withroles', TournamentShellAction::class . ':options');
            $group->get('/withroles', TournamentShellAction::class . ':fetchWithRoles');
        });
    });

    $app->group('/tournamentspublic', function ( Group $group ) {
        $group->options('', TournamentAction::class . ':options');             // @TODO #DEPRECATED
        $group->get('', TournamentAction::class . ':fetch');             // @TODO #DEPRECATED
        $group->options('/{id}', TournamentAction::class . ':options');
        $group->get('/{id}', TournamentAction::class . ':fetchOnePublic');
        $group->options('/pdf/{id}', TournamentAction::class . ':options');       // @TODO #DEPRECATED
        $group->get('/pdf/{id}', TournamentAction::class . ':fetchPdf');       // @TODO #DEPRECATED
    });

    $app->group('/sponsors', function ( Group $group ) {
        $group->options('', SponsorAction::class . ':options');
        $group->get('/', SponsorAction::class . ':fetch');
        $group->get('/{id}', SponsorAction::class . ':fetchOne');
        $group->post('', SponsorAction::class . ':add');
        $group->options('/{id}', SponsorAction::class . ':options');
        $group->put('/{id}', SponsorAction::class . ':edit');
        $group->delete('/{id}', SponsorAction::class . ':remove');
        $group->options('/upload/', SponsorAction::class . ':options');
        $group->post('/upload/', SponsorAction::class . ':upload');         // POSTMAN NOT FINISHED
    });

    // deze verplaatsen naar fctoernooi
    //   $app->any('/voetbal/{resourceType}[/{id}]', VoetbalApp\Action\Slim\Handler::class ); // POSTMAN TODO


//    $app->group('/voetbal', function ( Group $voetbalGroup ) {
//        $voetbalGroup->group('/structures', function ( Group $group ) {
//            $group->get('/{id}', StructureAction::class . ':fetchOne');
//            $group->put('/{id}', StructureAction::class . ':edit');
//        });
//
//        $voetbalGroup->group('/planning', function ( Group $group ) {
//            $group->get('/{id}', PlanningAction::class . ':fetch');
//            $group->post('/{id}', PlanningAction::class . ':add');
//            $group->put('/{id}', PlanningAction::class . ':edit');
//        });
//    });
};