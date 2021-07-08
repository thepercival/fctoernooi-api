<?php

declare(strict_types=1);

use App\Actions\Sports\ScoreConfigAction;
use App\Actions\TournamentAction;
use App\Actions\ReportAction;
use App\Actions\Tournament\ShellAction;
use App\Actions\TournamentUserAction;
use App\Actions\Tournament\InvitationAction;
use App\Actions\AuthAction;
use App\Actions\UserAction;
use App\Actions\SponsorAction;
use App\Actions\LockerRoomAction;
use App\Actions\Sports\StructureAction;
use App\Actions\Sports\PlanningAction;
use App\Actions\Sports\Planning\ConfigAction as PlanningConfigAction;
use App\Actions\Sports\SportAction;
use App\Actions\Sports\FieldAction;
use App\Actions\Sports\GameAgainstAction;
use App\Actions\Sports\GameTogetherAction;
use App\Actions\Sports\RefereeAction;
use App\Actions\Sports\CompetitorAction;
use App\Actions\Sports\CompetitionSportAction;
use App\Actions\Sports\AgainstQualifyConfigAction;
use App\Actions\Sports\Planning\GameAmountConfigAction;
use App\Middleware\TournamentMiddleware;
use App\Middleware\UserMiddleware;
use App\Middleware\Authorization\UserMiddleware as UserAuthMiddleware;
use App\Middleware\Authorization\Tournament\UserMiddleware as TournamentUserAuthMiddleware;
use App\Middleware\Authorization\Tournament\Admin\AdminMiddleware as TournamentAdminAuthMiddleware;
use App\Middleware\Authorization\Tournament\Admin\RoleAdminMiddleware as TournamentRoleAdminAuthMiddleware;
use App\Middleware\Authorization\Tournament\Admin\GameAdminMiddleware as TournamentGameAdminAuthMiddleware;
use App\Middleware\Authorization\Tournament\PublicMiddleware as TournamentPublicAuthMiddleware;
use App\Middleware\VersionMiddleware;
use Slim\App;
use Slim\Interfaces\RouteCollectorProxyInterface as Group;
use Slim\Views\TwigMiddleware;
use Slim\Views\Twig as TwigView;

