<?php

declare(strict_types=1);

namespace App\Actions\Sports;

use App\Response\ErrorResponse;
use FCToernooi\Tournament;
use Psr\Log\LoggerInterface;
use JMS\Serializer\SerializerInterface;
use Sports\Sport\Repository as SportRepository;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Actions\Action;
use Sports\Sport;
use JMS\Serializer\DeserializationContext;
use JMS\Serializer\SerializationContext;

final class SportAction extends Action
{
    public function __construct(
        LoggerInterface $logger,
        SerializerInterface $serializer,
        protected SportRepository $sportRepos
    ) {
        parent::__construct($logger, $serializer);
    }

    protected function getDeserializationContext(): DeserializationContext
    {
        return DeserializationContext::create()->setGroups(['Default', 'noReference']);
    }

    protected function getSerializationContext(): SerializationContext
    {
        return SerializationContext::create()->setGroups(['Default', 'noReference']);
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
            $json = $this->serializer->serialize($sports, 'json', $this->getSerializationContext());
            return $this->respondWithJson($response, $json);
        } catch (\Exception $exception) {
            return new ErrorResponse($exception->getMessage(), 400);
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
            throw new \Exception("geen sport met het opgegeven id gevonden", E_ERROR);
        }

        $json = $this->serializer->serialize($sport, 'json', $this->getSerializationContext());
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
            $sportSer = $this->serializer->deserialize($this->getRawData(), Sport::class, 'json', $this->getDeserializationContext());

            $newSport = $this->sportRepos->findOneBy(['name' => strtolower($sportSer->getName())]);
            if ($newSport !== null && ($sportSer->getTeam() !== $newSport->getTeam())
            ) {
                throw new \Exception('de sport "' . $newSport->getName() . '" bestaat al, kies een andere naam', E_ERROR);
            }
            if ($newSport === null) {
                $newSport = new Sport(strtolower($sportSer->getName()), $sportSer->getTeam());
                $newSport->setCustomId($sportSer->getCustomId());
                $this->sportRepos->save($newSport);
            }

            $json = $this->serializer->serialize($newSport, 'json', $this->getSerializationContext());
            return $this->respondWithJson($response, $json);
        } catch (\Exception $exception) {
            return new ErrorResponse($exception->getMessage(), 422);
        }
    }
}
