<?php
declare(strict_types=1);

namespace App\Actions;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Slim\Exception\HttpBadRequestException;
use Slim\Exception\HttpNotFoundException;
use Slim\Exception\HttpMethodNotAllowedException;
use JMS\Serializer\SerializerInterface;

abstract class Action
{
    public function __construct(protected LoggerInterface $logger, protected SerializerInterface $serializer)
    {
    }


//    abstract public function __invoke(Request $request, Response $response, $args): Response;
//    abstract protected function fetchOne( Request $request, Response $response, $args ): Response;
//    abstract protected function fetch( Request $request, Response $response, $args ): Response;
//    abstract protected function add( Request $request, Response $response, $args ): Response;
//    abstract protected function edit( Request $request, Response $response, $args ): Response;
//    abstract protected function remove( Request $request, Response $response, $args ): Response;

    /**
     * @param Request $request
     * @param Response $response
     * @param array<string, int|string> $args
     * @return Response
     */
    public function options(Request $request, Response $response, array $args): Response
    {
        return $response;
    }

    /**
     * @param Request $request
     * @return array<string|int, mixed>|object
     * @throws HttpBadRequestException
     */
    protected function getFormData(Request $request): array|object
    {
        $input = json_decode($this->getRawData());
        if ($input === null) {
            return new \stdClass();
        }

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new HttpBadRequestException($request, 'Malformed JSON input.');
        }

        return $input;
    }

    protected function getRawData(): false|string
    {
        return file_get_contents('php://input');
    }

    /**
     * @param Request $request
     * @param array<string, int|string> $args
     * @param string $name
     * @return string|int
     * @throws HttpBadRequestException
     */
    protected function resolveArg(Request $request, array $args, string $name): string|int
    {
        if (!isset($args[$name])) {
            throw new HttpBadRequestException($request, "Could not resolve argument `{$name}`.");
        }

        return $args[$name];
    }

    protected function respondWithJson(Response $response, string $json): Response
    {
        $response->getBody()->write($json);
        return $response->withHeader('Content-Type', 'application/json');
    }
}
