<?php

declare(strict_types=1);

namespace App\Actions;

use App\Response\ForbiddenResponse as ForbiddenResponse;
use Doctrine\Common\Collections\ArrayCollection;
use FCToernooi\Competitor;
use FCToernooi\LockerRoom;
use FCToernooi\LockerRoom\Repository as LockerRoomRepository;
use FCToernooi\Tournament;
use JMS\Serializer\SerializerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Slim\Exception\HttpException;

/**
 * @template Action<LockerRoom>
 */
final class LockerRoomAction extends Action
{
    public function __construct(
        LoggerInterface $logger,
        SerializerInterface $serializer,
        private LockerRoomRepository $lockerRoomRepos
    ) {
        parent::__construct($logger, $serializer);
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
            /** @var Tournament $tournament */
            $tournament = $request->getAttribute("tournament");

            /** @var LockerRoom $lockerRoomSer */
            $lockerRoomSer = $this->serializer->deserialize($this->getRawData($request), LockerRoom::class, 'json');

            $newLockerRoom = new LockerRoom($tournament, $lockerRoomSer->getName());
            $this->lockerRoomRepos->save($newLockerRoom);

            $json = $this->serializer->serialize($newLockerRoom, 'json');
            return $this->respondWithJson($response, $json);
        } catch (\Exception $exception) {
            throw new HttpException($request, $exception->getMessage(), 422);
        }
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param array<string, int|string> $args
     * @return Response
     */
    public function edit(Request $request, Response $response, array $args): Response
    {
        try {
            /** @var Tournament $tournament */
            $tournament = $request->getAttribute("tournament");

            /** @var LockerRoom $lockerRoomSer */
            $lockerRoomSer = $this->serializer->deserialize($this->getRawData($request), LockerRoom::class, 'json');

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
            throw new HttpException($request, $exception->getMessage(), 422);
        }
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param array<string, int|string> $args
     * @return Response
     */
    public function remove(Request $request, Response $response, array $args): Response
    {
        try {
            /** @var Tournament $tournament */
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
            throw new HttpException($request, $exception->getMessage(), 422);
        }
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param array<string, int|string> $args
     * @return Response
     */
    public function syncCompetitors(Request $request, Response $response, array $args): Response
    {
        try {
            /** @psalm-var class-string $className */
            $className = ArrayCollection::class . '<' . Competitor::class . '>';
            /** @psalm-var ArrayCollection<int|string, Competitor> $newCompetitors */
            $newCompetitors =  $this->serializer->deserialize(
                $this->getRawData($request),
                $className,
                'json'
            );
            // $this->deserialize($request, $className);

            /** @var Tournament $tournament */
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
            throw new HttpException($request, $exception->getMessage(), 422);
        }
    }
}
