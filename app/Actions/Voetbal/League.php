<?php
/**
 * Created by PhpStorm.
 * User: coen
 * Date: 2-2-17
 * Time: 21:49
 */

namespace App\Actions\Voetbal;

use JMS\Serializer\Serializer;
use Voetbal\League as LeagueBase;
use Voetbal\League\Repository as LeagueRepository;
use Voetbal\Association\Repository as AssociationRepository;
use Voetbal;

final class League
{
    /**
     * @var LeagueRepository
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
	    LeagueRepository $repos,
        AssociationRepository $associationRepos,
        Serializer $serializer)
	{
        $this->repos = $repos;
		$this->associationRepos = $associationRepos;
		$this->serializer = $serializer;
	}

	public function fetch( $request, $response, $args)
	{
        $objects = $this->repos->findAll();
		return $response
			->withHeader('Content-Type', 'application/json;charset=utf-8')
			->write( $this->serializer->serialize( $objects, 'json') );
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
		return $response->withStatus(404)->write('geen competitie met het opgegeven id gevonden');
	}

	public function add( $request, $response, $args)
	{
        try {
            /** @var \Voetbal\League|false $leagueSer */
            $leagueSer = $this->serializer->deserialize(json_encode($request->getParsedBody()), 'Voetbal\League', 'json');
            if ( $leagueSer === false ) {
                throw new \Exception("er kan geen competitie worden toegevoegd o.b.v. de invoergegevens", E_ERROR);
            }

            $association = $this->associationRepos->find($request->getParam("associationid"));
            if ( $association === null ) {
                throw new \Exception("de bond kon niet gevonden worden o.b.v. de invoer", E_ERROR);
            }

            $league = new LeagueBase( $association, $leagueSer->getName() );
            $league->setAbbreviation( $leagueSer->getAbbreviation() );
            $league->setSportDep('deprecated');
            $league = $this->repos->save($league);

            return $response
                ->withStatus(201)
                ->withHeader('Content-Type', 'application/json;charset=utf-8')
                ->write($this->serializer->serialize( $league, 'json'));
            ;
        }
        catch( \Exception $e ){
            return $response->withStatus(404)->write( $e->getMessage() );
        }
	}

	public function edit( $request, $response, $args)
	{
        $sErrorMessage = null;
        try {
            /** @var \Voetbal\League|false $leagueSer */
            $leagueSer = $this->serializer->deserialize(json_encode($request->getParsedBody()), 'Voetbal\League', 'json');
            if ( $leagueSer === false ) {
                throw new \Exception("er kan geen competitie worden gewijzigd o.b.v. de invoergegevens", E_ERROR);
            }

            $association = $this->associationRepos->find($request->getParam("associationid"));
            if ( $association === null ) {
                throw new \Exception("de bond kon niet gevonden worden o.b.v. de invoer", E_ERROR);
            }

            $league = $this->repos->find($args['id']);
            if ( $league === null ) {
                throw new \Exception("de competitie kon niet gevonden worden o.b.v. de invoer", E_ERROR);
            }

            if ( $league->getAssociation() !== $association ) {
                throw new \Exception("de bond van de competitie komt niet overeen met de paramter-bond", E_ERROR);
            }

            $league->setName($leagueSer->getName());
            $league->setAbbreviation($leagueSer->getAbbreviation());
            $league = $this->repos->save($league);

            return $response
                ->withStatus(201)
                ->withHeader('Content-Type', 'application/json;charset=utf-8')
                ->write($this->serializer->serialize( $league, 'json'));
            ;
        }
        catch( \Exception $e ){
            $sErrorMessage = $e->getMessage();
        }
        return $response->withStatus(404)->write( $sErrorMessage );
	}

	public function remove( $request, $response, $args)
	{
		$league = $this->repos->find($args['id']);
		try {
			$this->repos->remove($league);
			return $response->withStatus(204);
		}
		catch( \Exception $e ){
            return $response->withStatus(404, $e->getMessage() );
		}
	}
}