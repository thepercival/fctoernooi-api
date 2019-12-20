<?php
declare(strict_types=1);

namespace App\Actions;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpBadRequestException;
use FCToernooi\Tournament\Repository as TournamentRepository;
use App\Exceptions\DomainRecordNotFoundException;
use Psr\Log\LoggerInterface;
use JMS\Serializer\SerializerInterface;
use JMS\Serializer\SerializationContext;

class Tournament extends Action
{
    /**
     * @var TournamentRepository
     */
    protected $tournamentRepository;

    /**
     * @var SerializerInterface
     */
    protected $serializer;

    /**
     * @param LoggerInterface $logger
     * @param TournamentRepository  $tournamentRepository
     */
    public function __construct(LoggerInterface $logger, TournamentRepository $tournamentRepository, SerializerInterface $serializer )
    {
        parent::__construct($logger);
        $this->tournamentRepository = $tournamentRepository;
        $this->serializer = $serializer;
    }

    /**
     * @return Response
     * @throws DomainRecordNotFoundException
     * @throws HttpBadRequestException
     */
    protected function fetchOne( Request $request, Response $response, $args ): Response
    {
        /** @var \FCToernooi\Tournament|null $tournament */
        $tournament = $this->tournamentRepository->find($args['id']);
        if ($tournament === null) {
            throw new DomainRecordNotFoundException("geen toernooi met het opgegeven id gevonden", E_ERROR);
        }

        $json = $this->serializer->serialize( $tournament, 'json', SerializationContext::create()->setGroups(['Default']));
//        return $response
//            ->withHeader('Content-Type', 'application/json;charset=utf-8')
//            ->write($this->serializer->serialize( $tournament, 'json', $this->getSerializationContext($tournament, $user)));
//        ;

        return $this->respondWithJson($json);
    }

    protected function fetch( Request $request, Response $response, $args ): Response
    {
        /** @var \FCToernooi\Tournament|null $tournament */
//        $tournament = $this->repos->find($args['id']);
//        if ($tournament === null) {
//            throw new \Exception("geen toernooi met het opgegeven id gevonden", E_ERROR);
//        }
        return $this->respondWithJson(json_encode(['key1'=>'value1',
            "key2" => ["12", "12"]], JSON_PRETTY_PRINT ) );
//            return $response
//                ->withHeader('Content-Type', 'application/json;charset=utf-8')
//                ->write($this->serializer->serialize( $tournament, 'json', $this->getSerializationContext($tournament, $user)));
//            ;

    }

    protected function add( Request $request, Response $response, $args ): Response
    {
        return $this->respondWithData(["key"=>"value"]);
    }

    protected function edit( Request $request, Response $response, $args ): Response
    {
        return $this->respondWithData(["key"=>"value"]);
    }

    protected function remove( Request $request, Response $response, $args ): Response
    {
        return $this->respondWithData(["key"=>"value"]);
    }
}
