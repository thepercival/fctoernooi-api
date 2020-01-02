<?php
/**
 * Created by PhpStorm.
 * User: coen
 * Date: 14-11-17
 * Time: 14:02
 */

namespace App\Actions\Voetbal;

use App\Copiers\StructureCopier;
use App\Response\ErrorResponse;
use Psr\Log\LoggerInterface;
use JMS\Serializer\SerializerInterface;
use Voetbal\Structure\Service as StructureService;
use Voetbal\Planning\Service as PlanningService;
use Voetbal\Structure\Repository as StructureRepository;
use Voetbal\Competition\Repository as CompetitionRepository;
use Doctrine\ORM\EntityManager;
use Voetbal\Structure as StructureBase;
use Voetbal\Round\Number as RoundNumber;
use Voetbal\Competition;
use App\Actions\Action;

final class StructureAction extends Action
{
    /**
     * @var StructureRepository
     */
    protected $structureRepos;
    /**
     * @var CompetitionRepository
     */
    protected $competitionRepos;
    /**
     * @var EntityManager
     */
    protected $em;

    public function __construct(
        LoggerInterface $logger,
        SerializerInterface $serializer,
        StructureRepository $structureRepos,
        CompetitionRepository $competitionRepos,
        EntityManager $em
    )
    {
        parent::__construct($logger,$serializer);
        $this->structureRepos = $structureRepos;
        $this->competitionRepos = $competitionRepos;
        $this->serializer = $serializer;
        $this->em = $em;
    }

    public function fetchOne( $request, $response, $args)
    {
        $competition = $this->competitionRepos->find( (int) $args['id'] );
        if( $competition === null ) {
            return $response->withStatus(404)->write('geen indeling gevonden voor competitie');
        }

        $structure = $this->structureRepos->getStructure( $competition );
        // var_dump($structure); die();

        $json = $this->serializer->serialize( $structure, 'json');
        return $this->respondWithJson($response, $json);
    }

    public function edit( $request, $response, $args)
    {
        $this->em->getConnection()->beginTransaction();
        try {
            /** @var \Voetbal\Structure|false $structureSer */
            $structureSer = $this->serializer->deserialize( $this->getRawData(), 'Voetbal\Structure', 'json');
            if ( $structureSer === false ) {
                throw new \Exception("er kan geen ronde worden gewijzigd o.b.v. de invoergegevens", E_ERROR);
            }
            $competition = $this->competitionRepos->find( (int) $args['id'] );
            if ($competition === null) {
                throw new \Exception("er kan geen competitie worden gevonden o.b.v. de invoergegevens", E_ERROR);
            }

            $structureCopier = new StructureCopier( $competition );
            $newStructure = $structureCopier->copy( $structureSer );

            $roundNumberAsValue = 1;
            $this->structureRepos->remove( $competition, $roundNumberAsValue );

            $roundNumber = $this->structureRepos->customPersist( $newStructure, $roundNumberAsValue);

//            $planningService = new PlanningService($competition);
//            $games = $planningService->create( $roundNumber, $competition->getStartDateTime() );
//            foreach( $games as $game ) {
//                $this->em->persist($game);
//            }
//            $this->em->flush();

            $this->em->getConnection()->commit();

            $json = $this->serializer->serialize( $newStructure, 'json');
            return $this->respondWithJson($response, $json);
        }
        catch( \Exception $e ){
            $this->em->getConnection()->rollBack();
            return new ErrorResponse($e->getMessage(), 401);
        }
    }

    public function remove( $request, $response, $args)
    {
        try {
            throw new \Exception("er is geen competitie zonder structuur mogelijk", E_ERROR);
        } catch (\Exception $e) {
            return new ErrorResponse($e->getMessage(), 404);
        }
    }
}