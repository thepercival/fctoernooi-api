<?php

declare(strict_types=1);

use App\Actions\TournamentAction;
use App\Actions\Tournament\ShellAction;
use App\Actions\AuthAction;
use App\Actions\SponsorAction;
use App\Actions\Voetbal\StructureAction;
use App\Actions\Voetbal\PlanningAction;
use App\Actions\Voetbal\Planning\ConfigAction as PlanningConfigAction;
use App\Actions\Voetbal\SportAction;
use App\Actions\Voetbal\FieldAction;
use App\Actions\Voetbal\PlaceAction;
use App\Actions\Voetbal\GameAction;
use App\Actions\Voetbal\RefereeAction;
use App\Actions\Voetbal\CompetitorAction;
use App\Actions\Voetbal\Sport\ConfigAction as SportConfigAction;
use App\Actions\Voetbal\Sport\ScoreConfigAction as SportScoreConfigAction;
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

            $group->group(
                '{tournamentId}/',
                function (Group $group) {
                    $group->options('structure', StructureAction::class . ':options');
                    $group->get('structure', StructureAction::class . ':fetchOne');
                    $group->options('export', TournamentAction::class . ':options');
                    $group->get('export', TournamentAction::class . ':export')->setName('tournament-export');
                }
            );
        }
        );
        $group->options('/shells', ShellAction::class . ':options');
        $group->get('/shells', ShellAction::class . ':fetchPublic');
    });

    $app->group('/auth', function ( Group $group ) {
        $group->options('/extendtoken', AuthAction::class . ':options');
        $group->post('/extendtoken', AuthAction::class . ':extendToken');
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
        $group->options('', TournamentAction::class . ':options');
        $group->post('', TournamentAction::class . ':add');
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
            $group->options('exportgeneratehash', TournamentAction::class . ':options');
            $group->get('exportgeneratehash', TournamentAction::class . ':exportGenerateHash');
            $group->options('copy', TournamentAction::class . ':options');
            $group->post('copy', TournamentAction::class . ':copy');

            $group->group(
                'sponsors',
                function (Group $group) {
                    $group->options('', SponsorAction::class . ':options');
                    $group->get('', SponsorAction::class . ':fetch');
                    $group->get('/{sponsorId}', SponsorAction::class . ':fetchOne');
                    $group->post('', SponsorAction::class . ':add');
                    $group->options('/{sponsorId}', SponsorAction::class . ':options');
                    $group->put('/{sponsorId}', SponsorAction::class . ':edit');
                    $group->delete('/{sponsorId}', SponsorAction::class . ':remove');
                    $group->options('/{sponsorId}/upload', SponsorAction::class . ':options');
                    $group->post(
                        '/{sponsorId}/upload',
                        SponsorAction::class . ':upload'
                    );         // POSTMAN NOT FINISHED
                });

            $group->options('structure', StructureAction::class . ':options');
            $group->get('structure', StructureAction::class . ':fetchOne');
            $group->put('structure', StructureAction::class . ':edit');

            $group->group('fields', function ( Group $group ) {
                $group->options('', FieldAction::class . ':options');
                $group->post('', FieldAction::class . ':add');
                $group->options('/{fieldId}', FieldAction::class . ':options');
                $group->put('/{fieldId}', FieldAction::class . ':edit');
                $group->delete('/{fieldId}', FieldAction::class . ':remove');
            });

            $group->group('referees', function ( Group $group ) {
                $group->options('', RefereeAction::class . ':options');
                $group->post('', RefereeAction::class . ':add');
                $group->options('/{refereeId}', RefereeAction::class . ':options');
                $group->put('/{refereeId}', RefereeAction::class . ':edit');
                $group->delete('/{refereeId}', RefereeAction::class . ':remove');
            });

            $group->group('sportconfigs', function ( Group $group ) {
                $group->options('', SportConfigAction::class . ':options');
                $group->post('', SportConfigAction::class . ':add');
                $group->options('/{sportconfigId}', SportConfigAction::class . ':options');
                $group->put('/{sportconfigId}', SportConfigAction::class . ':edit');
                $group->delete('/{sportconfigId}', SportConfigAction::class . ':remove');
            });

            $group->group('competitors', function ( Group $group ) {
                $group->options('', CompetitorAction::class . ':options');
                $group->post('', CompetitorAction::class . ':add');
                $group->options('/{competitorId}', CompetitorAction::class . ':options');
                $group->put('/{competitorId}', CompetitorAction::class . ':edit');
            }
            );

            $group->group(
                'sportscoreconfigs',
                function (Group $group) {
                    $group->options('', SportScoreConfigAction::class . ':options');
                    $group->post('', SportScoreConfigAction::class . ':add');
                    $group->options('/{sportscoreconfigId}', SportScoreConfigAction::class . ':options');
                    $group->put('/{sportscoreconfigId}', SportScoreConfigAction::class . ':edit');
                }
            );

            $group->group(
                'places',
                function (Group $group) {
                    $group->options('/{placeId}', PlaceAction::class . ':options');
                    $group->put('/{placeId}', PlaceAction::class . ':edit');
                }
            );

            $group->group(
                'games',
                function (Group $group) {
                    $group->options('/{gameId}', GameAction::class . ':options');
                    $group->put('/{gameId}', GameAction::class . ':edit');
                }
            );

            $group->group(
                'planning/{roundnumber}',
                function (Group $group) {
                    $group->options('', PlanningAction::class . ':options');
                    $group->get('', PlanningAction::class . ':fetch');
                    $group->options('/create', PlanningAction::class . ':options');
                    $group->post('/create', PlanningAction::class . ':create');
                    $group->options('/reschedule', PlanningAction::class . ':options');
                    $group->post('/reschedule', PlanningAction::class . ':reschedule');
                }
            );

            $group->group(
                'planningconfigs/{roundnumber}',
                function (Group $group) {
                    $group->options('', PlanningConfigAction::class . ':options');
                    $group->post('', PlanningConfigAction::class . ':add');
                    $group->options('/{planningConfigId}', PlanningConfigAction::class . ':options');
                    $group->put('/{planningConfigId}', PlanningConfigAction::class . ':edit');
                }
            );
        }
        );
    }
    );

    $app->options('/myshells', ShellAction::class . ':options');
    $app->get('/myshells', ShellAction::class . ':fetchMine');
    $app->options('/shells', ShellAction::class . ':options');
    $app->get('/shells', ShellAction::class . ':fetchPublic');

    $app->options('/sports/{sportCustomId}', SportAction::class . ':options');
    $app->get('/sports/{sportCustomId}', SportAction::class . ':fetchOne');
    $app->options('/sports', SportAction::class . ':options');
    $app->post('/sports', SportAction::class . ':add');
};