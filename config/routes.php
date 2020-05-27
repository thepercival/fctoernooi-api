<?php

declare(strict_types=1);

use App\Actions\TournamentAction;
use App\Actions\Tournament\ShellAction;
use App\Actions\TournamentUserAction;
use App\Actions\Tournament\InvitationAction;
use App\Actions\AuthAction;
use App\Actions\SponsorAction;
use App\Actions\LockerRoomAction;
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

return function (App $app): void {
    $app->group(
        '/public',
        function (Group $group): void {
            $group->group(
                '/auth',
                function (Group $group): void {
                    $group->options('/register', AuthAction::class . ':options');
                    $group->post('/register', AuthAction::class . ':register');
                    $group->options('/login', AuthAction::class . ':options');
                    $group->post('/login', AuthAction::class . ':login');
                    $group->options('/passwordreset', AuthAction::class . ':options');
                    $group->post('/passwordreset', AuthAction::class . ':passwordreset');
                    $group->options('/passwordchange', AuthAction::class . ':options');
                    $group->post('/passwordchange', AuthAction::class . ':passwordchange');
                }
            );
            $group->group(
                '/tournaments/',
                function (Group $group): void {
                    $group->options('{tournamentId}', TournamentAction::class . ':options');
                    $group->get('{tournamentId}', TournamentAction::class . ':fetchOnePublic');

                    $group->group(
                        '{tournamentId}/',
                        function (Group $group): void {
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

    $app->group(
        '/auth',
        function (Group $group): void {
            $group->options('/extendtoken', AuthAction::class . ':options');
            $group->post('/extendtoken', AuthAction::class . ':extendToken');
        }
    );

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

    $app->group(
        '/tournaments',
        function (Group $group): void {
            $group->options('/{tournamentId}', TournamentAction::class . ':options');
            $group->get('/{tournamentId}', TournamentAction::class . ':fetchOne');
            $group->options('', TournamentAction::class . ':options');
            $group->post('', TournamentAction::class . ':add');
            // $group->options('/{tournamentId}', TournamentAction::class . ':options');
            $group->put('/{tournamentId}', TournamentAction::class . ':edit');
            // $group->options('/{tournamentId}', TournamentAction::class . ':options');
            $group->delete('/{tournamentId}', TournamentAction::class . ':remove');

            $group->group(
                '/{tournamentId}/',
                function (Group $group): void {
                    $group->options('sendrequestoldstructure', TournamentAction::class . ':options');
                    $group->post('sendrequestoldstructure', TournamentAction::class . ':sendRequestOldStructure');
                    $group->options('userrefereeid', TournamentAction::class . ':options');
                    $group->get('userrefereeid', TournamentAction::class . ':getUserRefereeId');
                    $group->options('exportgeneratehash', TournamentAction::class . ':options');
                    $group->get('exportgeneratehash', TournamentAction::class . ':exportGenerateHash');
                    $group->options('lockerrooms', TournamentAction::class . ':options');
                    $group->post('lockerrooms', TournamentAction::class . ':saveLockerRooms');
                    $group->options('copy', TournamentAction::class . ':options');
                    $group->post('copy', TournamentAction::class . ':copy');

                    $group->group(
                        'sponsors',
                        function (Group $group): void {
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
                        }
                    );

                    $group->options('structure', StructureAction::class . ':options');
                    $group->get('structure', StructureAction::class . ':fetchOne');
                    $group->put('structure', StructureAction::class . ':edit');

                    $group->group(
                        'fields',
                        function (Group $group): void {
                            $group->options('', FieldAction::class . ':options');
                            $group->post('', FieldAction::class . ':add');
                            $group->options('/{fieldId}', FieldAction::class . ':options');
                            $group->put('/{fieldId}', FieldAction::class . ':edit');
                            $group->delete('/{fieldId}', FieldAction::class . ':remove');
                        }
                    );

                    $group->group(
                        'referees',
                        function (Group $group): void {
                            $group->options('', RefereeAction::class . ':options');
                            $group->post('', RefereeAction::class . ':add');
                            $group->options('/{refereeId}', RefereeAction::class . ':options');
                            $group->put('/{refereeId}', RefereeAction::class . ':edit');
                            $group->delete('/{refereeId}', RefereeAction::class . ':remove');
                        }
                    );

                    $group->group(
                        'sportconfigs',
                        function (Group $group): void {
                            $group->options('', SportConfigAction::class . ':options');
                            $group->post('', SportConfigAction::class . ':add');
                            $group->options('/{sportconfigId}', SportConfigAction::class . ':options');
                            $group->put('/{sportconfigId}', SportConfigAction::class . ':edit');
                            $group->delete('/{sportconfigId}', SportConfigAction::class . ':remove');
                        }
                    );

                    $group->group(
                        'competitors',
                        function (Group $group): void {
                            $group->options('', CompetitorAction::class . ':options');
                            $group->post('', CompetitorAction::class . ':add');
                            $group->options('/{competitorId}', CompetitorAction::class . ':options');
                            $group->put('/{competitorId}', CompetitorAction::class . ':edit');
                        }
                    );

                    $group->group(
                        'sportscoreconfigs',
                        function (Group $group): void {
                            $group->options('', SportScoreConfigAction::class . ':options');
                            $group->post('', SportScoreConfigAction::class . ':add');
                            $group->options('/{sportscoreconfigId}', SportScoreConfigAction::class . ':options');
                            $group->put('/{sportscoreconfigId}', SportScoreConfigAction::class . ':edit');
                        }
                    );

                    $group->group(
                        'places',
                        function (Group $group): void {
                            $group->options('/{placeId}', PlaceAction::class . ':options');
                            $group->put('/{placeId}', PlaceAction::class . ':edit');
                        }
                    );

                    $group->group(
                        'games',
                        function (Group $group): void {
                            $group->options('/{gameId}', GameAction::class . ':options');
                            $group->put('/{gameId}', GameAction::class . ':edit');
                        }
                    );

                    $group->group(
                        'planning/{roundnumber}',
                        function (Group $group): void {
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
                        function (Group $group): void {
                            $group->options('', PlanningConfigAction::class . ':options');
                            $group->post('', PlanningConfigAction::class . ':add');
                            $group->options('/{planningConfigId}', PlanningConfigAction::class . ':options');
                            $group->put('/{planningConfigId}', PlanningConfigAction::class . ':edit');
                        }
                    );

                    $group->group(
                        'users',
                        function (Group $group): void {
                            $group->options('', TournamentUserAction::class . ':options');
                            $group->post('', TournamentUserAction::class . ':add');
                            $group->options('/{tournamentuserId}', TournamentUserAction::class . ':options');
                            $group->put('/{tournamentuserId}', TournamentUserAction::class . ':edit');
                            $group->delete('/{tournamentuserId}', TournamentUserAction::class . ':remove');
                        }
                    );

                    $group->group(
                        'invitations',
                        function (Group $group): void {
                            $group->options('', InvitationAction::class . ':options');
                            $group->get('', InvitationAction::class . ':fetch');
                            $group->post('', InvitationAction::class . ':add');
                            $group->options('/{invitationId}', InvitationAction::class . ':options');
                            $group->put('/{invitationId}', InvitationAction::class . ':edit');
                            $group->delete('/{invitationId}', InvitationAction::class . ':remove');
                        }
                    );
                }
            );
        }
    );

    $app->options('/shellswithrole', ShellAction::class . ':options');
    $app->get('/shellswithrole', ShellAction::class . ':fetchWithRole');
    $app->options('/shells', ShellAction::class . ':options');
    $app->get('/shells', ShellAction::class . ':fetchPublic');

    $app->options('/sports/{sportCustomId}', SportAction::class . ':options');
    $app->get('/sports/{sportCustomId}', SportAction::class . ':fetchOne');
    $app->options('/sports', SportAction::class . ':options');
    $app->post('/sports', SportAction::class . ':add');
};
