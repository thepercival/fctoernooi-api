<?php

declare(strict_types=1);

namespace App\Actions\Sports;

use App\Response\ErrorResponse;
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
    /**
     * @var SportRepository
     */
    protected $sportRepos;

    public function __construct(
        LoggerInterface $logger,
        SerializerInterface $serializer,
        SportRepository $sportRepos
    ) {
        parent::__construct($logger, $serializer);
        $this->sportRepos = $sportRepos;
    }

    protected function getDeserializationContext()
    {
        return DeserializationContext::create()->setGroups(['Default', 'noReference']);
    }

    protected function getSerializationContext()
    {
        return SerializationContext::create()->setGroups(['Default', 'noReference']);
    }

    public function fetchOne(Request $request, Response $response, $args): Response
    {
        $sport = $this->sportRepos->findOneBy(["customId" => (int)$args['sportCustomId']]);
        if ($sport === null) {
            throw new \Exception("geen sport met het opgegeven id gevonden", E_ERROR);
        }

        $json = $this->serializer->serialize($sport, 'json', $this->getSerializationContext() );
        return $this->respondWithJson($response, $json);
    }

    public function add(Request $request, Response $response, $args): Response
    {
        try {
            /** @var Sport $sport */
            $sport = $this->serializer->deserialize($this->getRawData(), Sport::class, 'json', $this->getDeserializationContext());

            // check if name exists, else create sport
            $newSport = $this->sportRepos->findOneBy(["name" => $sport->getName()]);

            if ($newSport === null) {
                $newSport = new Sport($sport->getName(), $sport->getTeam(), $sport->getNrOfGamePlaces(), $sport->getGameMode() );
                $newSport->setCustomId($sport->getCustomId());
                $this->sportRepos->save($newSport);
            }
            $this->sportRepos->save($newSport);

            $json = $this->serializer->serialize($newSport, 'json', $this->getSerializationContext());
            return $this->respondWithJson($response, $json);
        } catch (\Exception $e) {
            return new ErrorResponse($e->getMessage(), 422);
        }
    }
}
