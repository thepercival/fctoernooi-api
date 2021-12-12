<?php

declare(strict_types=1);

use App\Middleware\CorsMiddleware;
use App\Response\ErrorResponse;
use App\Response\UnauthorizedResponse;
use FCToernooi\Auth\Token as AuthToken;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Psr\Log\LoggerInterface;
use Selective\Config\Configuration;
use Slim\App;
use Slim\Exception\HttpMethodNotAllowedException;
use Slim\Exception\HttpNotFoundException;
use Tuupola\Middleware\JwtAuthentication;

// middleware is executed by LIFO

return function (App $app): void {
    $container = $app->getContainer();
    if ($container === null) {
        return;
    }
    /** @var Configuration $config */
    $config = $container->get(Configuration::class);

    $app->add(
        function (Request $request, RequestHandler $handler): Response {
            $response = $handler->handle($request);
            header_remove('X-Powered-By');
            return $response; // ->withoutHeader("X-Powered-By");
        }
    );

    $app->add(
        new JwtAuthentication(
            [
                'secret' => $config->getString('auth.jwtsecret'),
                'logger' => $container->get(LoggerInterface::class),
                'rules' => [
                    new JwtAuthentication\RequestPathRule(
                        [
                            'path' => '/',
                            'ignore' => ['/public']
                        ]
                    ),
                    new JwtAuthentication\RequestMethodRule(['ignore' => ['OPTIONS']])
                ],
                'error' => function (Response $response, array $args): UnauthorizedResponse {
                    /** @var string $message */
                    $message = $args['message'];
                    return new UnauthorizedResponse($message);
                },
                'before' => function (Request $request, array $args): Request {
                    if (is_array($args['decoded']) && count($args['decoded']) > 0) {
                        /** @var array<string, string|int> $decoded */
                        $decoded = $args['decoded'];
                        $token = new AuthToken($decoded);
                        return $request->withAttribute('token', $token);
                    }
                    return $request;
                }
            ]
        )
    );

    $app->add((new Middlewares\ContentType(['html', 'json']))->errorResponse());
    $app->add((new Middlewares\ContentType())->charsets(['UTF-8'])->errorResponse());
    $app->add((new Middlewares\ContentEncoding(['gzip', 'deflate'])));

    $app->add(new CorsMiddleware($config->getString('www.wwwurl')));

    // Add Routing Middleware
    $app->addRoutingMiddleware();

//    // always last, so it is called first!
    $errorMiddleware = $app->addErrorMiddleware($config->getString('environment') === 'development', true, true);

    // Set the Not Found Handler
    $errorMiddleware->setErrorHandler(
        HttpNotFoundException::class,
        function (Request $request, Throwable $exception, bool $displayErrorDetails): ErrorResponse {
            return new ErrorResponse($exception->getMessage(), 404);
        }
    );

    // Set the Not Allowed Handler
    $errorMiddleware->setErrorHandler(
        HttpMethodNotAllowedException::class,
        function (Request $request, Throwable $exception, bool $displayErrorDetails): ErrorResponse {
            return new ErrorResponse($exception->getMessage(), 405);
        }
    );
};
