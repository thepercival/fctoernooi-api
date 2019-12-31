<?php
/**
 * Created by PhpStorm.
 * User: coen
 * Date: 14-11-17
 * Time: 14:02
 */

namespace App\Actions\Voetbal;

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

    public function fetch( $request, $response, $args)
    {
        return $this->fetchOne( $request, $response, $args);

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

    public function add( $request, $response, $args)
    {
        return $response->withStatus( 401 )->write( "only one entity can be fetched" );

//        $this->em->getConnection()->beginTransaction();
//        try {
//            /** @var \Voetbal\Structure $structureSer */
//            $structureSer = $this->serializer->deserialize( json_encode($request->getParsedBody()), 'Voetbal\Structure', 'json');
//
//            if ( $structureSer === null ) {
//                throw new \Exception("er kan geen structuur worden aangemaakt o.b.v. de invoergegevens", E_ERROR);
//            }
//
//            $competitionid = (int) $request->getParam("competitionid");
//            $competition = $this->competitionRepos->find($competitionid);
//            if ( $competition === null ) {
//                throw new \Exception("het competitieseizoen kan niet gevonden worden", E_ERROR);
//            }
//
//            $roundNumber = $this->structureRepos->findRoundNumber($competition, 1);
//            if( $roundNumber !== null ) {
//                throw new \Exception("er is al een structuur aanwezig", E_ERROR);
//            }
//
//            $structure = $this->service->createFromSerialized( $structureSer, $competition );
//            $this->structureRepos->customPersist($structure);
//            $this->em->getConnection()->commit();
//
//            return $response
//                ->withStatus(201)
//                ->withHeader('Content-Type', 'application/json;charset=utf-8')
//                ->write($this->serializer->serialize( $structure, 'json'));
//            ;
//        }
//        catch( \Exception $e ){
//            $this->em->getConnection()->rollBack();
//            return $response->withStatus( 422 )->write( $e->getMessage() );
//        }
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
            $this->postSerialize( $structureSer->getFirstRoundNumber(), $competition );

            $roundNumberAsValue = 1;
            $this->structureRepos->remove( $competition, $roundNumberAsValue );

            $roundNumber = $this->structureRepos->customPersist($structureSer, $roundNumberAsValue);

//            $planningService = new PlanningService($competition);
//            $games = $planningService->create( $roundNumber, $competition->getStartDateTime() );
//            foreach( $games as $game ) {
//                $this->em->persist($game);
//            }
//            $this->em->flush();

            $this->em->getConnection()->commit();

            $json = $this->serializer->serialize( $structureSer, 'json');
            return $this->respondWithJson($response, $json);
        }
        catch( \Exception $e ){
            $this->em->getConnection()->rollBack();
            return new ErrorResponse($e->getMessage(), 401);
        }
    }

    protected function postSerialize( RoundNumber $roundNumber, Competition $competition ) {
        $competitors = $competition->getLeague()->getAssociation()->getCompetitors();
        $roundNumber->setCompetition($competition);
        foreach( $roundNumber->getPlaces() as $place ) {
            if( $place->getCompetitor() === null ) {
                continue;
            }
            $foundCompetitors = $competitors->filter( function( $competitorIt ) use ($place) {
                return $competitorIt->getId() === $place->getCompetitor()->getId();
            });
            if( $foundCompetitors->count() !== 1 ) {
                continue;
            }
            $place->setCompetitor( $foundCompetitors->first() );
        }

        $sports = $competition->getSports();
        foreach( $roundNumber->getSportScoreConfigs() as $sportScoreConfig ) {
            $foundSports = $sports->filter( function( $sport ) use ($sportScoreConfig) {
                return $sport->getId() === $sportScoreConfig->getSport()->getId();
            } );
            if( $foundSports->count() !== 1 ) {
                throw new \Exception("Er kon geen sport worden gevonden voor de configuratie", E_ERROR );
            }
            $sportScoreConfig->setSport( $foundSports->first() );
        }

        if( $roundNumber->hasNext() ) {
            $this->postSerialize( $roundNumber->getNext(), $competition );
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