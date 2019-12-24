<?php
declare(strict_types=1);

use App\Middleware\VersionMiddleware;
use App\Middleware\AuthenticationMiddleware;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use App\Response\UnauthorizedResponse;
use Gofabian\Negotiation\NegotiationMiddleware;
use Tuupola\Middleware\JwtAuthentication;
use Tuupola\Middleware\CorsMiddleware;
use Slim\Exception\HttpMethodNotAllowedException;
use Slim\Exception\HttpNotFoundException;
use Psr\Log\LoggerInterface;
use Slim\App;
use FCToernooi\Auth\Settings as AuthSettings;
use FCToernooi\Auth\Token as AuthToken;

// middleware is executed by LIFO

return function (App $app) {
    $app->add(function (Request $request, RequestHandler $handler): Response {
        $response = $handler->handle($request);
        header_remove("X-Powered-By");
        return $response; // ->withoutHeader('X-Powered-By');
    });

    $app->add( AuthenticationMiddleware::class);

    $app->add( new JwtAuthentication([
            "secret" => $app->getContainer()->get( AuthSettings::class )->getJwtSecret(),
            "logger" => $app->getContainer()->get( LoggerInterface::class ),
            "rules" => [
                new JwtAuthentication\RequestPathRule([
                    "path" => "/",
                    "ignore" => [
                        "/auth/register", "/auth/login","/auth/passwordreset","/auth/passwordchange",
                        "/tournamentshells", "/tournamentspublic", "/tournaments/export",
                        "/voetbal/structures", "/voetbal/sports"
                    ]
                ]),
                new JwtAuthentication\RequestMethodRule([
                    "ignore" => ["OPTIONS"]
                ])
            ],
            "error" => function(Response $response, $arguments) {
                return new UnauthorizedResponse($arguments["message"]);
            },
            "before" => function ( Request $request, $arguments) {
                $token = new AuthToken( $arguments["decoded"] );
                return $request->withAttribute("token", $token);
            }
        ])
    );

    $app->add(VersionMiddleware::class);

    $app->add( new CorsMiddleware([
            "logger" => $app->getContainer()->get( LoggerInterface::class ),
            "origin" => $app->getContainer()->get('settings')['www']['urls'],
            "methods" => ["GET", "POST", "PUT", "PATCH", "DELETE"],
            "headers.allow" => ["Authorization", "If-Match", "If-Unmodified-Since","Content-Type","X-Api-Version"],
            "headers.expose" => ["Authorization", "Etag"],
            "credentials" => true,
            "cache" => 300,
            "error" => function (Request $request, Response $response, $arguments) {
                return new UnauthorizedResponse($arguments["message"]);
            }
        ])
    );

    $app->add( (new Middlewares\ContentType([/*'html',*/ 'json']))->errorResponse() );

//    $app->add(new NegotiationMiddleware([
//        'accept' => ['text/html', 'application/json'],
//        'accept-language' => ['en', 'de-DE'],
//        'accept-encoding' => ['gzip'],
//        'accept-charset' => ['utf-8']
//    ]));

//    // always last, so it is called first!
    $errorMiddleware = $app->addErrorMiddleware( $app->getContainer()->get("settings")['environment'] === "development" , true, true);

    // Set the Not Found Handler
    $errorMiddleware->setErrorHandler(
        HttpNotFoundException::class,
        function (Request $request, Throwable $exception, bool $displayErrorDetails) {
            $response = new Response();
            $response->getBody()->write('404 NOT FOUND');
            return $response->withStatus(404);
        });

    // Set the Not Allowed Handler
    $errorMiddleware->setErrorHandler(
        HttpMethodNotAllowedException::class,
        function (Request $request, Throwable $exception, bool $displayErrorDetails) {
            $response = new Response();
            $response->getBody()->write('405 NOT ALLOWED');
            return $response->withStatus(405);
        });
};





