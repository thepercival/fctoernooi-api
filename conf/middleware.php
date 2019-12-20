<?php
declare(strict_types=1);

use App\Middleware\SessionMiddleware;
use App\Middleware\VersionMiddleware;
use Slim\App;

return function (App $app) {
    $app->add(SessionMiddleware::class);
    $app->add(VersionMiddleware::class);

    $x = $app->getContainer()->get("settings");
    $errorMiddleware = $app->addErrorMiddleware( $app->getContainer()->get("settings")['environment'] === "development" , true, true);

    // Set the Not Found Handler
    $errorMiddleware->setErrorHandler(
        HttpNotFoundException::class,
        function (ServerRequestInterface $request, Throwable $exception, bool $displayErrorDetails) {
            $response = new Response();
            $response->getBody()->write('404 NOT FOUND');

            return $response->withStatus(404);
        });

    // Set the Not Allowed Handler
    $errorMiddleware->setErrorHandler(
        HttpMethodNotAllowedException::class,
        function (ServerRequestInterface $request, Throwable $exception, bool $displayErrorDetails) {
            $response = new Response();
            $response->getBody()->write('405 NOT ALLOWED');

            return $response->withStatus(405);
        });
};





