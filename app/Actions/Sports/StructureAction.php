<?php

namespace App\Actions\Sports;

use FCToernooi\Tournament;
use Sports\Competition;
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
    /**
     * @var StructureRepository
     */
    protected $structureRepos;
    /**
     * @var CompetitorRepository
     */
    protected $competitorRepos;

    public function __construct(
        LoggerInterface $logger,
        SerializerInterface $serializer,
        StructureRepository $structureRepos,
        CompetitorRepository $competitorRepos
    ) {
        parent::__construct($logger, $serializer);
        $this->structureRepos = $structureRepos;
        $this->competitorRepos = $competitorRepos;
    }

    public function fetchOne(Request $request, Response $response, $args): Response
    {
        try {
            $competition = $request->getAttribute("tournament")->getCompetition();

            $structure = $this->structureRepos->getStructure($competition);
            // var_dump($structure); die();

            $json = $this->serializer->serialize($structure, 'json');
            return $this->respondWithJson($response, $json);
        } catch (\Exception $e) {
            return new ErrorResponse($e->getMessage(), 500);
        }
    }

    public function edit(Request $request, Response $response, $args): Response
    {
        try {
            /** @var Structure|false $structureSer */
            $structureSer = $this->serializer->deserialize($this->getRawData(), Structure::class, 'json');
            if ($structureSer === false) {
                throw new \Exception("er kan geen ronde worden gewijzigd o.b.v. de invoergegevens", E_ERROR);
            }
            /** @var Tournament $tournament */
            $tournament = $request->getAttribute("tournament");

            $competition = $tournament->getCompetition();

            $structure = $this->structureRepos->getStructure($competition);
            $structureCopier = new StructureCopier($competition);
            $newStructure = $structureCopier->copy($structureSer);

            $structureValidator = new StructureValidator();
            $structureValidator->checkValidity($competition, $newStructure);

            $roundNumberAsValue = 1;
            $this->structureRepos->removeAndAdd($competition, $newStructure, $roundNumberAsValue);

            $this->competitorRepos->syncCompetitors($tournament, $newStructure->getRootRound() );

            $json = $this->serializer->serialize($newStructure, 'json');
            return $this->respondWithJson($response, $json);
        } catch (\Exception $e) {
            return new ErrorResponse($e->getMessage(), 422);
        }
    }

}
