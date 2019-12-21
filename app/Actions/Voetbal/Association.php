<?php
/**
 * Created by PhpStorm.
 * User: coen
 * Date: 2-2-17
 * Time: 21:49
 */

namespace VoetbalApp\Action;

use JMS\Serializer\Serializer;
use Voetbal\Association\Service as AssociationService;
use Voetbal\Association as AssociationBase;
use Voetbal\Association\Repository as AssociationRepository;
use Voetbal;

final class Association
{
    /**
     * @var AssociationService
     */
	protected $service;
    /**
     * @var AssociationRepository
     */
    protected $repos;
    /**
     * @var Serializer
     */
    protected $serializer;

	public function __construct(AssociationService $service, AssociationRepository $repos, Serializer $serializer)
	{
		$this->repos = $repos;
        $this->service = $service;
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
		return $response->withStatus(404)->write('geen bond met het opgegeven id gevonden');
	}

	public function add( $request, $response, $args)
	{
        try {
            /** @var \Voetbal\Association|false $associationSer */
            $associationSer = $this->serializer->deserialize(json_encode($request->getParsedBody()),'Voetbal\Association', 'json');
            if ($associationSer === false) {
                throw new \Exception("er kan geen bond worden toegevoegd o.b.v. de invoergegevens", E_ERROR);
            }

            $parentAssociation = null;
            if ($associationSer->getParent() !== null) {
                $parentAssociation = $this->repos->find($associationSer->getParent()->getId());
            }

            $associationWithSameName = $this->repos->findOneBy(array('name' => $associationSer->getName()));
            if ($associationWithSameName !== null) {
                throw new \Exception("de bond met de naam " . $associationSer->getName() . " bestaat al", E_ERROR);
            }

            $association = new AssociationBase($associationSer->getName());
            $association->setDescription($associationSer->getDescription());
            $association = $this->service->changeParent($association, $parentAssociation);
            $this->repos->save( $association );

            return $response
                ->withStatus(201)
                ->withHeader('Content-Type', 'application/json;charset=utf-8')
                ->write($this->serializer->serialize( $association, 'json'));
            ;
        }
        catch( \Exception $e ){
            return $response->withStatus(404)->write( $e->getMessage() );
        }
	}

	public function edit( $request, $response, $args)
	{
        try {

            /** @var \Voetbal\Association|false $associationSer */
            $associationSer = $this->serializer->deserialize(json_encode($request->getParsedBody()), 'Voetbal\Association', 'json');
            if ( $associationSer === false ) {
                throw new \Exception("er kan geen bond worden gewijzigd o.b.v. de invoergegevens", E_ERROR);
            }

            $association = $this->repos->find($args['id']);
            if ( $association === null ) {
                throw new \Exception("de bond kon niet gevonden worden o.b.v. de invoer", E_ERROR);
            }
            $parentAssociation = null;
            if( $associationSer->getParent() !== null ) {
                $parentAssociation = $this->repos->find($associationSer->getParent()->getId());
            }

            $associationWithSameName = $this->repos->findOneBy( array('name' => $associationSer->getName() ) );
            if ( $associationWithSameName !== null and $associationWithSameName !== $association ){
                throw new \Exception("de bond met de naam ".$associationSer->getName()." bestaat al", E_ERROR );
            }

            $association->setName($associationSer->getName());
            $association->setDescription($associationSer->getDescription());
            $association = $this->service->changeParent( $association, $parentAssociation );
            $this->repos->save( $association );

            return $response
                ->withStatus(201)
                ->withHeader('Content-Type', 'application/json;charset=utf-8')
                ->write($this->serializer->serialize( $association, 'json'));
            ;
        }
        catch( \Exception $e ){
            return $response->withStatus(404)->write( $e->getMessage() );
        }
	}

	public function remove( $request, $response, $args)
	{
		$association = $this->repos->find($args['id']);
		try {
			$this->repos->remove($association);
			return $response->withStatus(204);
		}
		catch( \Exception $e ){
            return $response->withStatus(404)->write( $e->getMessage() );
		}
	}
}