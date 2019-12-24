<?php
/**
 * Created by PhpStorm.
 * User: coen
 * Date: 2-2-17
 * Time: 21:49
 */

namespace App\Actions\Voetbal;

use JMS\Serializer\Serializer;
use Voetbal\Season\Repository as SeasonRepository;
use Voetbal;

final class Season
{
    /**
     * @var SeasonRepository
     */
	protected $repos;
    /**
     * @var Serializer
     */
	protected $serializer;

	public function __construct(SeasonRepository $repos, Serializer $serializer)
	{
		$this->repos = $repos;
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
		return $response->withStatus(404)->write('geen seizoen met het opgegeven id gevonden');
	}

	public function add( $request, $response, $args)
	{
        try {
            /** @var \Voetbal\Season|false $seasonSer */
            $seasonSer = $this->serializer->deserialize(json_encode($request->getParsedBody()), 'Voetbal\Season', 'json');
            if ( $seasonSer === false ) {
                throw new \Exception("er kan geen seizoen worden toegevoegd o.b.v. de invoergegevens", E_ERROR);
            }

            $seasonWithSameName = $this->repos->findOneBy( array('name' => $seasonSer->getName() ) );
            if ( $seasonWithSameName !== null ){
                throw new \Exception("het seizoen ".$seasonSer->getName()." bestaat al", E_ERROR );
            }

            $seasonRet = $this->repos->save( $seasonSer );

            return $response
                ->withStatus(201)
                ->withHeader('Content-Type', 'application/json;charset=utf-8')
                ->write($this->serializer->serialize( $seasonRet, 'json'));
            ;
        }
        catch( \Exception $e ){
            return $response->withStatus(404)->write( $e->getMessage() );
        }
	}

	public function edit( $request, $response, $args)
	{
        try {
            /** @var \Voetbal\Season|false $seasonSer */
            $seasonSer = $this->serializer->deserialize(json_encode($request->getParsedBody()), 'Voetbal\Season', 'json');
            if ( $seasonSer === false ) {
                throw new \Exception("er kan geen seizoen worden gewijzigd o.b.v. de invoergegevens", E_ERROR);
            }

            $season = $this->repos->find($args['id']);
            if ( $season === null ) {
                throw new \Exception("het seizoen kon niet gevonden worden o.b.v. de invoer", E_ERROR);
            }

            $seasonWithSameName = $this->repos->findOneBy( array( 'name' => $seasonSer->getName() ) );
            if ( $seasonWithSameName !== null and $season !== $seasonWithSameName ){
                throw new \Exception("het seizoen ".$seasonSer->getName()." bestaat al", E_ERROR );
            }

            $season->setName( $seasonSer->getName() );
            $season->setStartDateTime( $seasonSer->getStartDateTime() );
            $season->setEndDateTime( $seasonSer->getEndDateTime() );
            $seasonRet = $this->repos->save( $season );

            return $response
                ->withStatus(201)
                ->withHeader('Content-Type', 'application/json;charset=utf-8')
                ->write($this->serializer->serialize( $seasonRet, 'json'));
            ;
        }
        catch( \Exception $e ){
            return $response->withStatus(404)->write( $e->getMessage() );
        }

	}

	public function remove( $request, $response, $args)
	{
		$season = $this->repos->find($args['id']);
		try {
			$this->repos->remove($season);

			return $response
				->withStatus(204);
			;
		}
		catch( \Exception $e ){
            return $response->withStatus(404, $e->getMessage() );
		}

	}
}