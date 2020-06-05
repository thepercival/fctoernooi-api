<?php

declare(strict_types=1);

use App\Actions\TournamentAction;
use App\Actions\Tournament\ShellAction;
use App\Actions\TournamentUserAction;
use App\Actions\Tournament\InvitationAction;
use App\Actions\AuthAction;
use App\Actions\UserAction;
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
use App\Middleware\Authorization\TournamentGameAdminMiddleware;
use App\Middleware\TournamentNotPublicMiddleware;
use App\Middleware\UserMiddleware;
use App\Middleware\Authorization\TournamentAdminMiddleware;
use App\Middleware\Authorization\TournamentRoleAdminMiddleware;
use Slim\App;
use Slim\Interfaces\RouteCollectorProxyInterface as Group;
use App\Middleware\TournamentMiddleware;

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
            // tournament
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
            )->add(TournamentMiddleware::class);

            $group->options('/shells', ShellAction::class . ':options');
            $group->get('/shells', ShellAction::class . ':fetchPublic');
        }
    );

    $app->group(
        '/auth',
        function (Group $group): void {
            $group->options('/extendtoken', AuthAction::class . ':options');
            $group->post('/extendtoken', AuthAction::class . ':extendToken');
            $group->options('/profile/{userId}', AuthAction::class . ':options');
            $group->put('/profile/{userId}', AuthAction::class . ':profile');
        }
    )->add(UserMiddleware::class);

    $app->group(
        '/users/{userId}',
        function (Group $group): void {
            $group->options('', UserAction::class . ':options');
            $group->get('', UserAction::class . ':fetchOne');
            $group->put('', UserAction::class . ':edit');
            $group->delete('', UserAction::class . ':remove');
        }
    )->add(UserMiddleware::class);

    $app->group(
        '/tournaments',
        function (Group $group): void {
            $group->options('', TournamentAction::class . ':options');
            $group->post('', TournamentAction::class . ':add')->add(UserMiddleware::class);
            $group->options('/{tournamentId}', TournamentAction::class . ':options');
            $group->get('/{tournamentId}', TournamentAction::class . ':fetchOne')
                ->add(TournamentNotPublicMiddleware::class)->add(UserMiddleware::class)->add(
                    TournamentMiddleware::class
                );
            $group->put('/{tournamentId}', TournamentAction::class . ':edit')
                ->add(TournamentAdminMiddleware::class)->add(UserMiddleware::class)->add(TournamentMiddleware::class);
            $group->delete('/{tournamentId}', TournamentAction::class . ':remove')
                ->add(TournamentAdminMiddleware::class)->add(UserMiddleware::class)->add(TournamentMiddleware::class);

            $group->group(
                '/{tournamentId}/',
                function (Group $group): void {
                    $group->options('structure', StructureAction::class . ':options');
                    // user
                    $group->get('structure', StructureAction::class . ':fetchOne')
                        ->add(TournamentNotPublicMiddleware::class)->add(UserMiddleware::class)->add(
                            TournamentMiddleware::class
                        );
                    // admin
                    $group->put('structure', StructureAction::class . ':edit')
                        ->add(TournamentAdminMiddleware::class)->add(UserMiddleware::class)->add(
                            TournamentMiddleware::class
                        );

                    $group->group(
                        'fields',
                        function (Group $group): void {
                            $group->options('', FieldAction::class . ':options');
                            $group->post('', FieldAction::class . ':add');
                            $group->options('/{fieldId}', FieldAction::class . ':options');
                            $group->put('/{fieldId}', FieldAction::class . ':edit');
                            $group->delete('/{fieldId}', FieldAction::class . ':remove');
                        }
                    )->add(TournamentAdminMiddleware::class)->add(UserMiddleware::class)->add(
                        TournamentMiddleware::class
                    );

                    $group->group(
                        'referees',
                        function (Group $group): void {
                            $group->options('/invite/{invite}', RefereeAction::class . ':options');
                            $group->post('/invite/{invite}', RefereeAction::class . ':add');
                            $group->options('/{refereeId}', RefereeAction::class . ':options');
                            $group->put('/{refereeId}', RefereeAction::class . ':edit');
                            $group->delete('/{refereeId}', RefereeAction::class . ':remove');
                        }
                    )->add(TournamentAdminMiddleware::class)->add(UserMiddleware::class)->add(
                        TournamentMiddleware::class
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
                    )->add(TournamentAdminMiddleware::class)->add(UserMiddleware::class)->add(
                        TournamentMiddleware::class
                    );

                    $group->group(
                        'competitors',
                        function (Group $group): void {
                            $group->options('', CompetitorAction::class . ':options');
                            $group->post('', CompetitorAction::class . ':add');
                            $group->options('/{competitorId}', CompetitorAction::class . ':options');
                            $group->put('/{competitorId}', CompetitorAction::class . ':edit');
                        }
                    )->add(TournamentAdminMiddleware::class)->add(UserMiddleware::class)->add(
                        TournamentMiddleware::class
                    );

                    $group->group(
                        'sportscoreconfigs',
                        function (Group $group): void {
                            $group->options('', SportScoreConfigAction::class . ':options');
                            $group->post('', SportScoreConfigAction::class . ':add');
                            $group->options('/{sportscoreconfigId}', SportScoreConfigAction::class . ':options');
                            $group->put('/{sportscoreconfigId}', SportScoreConfigAction::class . ':edit');
                        }
                    )->add(TournamentAdminMiddleware::class)->add(UserMiddleware::class)->add(
                        TournamentMiddleware::class
                    );

                    $group->group(
                        'places',
                        function (Group $group): void {
                            $group->options('/{placeId}', PlaceAction::class . ':options');
                            $group->put('/{placeId}', PlaceAction::class . ':edit');
                        }
                    )->add(TournamentAdminMiddleware::class)->add(UserMiddleware::class)->add(
                        TournamentMiddleware::class
                    );

                    $group->group(
                        'games',
                        function (Group $group): void {
                            $group->options('/{gameId}', GameAction::class . ':options');
                            $group->put('/{gameId}', GameAction::class . ':edit');
                        }
                    )->add(TournamentGameAdminMiddleware::class)->add(UserMiddleware::class)->add(
                        TournamentMiddleware::class
                    );;

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
                    )->add(TournamentAdminMiddleware::class)->add(UserMiddleware::class)->add(
                        TournamentMiddleware::class
                    );

                    $group->group(
                        'planningconfigs/{roundnumber}',
                        function (Group $group): void {
                            $group->options('', PlanningConfigAction::class . ':options');
                            $group->post('', PlanningConfigAction::class . ':add');
                            $group->options('/{planningConfigId}', PlanningConfigAction::class . ':options');
                            $group->put('/{planningConfigId}', PlanningConfigAction::class . ':edit');
                        }
                    )->add(TournamentAdminMiddleware::class)->add(UserMiddleware::class)->add(
                        TournamentMiddleware::class
                    );

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
                            $group->post('/{sponsorId}/upload', SponsorAction::class . ':upload');
                        }
                    )->add(TournamentAdminMiddleware::class)->add(UserMiddleware::class)->add(
                        TournamentMiddleware::class
                    );;

                    $group->group(
                        'lockerrooms',
                        function (Group $group): void {
                            $group->options('', LockerRoomAction::class . ':options');
                            $group->post('', LockerRoomAction::class . ':add');
                            $group->options('/{lockerRoomId}', LockerRoomAction::class . ':options');
                            $group->put('/{lockerRoomId}', LockerRoomAction::class . ':edit');
                            $group->delete('/{lockerRoomId}', LockerRoomAction::class . ':remove');

                            $group->options('/{lockerRoomId}/synccompetitors', LockerRoomAction::class . ':options');
                            $group->post(
                                '/{lockerRoomId}/synccompetitors',
                                LockerRoomAction::class . ':syncCompetitors'
                            );
                        }
                    )->add(TournamentAdminMiddleware::class)->add(UserMiddleware::class)->add(
                        TournamentMiddleware::class
                    );

                    $group->group(
                        'users',
                        function (Group $group): void {
                            $group->options('/{tournamentUserId}', TournamentUserAction::class . ':options');
                            $group->put('/{tournamentUserId}', TournamentUserAction::class . ':edit');
                            $group->delete('/{tournamentUserId}', TournamentUserAction::class . ':remove');
                        }
                    )->add(TournamentRoleAdminMiddleware::class)->add(UserMiddleware::class)->add(
                        TournamentMiddleware::class
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
                    )->add(TournamentRoleAdminMiddleware::class)->add(UserMiddleware::class)->add(
                        TournamentMiddleware::class
                    );

                    $group->group(
                        'userrefereeid',
                        function (Group $group): void {
                            $group->options('', TournamentAction::class . ':options');
                            $group->get('', TournamentAction::class . ':getUserRefereeId');
                        }
                    )->add(TournamentNotPublicMiddleware::class)->add(UserMiddleware::class)->add(
                        TournamentMiddleware::class
                    );

                    $group->group(
                        '',
                        function (Group $group): void {
                            // admin
                            $group->options('sendrequestoldstructure', TournamentAction::class . ':options');
                            $group->post(
                                'sendrequestoldstructure',
                                TournamentAction::class . ':sendRequestOldStructure'
                            );
                            $group->options('exportgeneratehash', TournamentAction::class . ':options');
                            $group->get('exportgeneratehash', TournamentAction::class . ':exportGenerateHash');
                            $group->options('copy', TournamentAction::class . ':options');
                            $group->post('copy', TournamentAction::class . ':copy');
                        }
                    )->add(TournamentAdminMiddleware::class)->add(UserMiddleware::class)->add(
                        TournamentMiddleware::class
                    );
                }
            );
        }
    );

    $app->group(
        '',
        function (Group $group): void {
            $group->options('/shellswithrole', ShellAction::class . ':options');
            $group->get('/shellswithrole', ShellAction::class . ':fetchWithRole');
            $group->options('/shells', ShellAction::class . ':options');
            $group->get('/shells', ShellAction::class . ':fetchPublic');

            $group->options('/sports/{sportCustomId}', SportAction::class . ':options');
            $group->get('/sports/{sportCustomId}', SportAction::class . ':fetchOne');
            $group->options('/sports', SportAction::class . ':options');
            $group->post('/sports', SportAction::class . ':add');
        }
    )->add(UserMiddleware::class);
};
