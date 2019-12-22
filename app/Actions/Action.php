<?php
declare(strict_types=1);

namespace App\Actions;

use App\Domain\DomainException\DomainRecordNotFoundException;
use mysql_xdevapi\Exception;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Slim\Exception\HttpBadRequestException;
use Slim\Exception\HttpNotFoundException;
use Slim\Exception\HttpMethodNotAllowedException;

abstract class Action
{
    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @param LoggerInterface $logger
     */
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

//    /**
//     * @param Request  $request
//     * @param Response $response
//     * @param array    $args
//     * @return Response
//     * @throws HttpNotFoundException
//     * @throws HttpBadRequestException
//     */
//    public function __invoke(Request $request, Response $response, $args): Response
//    {
//        $this->request = $request;
//        $this->response = $response;
//        $this->args = $args;
//
//        try {
//            return $this->action($request, $response, $args);
//        } catch (DomainRecordNotFoundException $e) {
//            throw new HttpNotFoundException($this->request, $e->getMessage());
//        }
//    }
//
//    /**
//     * @return Response
//     * @throws DomainRecordNotFoundException
//     * @throws HttpBadRequestException
//     */
//    abstract protected function fetchOne( Request $request, Response $response, $args ): Response;
//    abstract protected function fetch( Request $request, Response $response, $args ): Response;
//    abstract protected function add( Request $request, Response $response, $args ): Response;
//    abstract protected function edit( Request $request, Response $response, $args ): Response;
//    abstract protected function remove( Request $request, Response $response, $args ): Response;
//
//    protected function action( Request $request, Response $response, $args ): Response
//    {
//        $id = array_key_exists("id", $args) ? $args["id"] : null;
//
//        if ($request->getMethod() === 'GET') {
//            if ($id) {
//                return $this->fetchOne($request, $response, $args);
//            } else {
//                return $this->fetch($request, $response, $args);
//            }
//        } elseif ($request->getMethod() === 'POST') {
//            return $this->add($request, $response, $args);
//        } elseif ($request->getMethod() === 'PUT') {
//            return $this->edit($request, $response, $args);
//        } elseif ($request->getMethod() === 'DELETE') {
//            return $this->remove($request, $response, $args);
//        }
//    }

    /**
     * @return array|object
     * @throws HttpBadRequestException
     */
    protected function getFormData( Request $request )
    {
        $input = json_decode(file_get_contents('php://input'));
        if( $input === null ) {
            return new \stdClass();
        }

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new HttpBadRequestException($request, 'Malformed JSON input.');
        }

        return $input;
    }

    /**
     * @param  string $name
     * @return mixed
     * @throws HttpBadRequestException
     */
    protected function resolveArg( Request $request, $args, string $name)
    {
        if (!isset($args[$name])) {
            throw new HttpBadRequestException($request, "Could not resolve argument `{$name}`.");
        }

        return $args[$name];
    }

    /**
     * @param string $json
     * @return Response
     */
    protected function respondWithJson(Response $response, string $json): Response
    {
        $response->getBody()->write($json);
        return $response->withHeader('Content-Type', 'application/json');
    }
}
