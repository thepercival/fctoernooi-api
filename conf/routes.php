<?php

// Routes
//$app->any('/voetbal/external/{resourceType}[/{id}]', \Voetbal\App\Action\Slim\ExternalHandler::class );
$app->any('/voetbal/{resourceType}[/{id}]', VoetbalApp\Action\Slim\Handler::class );

$app->group('/auth', function () use ($app) {
	$app->post('/register', 'App\Action\Auth:register');
	$app->post('/login', 'App\Action\Auth:login');
    $app->post('/validatetoken', 'App\Action\Auth:validateToken');
    /*$app->post('/auth/activate', 'App\Action\Auth:activate');*/
	$app->post('/passwordreset', 'App\Action\Auth:passwordreset');
	$app->post('/passwordchange', 'App\Action\Auth:passwordchange');
});

$app->group('/users', function () use ($app) {
    $app->get('', 'App\Action\User:fetch');
    $app->get('/{id}', 'App\Action\User:fetchOne');
});

$app->group('/tournaments', function () use ($app) {
    $app->post('', 'App\Action\Tournament:add');
    $app->get('/{id}', 'App\Action\Tournament:fetchOne');
    $app->put('/{id}', 'App\Action\Tournament:edit');
    $app->delete('/{id}', 'App\Action\Tournament:remove');
    $app->post('/syncrefereeroles/{id}', 'App\Action\Tournament:syncRefereeRoles');
    $app->get('/userrefereeid/{id}', 'App\Action\Tournament:getUserRefereeId');
    $app->get('/pdf/{id}', 'App\Action\Tournament:fetchPdf');
    $app->post('/copy/{id}', 'App\Action\Tournament:copy');
});

$app->group('/tournamentspublic', function () use ($app) {
    $app->get('', 'App\Action\TournamentShell:fetch');             // #DEPRECATED
    $app->get('/{id}', 'App\Action\Tournament:fetchOnePublic');
    $app->get('/pdf/{id}', 'App\Action\Tournament:fetchPdf');       // #DEPRECATED
});

$app->get('/tournamentshells', 'App\Action\Tournament\Shell:fetch');
$app->get('/tournamentshellswithroles', 'App\Action\Tournament\Shell:fetchWithRoles');

$app->group('/roles', function () use ($app) {
    $app->get('', 'App\Action\Role\User:fetch');
    $app->get('/{id}', 'App\Action\Role:fetchOne');
});

$app->group('/sponsors', function () use ($app) {
    $app->post('', 'App\Action\Sponsor:add');
    $app->get('', 'App\Action\Sponsor:fetch');
    $app->get('/{id}', 'App\Action\Sponsor:fetchOne');
    $app->put('/{id}', 'App\Action\Sponsor:edit');
    $app->delete('/{id}', 'App\Action\Sponsor:remove');

    $app->post('/upload/', 'App\Action\Sponsor:upload');
});