<?php

declare(strict_types=1);

namespace App\Actions\Sports;

use App\Actions\Action;
use App\Middleware\JsonCacheMiddleware;
use App\Response\ErrorResponse;
use Exception;
use FCToernooi\Competitor\Repository as CompetitorRepository;
use FCToernooi\Tournament;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\SerializerInterface;
use Memcached;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Sports\Structure;
use Sports\Structure\Copier as StructureCopier;
use Sports\Structure\Repository as StructureRepository;
use Sports\Structure\Validator as StructureValidator;

final class StructureAction extends Action
{
    public function __construct(
        LoggerInterface $logger,
        SerializerInterface $serializer,
        protected StructureRepository $structureRepos,
        private Memcached $memcached,
        private StructureCopier $structureCopier,
        protected CompetitorRepository $competitorRepos
    ) {
        parent::__construct($logger, $serializer);
    }

    /**
     * @return list<string>
     */
    protected function getDeserialzeGroups(): array
    {
        return ['Default', 'structure', 'games'];
    }

    protected function getSerializationContext(): SerializationContext
    {
        return SerializationContext::create()->setGroups(['Default', 'structure', 'games']);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param array<string, int|string> $args
     * @return Response
     */
    public function fetchOne(Request $request, Response $response, array $args): Response
    {
        try {
            /** @var Tournament $tournament */
            $tournament = $request->getAttribute("tournament");

            $competition = $tournament->getCompetition();

            $cacheId = JsonCacheMiddleware::StructureCacheIdPrefix . (string)$tournament->getId();

            $json = $this->memcached->get($cacheId);
            if ($json === false || $json === Memcached::RES_NOTFOUND) {
                $structure = $this->structureRepos->getStructure($competition);
                $json = $this->serializer->serialize(
                    $structure,
                    'json',
                    $this->getSerializationContext()
                );
                $this->memcached->set($cacheId, $json, 60 * 60 * 24);
            }
            return $this->respondWithJson($response, $json);
        } catch (\Exception $exception) {
            return new ErrorResponse($exception->getMessage(), 500);
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
            /** @var Structure|false $structureSer */
            $structureSer = $this->deserialize($request, Structure::class, $this->getDeserialzeGroups());
            if ($structureSer === false) {
                throw new \Exception("er kan geen ronde worden gewijzigd o.b.v. de invoergegevens", E_ERROR);
            }

            /** @var Tournament $tournament */
            $tournament = $request->getAttribute("tournament");

            $competition = $tournament->getCompetition();

            $newStructure = $this->structureCopier->copy($structureSer, $competition);

            $structureValidator = new StructureValidator();
            $structureValidator->checkValidity($competition, $newStructure, $tournament->getPlaceRanges());

            $roundNumberAsValue = 1;
            try {
                $this->structureRepos->getStructure($competition);
                $this->structureRepos->removeAndAdd($competition, $newStructure, $roundNumberAsValue);
            } catch (Exception $e) {
                $this->structureRepos->add($newStructure, $roundNumberAsValue);
            }

            $structure = $this->structureRepos->getStructure($competition);
            $this->competitorRepos->syncCompetitors($tournament, $structure->getRootRound());

            $json = $this->serializer->serialize($structure, 'json', $this->getSerializationContext());
            return $this->respondWithJson($response, $json);
        } catch (\Exception $exception) {
            return new ErrorResponse($exception->getMessage(), 422);
        }
    }
}
