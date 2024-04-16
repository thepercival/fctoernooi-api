<?php

declare(strict_types=1);

namespace App\Actions\Sports;

use App\Actions\Action;
use App\Response\ErrorResponse;
use Exception;
use JMS\Serializer\DeserializationContext;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\SerializerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Sports\Sport;
use Sports\Sport\Repository as SportRepository;

final class SportAction extends Action
{
    public function __construct(
        LoggerInterface $logger,
        SerializerInterface $serializer,
        protected SportRepository $sportRepos
    ) {
        parent::__construct($logger, $serializer);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param array<string, int|string> $args
     * @return Response
     */
    public function fetch(Request $request, Response $response, array $args): Response
    {
        try {
            $sports = $this->sportRepos->findByExt(true);
            $json = $this->serializer->serialize($sports, 'json');
            return $this->respondWithJson($response, $json);
        } catch (Exception $exception) {
            return new ErrorResponse($exception->getMessage(), 400, $this->logger);
        }
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param array<string, int|string> $args
     * @return Response
     */
    public function fetchOne(Request $request, Response $response, array $args): Response
    {
        $sport = $this->sportRepos->findOneBy(["customId" => (int)$args['sportCustomId']]);
        if ($sport === null) {
            throw new Exception("geen sport met het opgegeven id gevonden", E_ERROR);
        }

        $json = $this->serializer->serialize($sport, 'json');
        return $this->respondWithJson($response, $json);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param array<string, int|string> $args
     * @return Response
     */
    public function add(Request $request, Response $response, array $args): Response
    {
        try {
            /** @var Sport $sportSer */
            $sportSer = $this->serializer->deserialize($this->getRawData($request), Sport::class, 'json');

            $newSport = $this->sportRepos->findOneBy(['name' => strtolower($sportSer->getName())]);
            if ($newSport === null) {
                $newSport = new Sport(
                    strtolower($sportSer->getName()),
                    $sportSer->getTeam(),
                    $sportSer->getDefaultGameMode(),
                    $sportSer->getDefaultNrOfSidePlaces()
                );
                $newSport->setCustomId($sportSer->getCustomId());
                $this->sportRepos->save($newSport);
            }

            $json = $this->serializer->serialize($newSport, 'json');
            return $this->respondWithJson($response, $json);
        } catch (Exception $exception) {
            return new ErrorResponse($exception->getMessage(), 422, $this->logger);
        }
    }
}
