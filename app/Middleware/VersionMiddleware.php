<?php
declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface as Middleware;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use App\Response\ErrorResponse;

class VersionMiddleware implements Middleware
{
    /**
     * {@inheritdoc}
     */
    public function process(Request $request, RequestHandler $handler): Response
    {
        if ($request->getMethod() === "OPTIONS") {
            return $handler->handle($request);
        }
        $apiVersion = $request->getHeaderLine('X-Api-Version');
        if ($apiVersion !== "24") {
            return new ErrorResponse("de app/website moet vernieuwd worden, ververs de pagina", 418);
        }
        return $handler->handle($request);
    }
}
