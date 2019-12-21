<?php
/**
 * Created by PhpStorm.
 * User: coen
 * Date: 18-2-17
 * Time: 21:33
 */

namespace VoetbalApp\Action;

use JMS\Serializer\Serializer;
use Voetbal\Competitor\Service as CompetitorService;
use Voetbal\Competitor\Repository as CompetitorRepository;
use Voetbal\Association\Repository as AssociationRepository;
use Voetbal\Competitor as CompetitorBase;

final class Competitor
{
    /**
     * @var CompetitorService
     */
    protected $service;
    /**
     * @var CompetitorRepository
     */
    protected $repos;
    /**
     * @var AssociationRepository
     */
    protected $associationRepos;
    /**
     * @var Serializer
     */
    protected $serializer;

    public function __construct(
        CompetitorRepository $repos,
        AssociationRepository $associationRepos,
        Serializer $serializer)
    {
        $this->repos = $repos;
        $this->associationRepos = $associationRepos;
        $this->serializer = $serializer;
    }

    public function fetch( $request, $response, $args)
    {
        $associationid = (int) $request->getParam("associationid");
        $association = $this->associationRepos->find($associationid);
        if ( $association === null ) {
            throw new \Exception("er kan geen bond worden gevonden o.b.v. de invoergegevens", E_ERROR);
        }
        $filters = array( "association" => $association );
        $competitors = $this->repos->findBy( $filters );
        return $response
            ->withHeader('Content-Type', 'application/json;charset=utf-8')
            ->write( $this->serializer->serialize( $competitors, 'json') );
        ;
    }

    public function fetchOne( $request, $response, $args)
    {
        $object = $this->repos->find($args['id']);
        if ($object) {
            return $response
                ->withHeader('Content-Type', 'application/json;charset=utf-8')
                ->write($this->serializer->serialize( $object, 'json'));
            ;
        }
        return $response->withStatus(404)->write( 'geen competitie met het opgegeven id gevonden');
    }

    public function add( $request, $response, $args)
    {
        try {
            $associationid = (int) $request->getParam("associationid");
            $association = $this->associationRepos->find($associationid);
            if ( $association === null ) {
                throw new \Exception("er kan geen bond worden gevonden o.b.v. de invoergegevens", E_ERROR);
            }

            /** @var \Voetbal\Competitor|false $competitorSer */
            $competitorSer = $this->serializer->deserialize( json_encode($request->getParsedBody()), 'Voetbal\Competitor', 'json');
            if ( $competitorSer === false ) {
                throw new \Exception("er kan geen deelnemer worden aangemaakt o.b.v. de invoergegevens", E_ERROR);
            }

            $competitor = new CompetitorBase( $association, $competitorSer->getName() );
            $competitor->setAbbreviation($competitorSer->getAbbreviation());
            $competitor->setRegistered($competitorSer->getRegistered());
            $competitor->setImageUrl($competitorSer->getImageUrl());
            $competitor->setInfo($competitorSer->getInfo());
            $this->repos->save($competitor);

            return $response
                ->withStatus(201)
                ->withHeader('Content-Type', 'application/json;charset=utf-8')
                ->write($this->serializer->serialize( $competitor, 'json'));
        } catch( \Exception $e ){
            return $response->withStatus(422)->write( $e->getMessage() );
        }
    }

    public function edit($request, $response, $args)
    {
        try {
            $competitor = $this->getCompetitor( $args['id'], (int) $request->getParam("associationid") );

            /** @var \Voetbal\Competitor|false $competitorSer */
            $competitorSer = $this->serializer->deserialize(json_encode($request->getParsedBody()), 'Voetbal\Competitor', 'json');
            if ( $competitorSer === false ) {
                throw new \Exception("de deelnemer kon niet gevonden worden o.b.v. de invoer", E_ERROR);
            }

            $competitor->setName($competitorSer->getName());
            $competitor->setAbbreviation($competitorSer->getAbbreviation());
            $competitor->setRegistered($competitorSer->getRegistered());
            $competitor->setImageUrl($competitorSer->getImageUrl());
            $competitor->setInfo($competitorSer->getInfo());
            $this->repos->save($competitor);

            return $response
                ->withStatus(201)
                ->withHeader('Content-Type', 'application/json;charset=utf-8')
                ->write($this->serializer->serialize($competitor, 'json'));
        } catch (\Exception $e) {
            $response->withStatus(404)->write($e->getMessage());
        }
    }

    public function remove( $request, $response, $args)
    {
        try {
            $competitor = $this->getCompetitor( $args['id'], (int) $request->getParam("associationid") );
            $this->repos->remove($competitor);
            return $response->withStatus(204);
        } catch( \Exception $e ){
            return $response->withStatus(404)->write($e->getMessage());
        }
    }

    protected function getCompetitor( int $id, int $associationId ): CompetitorBase
    {
        $association = $this->associationRepos->find($associationId);
        if ( $association === null ) {
            throw new \Exception("er kan bond worden gevonden o.b.v. de invoergegevens", E_ERROR);
        }
        $competitor = $this->repos->find($id);
        if ( $competitor === null ) {
            throw new \Exception("de deelnemer kon niet gevonden worden o.b.v. de invoer", E_ERROR);
        }
        if ($competitor->getAssociation() !== $association) {
            throw new \Exception("de bond van de deelnemer komt niet overeen met de verstuurde bond", E_ERROR);
        }
        return $competitor;
    }
}