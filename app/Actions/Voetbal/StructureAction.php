<?php
/**
 * Created by PhpStorm.
 * User: coen
 * Date: 14-11-17
 * Time: 14:02
 */

namespace App\Actions\Voetbal;

use Voetbal\Competition;
use Voetbal\Structure;
use Voetbal\Structure\Copier as StructureCopier;
use App\Response\ErrorResponse;
use Psr\Log\LoggerInterface;
use JMS\Serializer\SerializerInterface;
use Voetbal\Structure\Repository as StructureRepository;
use Voetbal\Competitor\Repository as CompetitorRepository;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Actions\Action;

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

    public function fetchOne( Request $request, Response $response, $args ): Response
    {
        $competition = $request->getAttribute("tournament")->getCompetition();

        $structure = $this->structureRepos->getStructure( $competition );
        // var_dump($structure); die();

        $json = $this->serializer->serialize( $structure, 'json');
        return $this->respondWithJson($response, $json);
    }

    public function edit( Request $request, Response $response, $args ): Response
    {
        try {
            /** @var Structure|false $structureSer */
            $structureSer = $this->serializer->deserialize($this->getRawData(), 'Voetbal\Structure', 'json');
            if ($structureSer === false) {
                throw new \Exception("er kan geen ronde worden gewijzigd o.b.v. de invoergegevens", E_ERROR);
            }
            /** @var Competition $competition */
            $competition = $request->getAttribute("tournament")->getCompetition();

            $structure = $this->structureRepos->getStructure($competition);
            $existingCompetitors = $structure ? $structure->getFirstRoundNumber()->getCompetitors() : [];
            $structureCopier = new StructureCopier($competition, $existingCompetitors);
            $newStructure = $structureCopier->copy($structureSer);

            $roundNumberAsValue = 1;
            $this->structureRepos->removeAndAdd($competition, $newStructure, $roundNumberAsValue);

            $this->competitorRepos->removeUnused($competition->getLeague()->getAssociation());

            $json = $this->serializer->serialize($newStructure, 'json');
            return $this->respondWithJson($response, $json);
        } catch( \Exception $e ){
            return new ErrorResponse($e->getMessage(), 422);
        }
    }
}