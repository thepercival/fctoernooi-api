<?php

declare(strict_types=1);

use App\Actions\AuthAction;
use App\Actions\LockerRoomAction;
use App\Actions\PaymentAction;
use App\Actions\PdfAction;
use App\Actions\RecessAction;
use App\Actions\RegistrationAction;
use App\Actions\RegistrationSettingsAction;
use App\Actions\ReportAction;
use App\Actions\SponsorAction;
use App\Actions\RuleAction;
use App\Actions\Sports\AgainstQualifyConfigAction;
use App\Actions\Sports\CompetitionSportAction;
use App\Actions\Sports\CompetitorAction;
use App\Actions\Sports\FieldAction;
use App\Actions\Sports\GameAgainstAction;
use App\Actions\Sports\GameTogetherAction;
use App\Actions\Sports\Planning\ConfigAction as PlanningConfigAction;
use App\Actions\Sports\Planning\GameAmountConfigAction;
use App\Actions\Sports\PlanningAction;
use App\Actions\Sports\RefereeAction;
use App\Actions\Sports\ScoreConfigAction;
use App\Actions\Sports\SportAction;
use App\Actions\Sports\StructureAction;
use App\Actions\Tournament\InvitationAction;
use App\Actions\Tournament\ShellAction;
use App\Actions\TournamentAction;
use App\Actions\TournamentUserAction;
use App\Actions\UserAction;
use App\Middleware\Authorization\Tournament\Admin\AdminMiddleware as TournamentAdminAuthMiddleware;
use App\Middleware\Authorization\Tournament\Admin\GameAdminMiddleware as TournamentGameAdminAuthMiddleware;
use App\Middleware\Authorization\Tournament\Admin\RoleAdminMiddleware as TournamentRoleAdminAuthMiddleware;
use App\Middleware\Authorization\Tournament\PublicMiddleware as TournamentPublicAuthMiddleware;
use App\Middleware\Authorization\Tournament\UserMiddleware as TournamentUserAuthMiddleware;
use App\Middleware\Authorization\UserMiddleware as UserAuthMiddleware;
use App\Middleware\JsonCacheMiddleware;
use App\Middleware\TournamentMiddleware;
use App\Middleware\UserMiddleware;
use App\Middleware\VersionMiddleware;
use Slim\App;
use Slim\Interfaces\RouteCollectorProxyInterface as Group;
use Slim\Views\Twig as TwigView;
use Slim\Views\TwigMiddleware;

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
                    $group->post('/passwordreset', AuthAction::class . ':resetPassword');
                    $group->options('/passwordchange', AuthAction::class . ':options');
                    $group->post('/passwordchange', AuthAction::class . ':changePassword');
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

                    $group->group(
                        '/categories/{categoryId}/registrations',
                        function (Group $group): void {
                            $group->options('', RegistrationAction::class . ':options');
                            $group->post('', RegistrationAction::class . ':add');
                        }
                    )->add(TournamentPublicAuthMiddleware::class)->add(TournamentMiddleware::class)->add(
                        VersionMiddleware::class
                    );

                    $group->group(
                        '/rules',
                        function (Group $group): void {
                            $group->options('', RuleAction::class . ':options');
                            $group->get('', RuleAction::class . ':fetch');
                        }
                    )->add(TournamentPublicAuthMiddleware::class)->add(TournamentMiddleware::class)->add(
                        VersionMiddleware::class
                    );

                    $group->group(
                        '/registrations/settings',
                        function (Group $group): void {
                            $group->options('', RegistrationSettingsAction::class . ':options');
                            $group->get('', RegistrationSettingsAction::class . ':fetchOne');
                        }
                    )->add(TournamentPublicAuthMiddleware::class)
                        ->add(TournamentMiddleware::class)->add(VersionMiddleware::class);
                }
            );

            $group->options('/shells', ShellAction::class . ':options');
            $group->get('/shells', ShellAction::class . ':fetchPublic')->add(VersionMiddleware::class);

            $group->get('/usagereport', ReportAction::class . ':usage')->add(
                TwigMiddleware::createFromContainer($app, TwigView::class)
            );

            $group->options('/paymentUpdate', PaymentAction::class . ':options');
            $group->get('/paymentUpdate', PaymentAction::class . ':update');
            $group->post('/paymentUpdate', PaymentAction::class . ':update');
            $group->options('/paymentRedirect/:paymentId', PaymentAction::class . ':options');
            $group->get('/paymentRedirect/:paymentId', PaymentAction::class . ':redirect');
        }
    );

    $app->group(
        '/auth',
        function (Group $group): void {
            $group->options('/extendtoken', AuthAction::class . ':options');
            $group->post('/extendtoken', AuthAction::class . ':extendToken');
            $group->options('/profile/{userId}', AuthAction::class . ':options');
            $group->put('/profile/{userId}', AuthAction::class . ':profile');
            $group->options('/validate/{code}', AuthAction::class . ':options');
            $group->post('/validate/{code}', AuthAction::class . ':validate');
            $group->options('/validationrequest', AuthAction::class . ':options');
            $group->post('/validationrequest', AuthAction::class . ':validatationRequest');
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
        '/payments',
        function (Group $group): void {
            $group->options('/methods', PaymentAction::class . ':options');
            $group->get('/methods', PaymentAction::class . ':fetchMethods');
            $group->options('/idealissuers', PaymentAction::class . ':options');
            $group->get('/idealissuers', PaymentAction::class . ':fetchIDealIssuers');
            $group->options('/buycredits', PaymentAction::class . ':options');
            $group->post('/buycredits', PaymentAction::class . ':buyCredits');
            $group->options('/mostrecentcreatedpayment', PaymentAction::class . ':options');
            $group->get('/mostrecentcreatedpayment', PaymentAction::class . ':fetchMostRecentCreatedPayment');
            $group->options('/{paymentId}', PaymentAction::class . ':options');
            $group->get('/{paymentId}', PaymentAction::class . ':fetchOne');
        }
    )->add(UserAuthMiddleware::class)->add(UserMiddleware::class)->add(VersionMiddleware::class);

    $app->group(
        '/tournaments',
        function (Group $group): void {
            $group->options('', TournamentAction::class . ':options');
            $group->post('', TournamentAction::class . ':add')->add(UserMiddleware::class);
            $group->options('/{tournamentId}', TournamentAction::class . ':options');
            $group->get('/{tournamentId}', TournamentAction::class . ':fetchOne')
                ->add(UserMiddleware::class)->add(TournamentMiddleware::class);
            $group->put('/{tournamentId}', TournamentAction::class . ':edit')
                ->add(TournamentAdminAuthMiddleware::class)
                ->add(UserMiddleware::class)
                ->add(TournamentMiddleware::class);
            $group->delete('/{tournamentId}', TournamentAction::class . ':remove')
                ->add(TournamentAdminAuthMiddleware::class)
                ->add(UserMiddleware::class)->add(TournamentMiddleware::class);

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
                    $group->options('upload', TournamentAction::class . ':options');
                    $group->post('upload', TournamentAction::class . ':upload')
                        ->add(TournamentAdminAuthMiddleware::class)->add(UserMiddleware::class)->add(
                            TournamentMiddleware::class
                        );

                    $group->options('structure/planningtotals', StructureAction::class . ':options');
                    $group->put('structure/planningtotals', StructureAction::class . ':getPlanningTotals')
                        ->add(TournamentAdminAuthMiddleware::class)->add(UserMiddleware::class)->add(
                            TournamentMiddleware::class
                        );

                    $group->group(
                        'referees',
                        function (Group $group): void {
                            $group->options('/invite/{invite}', RefereeAction::class . ':options');
                            $group->post('/invite/{invite}', RefereeAction::class . ':add');
                            $group->options('/{refereeId}/{invite}', RefereeAction::class . ':options');
                            $group->put('/{refereeId}/{invite}', RefereeAction::class . ':edit');
                            $group->options('/{refereeId}', RefereeAction::class . ':options');
                            $group->delete('/{refereeId}', RefereeAction::class . ':remove');
                            $group->options('/{refereeId}/priorityup', RefereeAction::class . ':options');
                            $group->post('/{refereeId}/priorityup', RefereeAction::class . ':priorityUp');
                            $group->options('/{refereeId}/rolestate', RefereeAction::class . ':options');
                            $group->get('/{refereeId}/rolestate', RefereeAction::class . ':getRoleState');
                        }
                    )->add(TournamentAdminAuthMiddleware::class)->add(UserMiddleware::class)->add(
                        TournamentMiddleware::class
                    );

                    $group->group(
                        'competitionsports',
                        function (Group $group): void {
                            $group->options('', CompetitionSportAction::class . ':options');
                            $group->post('', CompetitionSportAction::class . ':add');

                            $group->group(
                                '/{competitionSportId}',
                                function (Group $group): void {
                                    $group->options('', CompetitionSportAction::class . ':options');
                                    // $group->put('/{competitionSportId}', CompetitionSportAction::class . ':edit');
                                    $group->delete('', CompetitionSportAction::class . ':remove');

                                    $group->group(
                                        '/fields',
                                        function (Group $group): void {
                                            $group->options('', FieldAction::class . ':options');
                                            $group->post('', FieldAction::class . ':add');
                                            $group->options('/{fieldId}', FieldAction::class . ':options');
                                            $group->put('/{fieldId}', FieldAction::class . ':edit');
                                            $group->delete('/{fieldId}', FieldAction::class . ':remove');
                                            $group->options('/{fieldId}/priorityup', FieldAction::class . ':options');
                                            $group->post('/{fieldId}/priorityup', FieldAction::class . ':priorityUp');
                                        }
                                    );

                                    $group->group(
                                        '/games',
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
                                    );
                                }
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
                            $group->post('/{registrationId}', CompetitorAction::class . ':addFromRegistration');
                            $group->options('/{competitorId}', CompetitorAction::class . ':options');
                            $group->get('/{competitorId}', CompetitorAction::class . ':fetchOne');
                            $group->put('/{competitorId}', CompetitorAction::class . ':edit');
                            $group->delete('/{competitorId}', CompetitorAction::class . ':remove');
                            $group->options('/{competitorOneId}/{competitorTwoId}', CompetitorAction::class . ':options');
                            $group->put('/{competitorOneId}/{competitorTwoId}', CompetitorAction::class . ':swap');
                            $group->options('/{competitorId}/upload', CompetitorAction::class . ':options');
                            $group->post('/{competitorId}/upload', CompetitorAction::class . ':upload');
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
                        'planning/{roundNumber}',
                        function (Group $group): void {
                            $group->options('/create', PlanningAction::class . ':options');
                            $group->post('/create', PlanningAction::class . ':create');
                            $group->options('/reschedule', PlanningAction::class . ':options');
                            $group->post('/reschedule', PlanningAction::class . ':reschedule');
                        }
                    )
                        ->add(TournamentAdminAuthMiddleware::class)
                        ->add(UserMiddleware::class)
                        ->add(TournamentMiddleware::class);

                    $group->options('planning/{roundNumber}', PlanningAction::class . ':options');
                    $group->get('planning/{roundNumber}', PlanningAction::class . ':fetch')->add(
                        TournamentMiddleware::class
                    );
                    $group->options('planning/{roundNumber}/progress', PlanningAction::class . ':options');
                    $group->get('planning/{roundNumber}/progress', PlanningAction::class . ':progress')->add(
                        TournamentMiddleware::class
                    );

                    $group->group(
                        'rules',
                        function (Group $group): void {
                            $group->options('', RuleAction::class . ':options');
                            $group->get('', RuleAction::class . ':fetch');
                            $group->post('', RuleAction::class . ':add');
                            $group->options('/{ruleId}', RuleAction::class . ':options');
                            $group->put('/{ruleId}', RuleAction::class . ':edit');
                            $group->delete('/{ruleId}', RuleAction::class . ':remove');

                            $group->options('/{ruleId}/priorityup', RuleAction::class . ':options');
                            $group->post('/{ruleId}/priorityup', RuleAction::class . ':priorityUp');

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
                        'recesses',
                        function (Group $group): void {
                            $group->options('', RecessAction::class . ':options');
                            $group->post('', RecessAction::class . ':add');
                            $group->options('/{recessId}', RecessAction::class . ':options');
                            $group->delete('/{recessId}', RecessAction::class . ':remove');
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

                            $group->options(
                                '/{tournamentUserId}/emailaddress',
                                TournamentUserAction::class . ':options'
                            );
                            $group->get(
                                '/{tournamentUserId}/emailaddress',
                                TournamentUserAction::class . ':getEmailaddress'
                            );
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
                        'categories/{categoryId}/registrations',
                        function (Group $group): void {
                            $group->options('', RegistrationAction::class . ':options');
                            $group->get('', RegistrationAction::class . ':fetch');
                            $group->post('', RegistrationAction::class . ':add');

                            $group->options('/{registrationId}', RegistrationAction::class . ':options');
                            $group->get('/{registrationId}', RegistrationAction::class . ':fetchOne');
                            $group->put('/{registrationId}', RegistrationAction::class . ':edit');
                            $group->delete('/{registrationId}', RegistrationAction::class . ':remove');
                        }
                    )->add(TournamentRoleAdminAuthMiddleware::class)->add(UserMiddleware::class)->add(
                        TournamentMiddleware::class
                    );

                    $group->group(
                        'registrations/settings',
                        function (Group $group): void {
                            $group->options('', RegistrationSettingsAction::class . ':options');
                            $group->get('', RegistrationSettingsAction::class . ':fetchOne');
                            $group->options('/{settingsId}', RegistrationSettingsAction::class . ':options');
                            $group->put('/{settingsId}', RegistrationSettingsAction::class . ':edit');
                        }
                    )->add(TournamentAdminAuthMiddleware::class)->add(UserMiddleware::class)->add(
                        TournamentMiddleware::class
                    );

                    $group->group(
                        'registrationsubjects',
                        function (Group $group): void {
                            $group->options('/{subject}', RegistrationSettingsAction::class . ':options');
                            $group->get('/{subject}', RegistrationSettingsAction::class . ':fetchOneText');
                            $group->put('/{subject}', RegistrationSettingsAction::class . ':editText');
                        }
                    )->add(TournamentAdminAuthMiddleware::class)->add(UserMiddleware::class)->add(
                        TournamentMiddleware::class
                    );

                    $group->group(
                        'userrefereeid',
                        function (Group $group): void {
                            $group->options('', TournamentAction::class . ':options');
                            $group->get('', TournamentAction::class . ':getUserRefereeId');
                        }
                    )->add(TournamentUserAuthMiddleware::class)
                        ->add(UserMiddleware::class)
                        ->add(TournamentMiddleware::class);

                    $group->group(
                        '',
                        function (Group $group): void {
                            $group->options('copy', TournamentAction::class . ':options');
                            $group->post('copy', TournamentAction::class . ':copy');
                        }
                    )->add(UserMiddleware::class)->add(TournamentMiddleware::class);

                    $group->group(
                        'pdf',
                        function (Group $group): void {
                            $group->options('', PdfAction::class . ':options');
                            $group->post('', PdfAction::class . ':create');
                            $group->options('/progress', PdfAction::class . ':options');
                            $group->get('/progress', PdfAction::class . ':progress');
                            $group->options('/apply-service', PdfAction::class . ':options');
                            $group->post('/apply-service', PdfAction::class . ':applyService');
                        }
                    )
                        ->add(TournamentRoleAdminAuthMiddleware::class)
                        ->add(UserMiddleware::class)
                        ->add(TournamentMiddleware::class);
                }
            );
        }
    )->add(JsonCacheMiddleware::class)->add(VersionMiddleware::class);

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
