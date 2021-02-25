<?php

declare(strict_types=1);

namespace App\Actions;

use App\Response\ErrorResponse;
use App\Response\ForbiddenResponse as ForbiddenResponse;
use Doctrine\Common\Collections\ArrayCollection;
use FCToernooi\Tournament;
use Selective\Config\Configuration;
use Slim\Factory\ServerRequestCreatorFactory;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use JMS\Serializer\SerializerInterface;
use FCToernooi\LockerRoom\Repository as LockerRoomRepository;
use FCToernooi\Tournament\Repository as TournamentRepository;
use FCToernooi\Competitor;
use FCToernooi\LockerRoom;

final class LockerRoomAction extends Action
{
    /**
     * @var LockerRoomRepository
     */
    private $lockerRoomRepos;
    /**
     * @var TournamentRepository
     */
    private $tournamentRepos;
    /**
     * @var Configuration
     */
    protected $config;

    public function __construct(
        LoggerInterface $logger,
        SerializerInterface $serializer,
        LockerRoomRepository $lockerRoomRepos,
        TournamentRepository $tournamentRepos,
        Configuration $config
    ) {
        parent::__construct($logger, $serializer);
        $this->lockerRoomRepos = $lockerRoomRepos;
        $this->tournamentRepos = $tournamentRepos;
        $this->config = $config;
    }

    public function add(Request $request, Response $response, $args): Response
    {
        try {
            /** @var Tournament $tournament */
            $tournament = $request->getAttribute("tournament");

            /** @var LockerRoom $lockerRoomSer */
            $lockerRoomSer = $this->serializer->deserialize($this->getRawData(), LockerRoom::class, 'json');

            $newLockerRoom = new LockerRoom($tournament, $lockerRoomSer->getName());
            $this->lockerRoomRepos->save($newLockerRoom);

            $json = $this->serializer->serialize($newLockerRoom, 'json');
            return $this->respondWithJson($response, $json);
        } catch (\Exception $exception) {
            return new ErrorResponse($exception->getMessage(), 422);
        }
    }

    public function edit(Request $request, Response $response, $args): Response
    {
        try {
            /** @var Tournament $tournament */
            $tournament = $request->getAttribute("tournament");

            /** @var LockerRoom $lockerRoomSer */
            $lockerRoomSer = $this->serializer->deserialize($this->getRawData(), LockerRoom::class, 'json');

            $lockerRoom = $this->lockerRoomRepos->find((int)$args['lockerRoomId']);
            if ($lockerRoom === null) {
                throw new \Exception("geen kleedkamer met het opgegeven id gevonden", E_ERROR);
            }
            if ($lockerRoom->getTournament() !== $tournament) {
                return new ForbiddenResponse("het toernooi komt niet overeen met het toernooi van de kleedkamer");
            }
            $lockerRoom->setName($lockerRoomSer->getName());
            $this->lockerRoomRepos->save($lockerRoom);

            $json = $this->serializer->serialize($lockerRoom, 'json');
            return $this->respondWithJson($response, $json);
        } catch (\Exception $exception) {
            return new ErrorResponse($exception->getMessage(), 422);
        }
    }

    public function remove(Request $request, Response $response, $args): Response
    {
        try {
            /** @var \FCToernooi\Tournament $tournament */
            $tournament = $request->getAttribute("tournament");

            $lockerRoom = $this->lockerRoomRepos->find((int)$args['lockerRoomId']);
            if ($lockerRoom === null) {
                throw new \Exception("geen kleedkamer met het opgegeven id gevonden", E_ERROR);
            }
            if ($lockerRoom->getTournament() !== $tournament) {
                return new ForbiddenResponse("het toernooi komt niet overeen met het toernooi van de kleedkamer");
            }

            $this->lockerRoomRepos->remove($lockerRoom);

            return $response->withStatus(200);
        } catch (\Exception $exception) {
            return new ErrorResponse($exception->getMessage(), 422);
        }
    }

    public function syncCompetitors(Request $request, Response $response, $args): Response
    {
        try {
            /** @var ArrayCollection|Competitor[] $newCompetitors */
            $newCompetitors = $this->serializer->deserialize(
                $this->getRawData(),
                ArrayCollection::class . '<' . Competitor::class . '>',
                'json'
            );

            /** @var \FCToernooi\Tournament $tournament */
            $tournament = $request->getAttribute("tournament");

            $lockerRoom = $this->lockerRoomRepos->find((int)$args['lockerRoomId']);
            if ($lockerRoom === null) {
                throw new \Exception("geen kleedkamer met het opgegeven id gevonden", E_ERROR);
            }
            if ($lockerRoom->getTournament() !== $tournament) {
                return new ForbiddenResponse("het toernooi komt niet overeen met het toernooi van de kleedkamer");
            }
            $this->lockerRoomRepos->updateCompetitors($lockerRoom, $newCompetitors);

            return $response->withStatus(200);
        } catch (\Exception $exception) {
            return new ErrorResponse($exception->getMessage(), 422);
        }
    }
}
