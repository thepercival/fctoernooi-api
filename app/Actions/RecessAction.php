<?php

declare(strict_types=1);

namespace App\Actions;

use App\Response\ForbiddenResponse as ForbiddenResponse;
use FCToernooi\Recess;
use FCToernooi\Recess\Repository as RecessRepository;
use FCToernooi\Tournament;
use JMS\Serializer\SerializerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Slim\Exception\HttpException;
use Sports\Structure\Repository as StructureRepository;

final class RecessAction extends Action
{
    public function __construct(
        LoggerInterface $logger,
        SerializerInterface $serializer,
        private RecessRepository $recessRepos,
        protected StructureRepository $structureRepos
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

            /** @var Recess $serRecess */
            $serRecess = $this->serializer->deserialize($this->getRawData($request), Recess::class, 'json');

            $validator = new Recess\Validator();
            $validator->validateNewPeriod($serRecess->getPeriod(), $tournament);

            $newRecess = new Recess($tournament, $serRecess->getPeriod());
            $this->recessRepos->save($newRecess);

            $json = $this->serializer->serialize($newRecess, 'json');
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

            $recess = $this->recessRepos->find((int)$args['recessId']);
            if ($recess === null) {
                throw new \Exception("geen pauze met het opgegeven id gevonden", E_ERROR);
            }
            if ($recess->getTournament() !== $tournament) {
                return new ForbiddenResponse("het toernooi komt niet overeen met het toernooi van de sponsor");
            }
            $this->recessRepos->remove($recess);
            return $response->withStatus(200);
        } catch (\Exception $exception) {
            throw new HttpException($request, $exception->getMessage(), 422);
        }
    }
}