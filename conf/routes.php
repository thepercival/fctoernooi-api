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

    $app->group('/public', function ( Group $group ) {
        $group->group('/auth', function ( Group $group ) {
            $group->options('/register', AuthAction::class . ':options');
            $group->post('/register', AuthAction::class . ':register');
            $group->options('/login', AuthAction::class . ':options');
            $group->post('/login', AuthAction::class . ':login');
            $group->options('/passwordreset', AuthAction::class . ':options');
            $group->post('/passwordreset', AuthAction::class . ':passwordreset');
            $group->options('/passwordchange', AuthAction::class . ':options');
            $group->post('/passwordchange', AuthAction::class . ':passwordchange');
        });
        $group->group('/tournaments/', function ( Group $group ) {
            $group->options('{tournamentId}', TournamentAction::class . ':options');
            $group->get('{tournamentId}', TournamentAction::class . ':fetchOnePublic');

            $group->group('{tournamentId}/', function ( Group $group ) {
                $group->options('structure', StructureAction::class . ':options');
                $group->get('structure', StructureAction::class . ':fetchOne');
            });
        });
        $group->options('/shells', TournamentShellAction::class . ':options');
        $group->get('/shells', TournamentShellAction::class . ':fetch');
    });

    $app->group('/auth', function ( Group $group ) {
        $group->options('/validatetoken', AuthAction::class . 'options');
        $group->post('/validatetoken', AuthAction::class . ':validateToken');
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
        $group->options('/{tournamentId}', TournamentAction::class . ':options');
        $group->get('/{tournamentId}', TournamentAction::class . ':fetchOne');
        $group->options('/', TournamentAction::class . ':options');
        $group->post('/', TournamentAction::class . ':add');
        // $group->options('/{tournamentId}', TournamentAction::class . ':options');
        $group->put('/{tournamentId}', TournamentAction::class . ':edit');
        // $group->options('/{tournamentId}', TournamentAction::class . ':options');
        $group->delete('/{tournamentId}', TournamentAction::class . ':remove');

        $group->group('/{tournamentId}/', function ( Group $group ) {

            $group->options('syncrefereeroles', TournamentAction::class . ':options');
            $group->post('syncrefereeroles', TournamentAction::class . ':syncRefereeRoles');
            $group->options('sendrequestoldstructure', TournamentAction::class . ':options');
            $group->post('sendrequestoldstructure', TournamentAction::class . ':sendRequestOldStructure');
            $group->options('userrefereeid', TournamentAction::class . ':options');
            $group->get('userrefereeid', TournamentAction::class . ':getUserRefereeId');
            $group->options('export', TournamentAction::class . ':options');
            $group->get('export', TournamentAction::class . ':export');                                       // POSTMAN NOT FINISHED
            $group->options('copy', TournamentAction::class . ':options');
            $group->post('copy', TournamentAction::class . ':copy');

            $group->group('sponsors/', function ( Group $group ) {
                $group->options('', SponsorAction::class . ':options');
                $group->get('', SponsorAction::class . ':fetch');
                $group->get('{sponsorId}', SponsorAction::class . ':fetchOne');
                $group->post('', SponsorAction::class . ':add');
                $group->options('{sponsorId}', SponsorAction::class . ':options');
                $group->put('{sponsorId}', SponsorAction::class . ':edit');
                $group->delete('{sponsorId}', SponsorAction::class . ':remove');
                $group->options('{sponsorId}/upload', SponsorAction::class . ':options');
                $group->post('{sponsorId}/upload', SponsorAction::class . ':upload');         // POSTMAN NOT FINISHED
            });


            $group->options('structure', StructureAction::class . ':options');
            $group->get('structure', StructureAction::class . ':fetchOne');
            $group->put('structure', StructureAction::class . ':edit');


//
//        $voetbalGroup->group('/planning', function ( Group $group ) {
//            $group->get('/{id}', PlanningAction::class . ':fetch');
//            $group->post('/{id}', PlanningAction::class . ':add');
//            $group->put('/{id}', PlanningAction::class . ':edit');
//        });
        });

    });

    $app->group('/shells', function ( Group $group ) {
        $group->options('/', TournamentShellAction::class . ':options');
        $group->get('/', TournamentShellAction::class . ':fetchWithRoles');
    });


};