<?php

declare(strict_types=1);

namespace App\Actions\Sports;

use Exception;
use FCToernooi\Tournament;
use JMS\Serializer\SerializationContext;
use Sports\Structure;
use Sports\Structure\Copier as StructureCopier;
use App\Response\ErrorResponse;
use Psr\Log\LoggerInterface;
use JMS\Serializer\SerializerInterface;
use Sports\Structure\Repository as StructureRepository;
use FCToernooi\Competitor\Repository as CompetitorRepository;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Actions\Action;
use Sports\Structure\Validator as StructureValidator;

final class StructureAction extends Action
{
    public function __construct(
        LoggerInterface $logger,
        SerializerInterface $serializer,
        protected StructureRepository $structureRepos,
        private StructureCopier $structureCopier,
        protected CompetitorRepository $competitorRepos
    ) {
        parent::__construct($logger, $serializer);
        $this->structureRepos = $structureRepos;
    }

    /**
     * @return list<string>
     */
    protected function getDeserialzeGroups(): array
    {
        return ['Default', 'structure'];
    }

    protected function getSerializationContext(): SerializationContext
    {
        return SerializationContext::create()->setGroups(['Default', 'structure']);
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
            $competition = $request->getAttribute("tournament")->getCompetition();

            $structure = $this->structureRepos->getStructure($competition);
            // var_dump($structure); die();

            $json = $this->serializer->serialize($structure, 'json', $this->getSerializationContext());
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
