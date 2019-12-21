<?php
/**
 * Created by PhpStorm.
 * User: cdunnink
 * Date: 7-2-2017
 * Time: 09:49
 */

namespace VoetbalApp\Action\External;

use JMS\Serializer\Serializer;
use Voetbal\External\System\Service as SystemService;
use Voetbal\External\System\Repository as SystemRepository;
use Voetbal;

final class System
{
    /**
     * @var SystemService
     */
    protected $service;
    /**
     * @var SystemRepository
     */
    protected $repos;
    /**
     * @var Serializer
     */
    protected $serializer;

    public function __construct(SystemService $service, SystemRepository $repos, Serializer $serializer)
    {
        $this->repos = $repos;
        $this->service = $service;
        $this->serializer = $serializer;
    }

    public function fetch( $request, $response, $args)
    {
        $systems = $this->repos->findAll();
        return $response
            ->withHeader('Content-Type', 'application/json;charset=utf-8')
            ->write( $this->serializer->serialize( $systems, 'json') );
        ;

    }

    public function fetchOne( $request, $response, $args)
    {
        $system = $this->repos->find($args['id']);
        if ($system) {
            return $response
                ->withHeader('Content-Type', 'application/json;charset=utf-8')
                ->write($this->serializer->serialize( $system, 'json'));
            ;
        }
        return $response->withStatus(404)->write('geen extern systeem met het opgegeven id gevonden');
    }

    public function add( $request, $response, $args)
    {
        $sErrorMessage = null;
        try {
            /** @var \Voetbal\External\System|false $systemSer */
            $systemSer = $this->serializer->deserialize(json_encode($request->getParsedBody()), 'Voetbal\External\System', 'json');
            if ( $systemSer === false ) {
                throw new \Exception("er kan geen extern systeem worden toegevoegd o.b.v. de invoergegevens", E_ERROR);
            }

            $systemWithSameName = $this->repos->findOneBy( array('name' => $systemSer->getName() ) );
            if ( $systemWithSameName !== null ){
                throw new \Exception("het externe systeem ".$systemSer->getName()." bestaat al", E_ERROR );
            }

            $systemRet = $this->repos->save( $systemSer );

            return $response
                ->withStatus(201)
                ->withHeader('Content-Type', 'application/json;charset=utf-8')
                ->write($this->serializer->serialize( $systemRet, 'json'));
            ;
        }
        catch( \Exception $e ){
            $sErrorMessage = $e->getMessage();
        }
        return $response->withStatus(404)->write( $sErrorMessage );
    }

    public function edit( $request, $response, $args)
    {
        $sErrorMessage = null;
        try {
            /** @var \Voetbal\External\System|false $systemSer */
            $systemSer = $this->serializer->deserialize(json_encode($request->getParsedBody()), 'Voetbal\External\System', 'json');
            if ( $systemSer === false ) {
                throw new \Exception("er kan geen extern systeem worden gewijzigd o.b.v. de invoergegevens", E_ERROR);
            }

            $system = $this->repos->find($systemSer->getId());
            if ( $system === null ) {
                throw new \Exception("de naam van het externe systeem wordt al gebruikt", E_ERROR);
            }

            $systemWithSameName = $this->repos->findOneBy( array( 'name' => $systemSer->getName() ) );
            if ( $systemWithSameName !== null and $system->getId() !== $systemWithSameName->getId() ){
                throw new \Exception("het externe systeem ".$systemSer->getName()." bestaat al", E_ERROR );
            }

            $system->setName( $systemSer->getName() );
            $system->setWebsite( $systemSer->getWebsite() );
            $system->setUsername( $systemSer->getUsername() );
            $system->setPassword( $systemSer->getPassword() );
            $system->setApiurl( $systemSer->getApiurl() );
            $system->setApikey( $systemSer->getApikey() );
            $systemRet = $this->repos->save( $system );

            return $response
                ->withStatus(201)
                ->withHeader('Content-Type', 'application/json;charset=utf-8')
                ->write($this->serializer->serialize( $systemRet, 'json'));
            ;
        }
        catch( \Exception $e ){
            $sErrorMessage = $e->getMessage();
        }
        return $response->withStatus(404)->write( $sErrorMessage );
    }

    public function remove( $request, $response, $args)
    {
        $system = $this->repos->find($args['id']);
        $sErrorMessage = null;
        try {
            $this->service->remove($system);

            return $response
                ->withStatus(200);
            ;
        }
        catch( \Exception $e ){
            $sErrorMessage = $e->getMessage();
        }
        return $response->withStatus(404)->write( $sErrorMessage );
    }
}