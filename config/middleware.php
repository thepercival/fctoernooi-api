<?php

declare(strict_types=1);

use JimTools\JwtAuth\Middleware;
use App\Handlers\DefaultErrorHandler as AppDefaultErrorHandler;
use App\Middleware\CorsMiddleware;
use App\Renderer\ErrorRenderer as AppErrorRenderer;
use App\Response\ErrorResponse;
use JimTools\JwtAuth\Exceptions\AuthorizationException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Psr\Log\LoggerInterface;
use Selective\Config\Configuration;
use Slim\App;
use Slim\Exception\HttpMethodNotAllowedException;
use Slim\Exception\HttpNotFoundException;

// middleware is executed by LIFO

return function (App $app): void {
    $container = $app->getContainer();
    if ($container === null) {
        return;
    }
    /** @var Configuration $config */
    $config = $container->get(Configuration::class);
    //    /** @var LoggerInterface $logger */
    //    $logger = $container->get(LoggerInterface::class);

    $app->add(
        function (Request $request, RequestHandler $handler): Response {
            $response = $handler->handle($request);
            header_remove('X-Powered-By');
            return $response; // ->withoutHeader("X-Powered-By");
        }
    );

    $app->add(Middleware\JwtAuthentication::class);
    $app->add((new Middlewares\ContentType(['html', 'json']))->errorResponse());
    $app->add((new Middlewares\ContentType())->charsets(['UTF-8'])->errorResponse());
    $app->add((new Middlewares\ContentEncoding(['gzip', 'deflate'])));

    // Add Routing Middleware
    $app->addRoutingMiddleware();

    /** @var LoggerInterface $logger */
    $logger = $container->get(LoggerInterface::class);

    $appDefaultErrorHandler = new AppDefaultErrorHandler(
        $app->getCallableResolver(),
        $app->getResponseFactory(),
        $container->get(LoggerInterface::class)
    );

    //    // always last, so it is called first!
    $errorMiddleware = $app->addErrorMiddleware(
        $config->getString('environment') === 'development',
        true,
        true,
        $container->get(LoggerInterface::class)
    );
    $appDefaultErrorHandler->forceContentType('plain/text');
    $appDefaultErrorHandler->registerErrorRenderer('plain/text', AppErrorRenderer::class);
    $errorMiddleware->setDefaultErrorHandler($appDefaultErrorHandler);

    $errorMiddleware->setErrorHandler(
        AuthorizationException::class,
        function (Request $request, Throwable $exception, bool $displayErrorDetails) use ($logger): ErrorResponse {
            $message = $exception->getMessage();
            if ($displayErrorDetails) {
                $previous = $exception->getPrevious();
                if ($previous !== null) {
                    $message = $previous->getMessage();
                }
            }
            return new ErrorResponse($message, 401, $logger);
        }
    );

    // Set the Not Found Handler
    /** @psalm-suppress UnusedClosureParam */
    $errorMiddleware->setErrorHandler(
        HttpNotFoundException::class,
        function (Request $request, Throwable $exception, bool $displayErrorDetails) use ($logger): ErrorResponse {
            return new ErrorResponse($exception->getMessage(), 404, $logger);
        }
    );

    // Set the Not Allowed Handler
    /** @psalm-suppress UnusedClosureParam */
    $errorMiddleware->setErrorHandler(
        HttpMethodNotAllowedException::class,
        function (Request $request, Throwable $exception, bool $displayErrorDetails) use ($logger): ErrorResponse {
            return new ErrorResponse($exception->getMessage(), 405, $logger);
        }
    );

    $app->add(new CorsMiddleware($config->getString('www.wwwurl')));
};