return function (App $app): void {
    $app->group(
        '/public',
        function (Group $group) use ($app): void {
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
            )->add(VersionMiddleware::class);

            // tournament
            $group->group(
                '/tournaments/{tournamentId}',
                function (Group $group): void {
                    $group->options('', TournamentAction::class . ':options');
                    $group->get('', TournamentAction::class . ':fetchOne')
                        ->add(TournamentPublicAuthMiddleware::class)->add(TournamentMiddleware::class)->add(
                            VersionMiddleware::class
                        );

                    $group->options('/structure', StructureAction::class . ':options');
                    $group->get('/structure', StructureAction::class . ':fetchOne')
                        ->add(TournamentPublicAuthMiddleware::class)->add(TournamentMiddleware::class)->add(
                            VersionMiddleware::class
                        );

                    $group->options('/export', TournamentAction::class . ':options');
                    $group->get('/export', TournamentAction::class . ':export')->setName('tournament-export')
                        ->add(TournamentMiddleware::class);
                }
            );

            $group->options('/shells', ShellAction::class . ':options');
            $group->get('/shells', ShellAction::class . ':fetchPublic')->add(VersionMiddleware::class);

            $group->get('/usagereport', ReportAction::class . ':usage')->add(
                TwigMiddleware::createFromContainer($app, TwigView::class)
            );
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
    )->add(UserAuthMiddleware::class)->add(UserMiddleware::class)->add(VersionMiddleware::class);

    $app->group(
        '/users/{userId}',
        function (Group $group): void {
            $group->options('', UserAction::class . ':options');
            $group->get('', UserAction::class . ':fetchOne');
            $group->put('', UserAction::class . ':edit');
            $group->delete('', UserAction::class . ':remove');
        }
    )->add(UserAuthMiddleware::class)->add(UserMiddleware::class)->add(VersionMiddleware::class);

    $app->group(
        '/tournaments',
        function (Group $group): void {
            $group->options('', TournamentAction::class . ':options');
            $group->post('', TournamentAction::class . ':add')->add(UserMiddleware::class);
            $group->options('/{tournamentId}', TournamentAction::class . ':options');
            $group->get('/{tournamentId}', TournamentAction::class . ':fetchOne')
                ->add(UserMiddleware::class)->add(
                    TournamentMiddleware::class
                );
            $group->put('/{tournamentId}', TournamentAction::class . ':edit')
                ->add(TournamentAdminAuthMiddleware::class)->add(UserMiddleware::class)->add(
                    TournamentMiddleware::class
                );
            $group->delete('/{tournamentId}', TournamentAction::class . ':remove')
                ->add(TournamentAdminAuthMiddleware::class)->add(UserMiddleware::class)->add(
                    TournamentMiddleware::class
                );

            $group->group(
                '/{tournamentId}/',
                function (Group $group): void {
                    $group->options('structure', StructureAction::class . ':options');
                    // user
                    $group->get('structure', StructureAction::class . ':fetchOne')
                        ->add(UserMiddleware::class)->add(
                            TournamentMiddleware::class
                        );
                    // admin
                    $group->put('structure', StructureAction::class . ':edit')
                        ->add(TournamentAdminAuthMiddleware::class)->add(UserMiddleware::class)->add(
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
                            $group->options('/{refereeId}/priorityup', RefereeAction::class . ':options');
                            $group->post('/{refereeId}/priorityup', RefereeAction::class . ':priorityUp');
                        }
                    )->add(TournamentAdminAuthMiddleware::class)->add(UserMiddleware::class)->add(
                        TournamentMiddleware::class
                    );

                    $group->group(
                        'competitionsports',
                        function (Group $group): void {
                            $group->options('', CompetitionSportAction::class . ':options');
                            $group->post('', CompetitionSportAction::class . ':add');
                            $group->options('/{competitionSportId}', CompetitionSportAction::class . ':options');
                            // $group->put('/{competitionSportId}', CompetitionSportAction::class . ':edit');
                            $group->delete('/{competitionSportId}', CompetitionSportAction::class . ':remove');

                            $group->group(
                                '/{competitionSportId}/fields',
                                function (Group $group): void {
                                    $group->options('', FieldAction::class . ':options');
                                    $group->post('', FieldAction::class . ':add');
                                    $group->options('/{fieldId}', FieldAction::class . ':options');
                                    $group->put('/{fieldId}', FieldAction::class . ':edit');
                                    $group->delete('/{fieldId}', FieldAction::class . ':remove');
                                    $group->options('/{fieldId}/priorityup', FieldAction::class . ':options');
                                    $group->post('/{fieldId}/priorityup', FieldAction::class . ':priorityUp');
                                }
                            )->add(TournamentAdminAuthMiddleware::class)->add(UserMiddleware::class)->add(
                                TournamentMiddleware::class
                            );
                        }
                    )->add(TournamentAdminAuthMiddleware::class)->add(UserMiddleware::class)->add(
                        TournamentMiddleware::class
                    );

                    $group->group(
                        'competitors',
                        function (Group $group): void {
                            $group->options('', CompetitorAction::class . ':options');
                            $group->get('', CompetitorAction::class . ':fetch');
                            $group->post('', CompetitorAction::class . ':add');
                            $group->options('/{competitorId}', CompetitorAction::class . ':options');
                            $group->put('/{competitorId}', CompetitorAction::class . ':edit');
                            $group->delete('/{competitorId}', CompetitorAction::class . ':remove');
                            $group->options('/{competitorOneId}/{competitorTwoId}', CompetitorAction::class . ':options');
                            $group->put('/{competitorOneId}/{competitorTwoId}', CompetitorAction::class . ':swap');
                        }
                    )->add(TournamentAdminAuthMiddleware::class)->add(UserMiddleware::class)->add(
                        TournamentMiddleware::class
                    );

                    $group->group(
                        'roundnumbers/{roundNumber}',
                        function (Group $group): void {
                            $group->group(
                                '/planningconfigs',
                                function (Group $group): void {
                                    $group->options('', PlanningConfigAction::class . ':options');
                                    $group->post('', PlanningConfigAction::class . ':save');
                                }
                            );

                            $group->group(
                                '/competitionsports/{competitionSportId}/gameamountconfigs',
                                function (Group $group): void {
                                    $group->options('', GameAmountConfigAction::class . ':options');
                                    $group->post('', GameAmountConfigAction::class . ':save');
                                }
                            );
                        }
                    )->add(TournamentAdminAuthMiddleware::class)->add(UserMiddleware::class)->add(
                        TournamentMiddleware::class
                    );

                    $group->group(
                        'rounds/{roundId}',
                        function (Group $group): void {
                            $group->group(
                                '/competitionsports/{competitionSportId}',
                                function (Group $group): void {
                                    $group->group(
                                        '/scoreconfigs',
                                        function (Group $group): void {
                                            $group->options('', ScoreConfigAction::class . ':options');
                                            $group->post('', ScoreConfigAction::class . ':save');
                                        }
                                    );
                                    $group->group(
                                        '/qualifyagainstconfigs',
                                        function (Group $group): void {
                                            $group->options('', AgainstQualifyConfigAction::class . ':options');
                                            $group->post('', AgainstQualifyConfigAction::class . ':save');
                                        }
                                    );
                                }
                            );
                        }
                    )->add(TournamentAdminAuthMiddleware::class)->add(UserMiddleware::class)->add(
                        TournamentMiddleware::class
                    );

                    $group->group(
                        'games',
                        function (Group $group): void {
                            $group->group(
                                'against',
                                function (Group $group): void {
                                    $group->options('', GameAgainstAction::class . ':options');
                                    $group->post('', GameAgainstAction::class . ':add');
                                    $group->options('/{gameId}', GameAgainstAction::class . ':options');
                                    $group->put('/{gameId}', GameAgainstAction::class . ':edit');
                                    $group->delete('/{gameId}', GameAgainstAction::class . ':remove');
                                }
                            );
                            $group->group(
                                'together',
                                function (Group $group): void {
                                    $group->options('', GameTogetherAction::class . ':options');
                                    $group->post('', GameTogetherAction::class . ':add');
                                    $group->options('/{gameId}', GameTogetherAction::class . ':options');
                                    $group->put('/{gameId}', GameTogetherAction::class . ':edit');
                                    $group->delete('/{gameId}', GameTogetherAction::class . ':remove');
                                }
                            );
                        }
                    )->add(TournamentGameAdminAuthMiddleware::class)->add(UserMiddleware::class)->add(
                        TournamentMiddleware::class
                    );

                    $group->group(
                        'planning/{roundNumber}',
                        function (Group $group): void {
                            $group->options('', PlanningAction::class . ':options');
                            $group->get('', PlanningAction::class . ':fetch');
                            $group->options('/create', PlanningAction::class . ':options');
                            $group->post('/create', PlanningAction::class . ':create');
                            $group->options('/reschedule', PlanningAction::class . ':options');
                            $group->post('/reschedule', PlanningAction::class . ':reschedule');
                            $group->options('/progress', PlanningAction::class . ':options');
                            $group->get('/progress', PlanningAction::class . ':progress');
                        }
                    )->add(TournamentAdminAuthMiddleware::class)->add(UserMiddleware::class)->add(
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
                    )->add(TournamentAdminAuthMiddleware::class)->add(UserMiddleware::class)->add(
                        TournamentMiddleware::class
                    );

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
                    )->add(TournamentAdminAuthMiddleware::class)->add(UserMiddleware::class)->add(
                        TournamentMiddleware::class
                    );

                    $group->group(
                        'users',
                        function (Group $group): void {
                            $group->options('/{tournamentUserId}', TournamentUserAction::class . ':options');
                            $group->put('/{tournamentUserId}', TournamentUserAction::class . ':edit');
                            $group->delete('/{tournamentUserId}', TournamentUserAction::class . ':remove');
                        }
                    )->add(TournamentRoleAdminAuthMiddleware::class)->add(UserMiddleware::class)->add(
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
                    )->add(TournamentRoleAdminAuthMiddleware::class)->add(UserMiddleware::class)->add(
                        TournamentMiddleware::class
                    );

                    $group->group(
                        'userrefereeid',
                        function (Group $group): void {
                            $group->options('', TournamentAction::class . ':options');
                            $group->get('', TournamentAction::class . ':getUserRefereeId');
                        }
                    )->add(TournamentUserAuthMiddleware::class)->add(UserMiddleware::class)->add(
                        TournamentMiddleware::class
                    );

                    $group->group(
                        '',
                        function (Group $group): void {
                            $group->options('exportgeneratehash', TournamentAction::class . ':options');
                            $group->get('exportgeneratehash', TournamentAction::class . ':exportGenerateHash');
                            $group->options('copy', TournamentAction::class . ':options');
                            $group->post('copy', TournamentAction::class . ':copy');
                        }
                    )->add(TournamentAdminAuthMiddleware::class)->add(UserMiddleware::class)->add(
                        TournamentMiddleware::class
                    );
                }
            );
        }
    )->add(VersionMiddleware::class);

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
            $group->get('/sports', SportAction::class . ':fetch');
            $group->post('/sports', SportAction::class . ':add');
        }
    )->add(UserAuthMiddleware::class)->add(UserMiddleware::class)->add(VersionMiddleware::class);
};
