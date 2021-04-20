<?php
declare(strict_types=1);

namespace App\Handlers;

use Exception;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Slim\Exception\HttpException;
use Slim\Exception\HttpForbiddenException;
use Slim\Exception\HttpMethodNotAllowedException;
use Slim\Exception\HttpNotFoundException;
use Slim\Exception\HttpNotImplementedException;
use Slim\Exception\HttpUnauthorizedException;
use Slim\Handlers\ErrorHandler as SlimErrorHandler;
use Slim\Interfaces\CallableResolverInterface;
use Throwable;

class HttpErrorHandler extends SlimErrorHandler
{
    /**
     * @param CallableResolverInterface $callableResolver
     * @param ResponseFactoryInterface  $responseFactory
     * @param LoggerInterface|null      $logger
     */
    public function __construct(
        CallableResolverInterface $callableResolver,
        ResponseFactoryInterface $responseFactory,
        ?LoggerInterface $logger = null
    ) {
        parent::__construct($callableResolver, $responseFactory, $logger );
    }

    /**
     * @inheritdoc
     */
    protected function respond(): ResponseInterface
    {
        $exception = $this->exception;
        $statusCode = 500;
        $message = 'An internal error has occurred while processing your request.';
//        $error = new ActionError(
//            ActionError::SERVER_ERROR,
//            'An internal error has occurred while processing your request.'
//        );

        if ($exception instanceof HttpException) {
            $statusCode = $exception->getCode();
            $message = $exception->getMessage();
            // $error->setDescription($exception->getMessage());

//            if ($exception instanceof HttpNotFoundException) {
//                $error->setType(ActionError::RESOURCE_NOT_FOUND);
//            } elseif ($exception instanceof HttpMethodNotAllowedException) {
//                $error->setType(ActionError::NOT_ALLOWED);
//            } elseif ($exception instanceof HttpUnauthorizedException) {
//                $error->setType(ActionError::UNAUTHENTICATED);
//            } elseif ($exception instanceof HttpForbiddenException) {
//                $error->setType(ActionError::INSUFFICIENT_PRIVILEGES);
//            } elseif ($exception instanceof HttpBadRequestException) {
//                $error->setType(ActionError::BAD_REQUEST);
//            } elseif ($exception instanceof HttpNotImplementedException) {
//                $error->setType(ActionError::NOT_IMPLEMENTED);
//            }
        }

        if (
            !($exception instanceof HttpException)
            && ($exception instanceof Exception /*|| $exception instanceof Throwable*/)
            && $this->displayErrorDetails
        ) {
            $message = $exception->getMessage();
            // $error->setDescription($exception->getMessage());
        }

//        $payload = new ActionPayload($statusCode, null, $error);
//        $encodedPayload = json_encode($payload, JSON_PRETTY_PRINT);

        $encodedPayload = json_encode(["error" => $message ], JSON_PRETTY_PRINT);
        $encodedPayload = $encodedPayload === false ? '' : $encodedPayload;

        $response = $this->responseFactory->createResponse($statusCode);
        $response->getBody()->write($encodedPayload);

        return $response->withHeader('Content-Type', 'application/json');
    }
}
