<?php
/**
 * Created by PhpStorm.
 * User: coen
 * Date: 26-11-17
 * Time: 12:35
 */

namespace VoetbalApp\Action;



use Voetbal\Sport\Repository as SportRepository;
use Voetbal\Competition\Repository as CompetitionRepository;
use JMS\Serializer\Serializer;
use Voetbal\Sport as SportBase;
use Voetbal\Sport\CustomId as SportCustomId;

final class Sport
{
    /**
     * @var CompetitionRepository
     */
    protected $competitionRepos;
    /**
     * @var SportRepository
     */
    protected $repos;

    /**
     * @var Serializer
     */
    protected $serializer;

    public function __construct(
        SportRepository $repos,
        CompetitionRepository $competitionRepos,
        Serializer $serializer
    )
    {
        $this->repos = $repos;
        $this->competitionRepos = $competitionRepos;
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
        $object = $this->repos->findOneBy( ["customId" => $args['id'] ] );
        if ($object) {
            return $response
                ->withHeader('Content-Type', 'application/json;charset=utf-8')
                ->write($this->serializer->serialize( $object, 'json'));
            ;
        }
        return $response->withStatus(404)->write('geen sport met het opgegeven id gevonden');
    }

    public function add( $request, $response, $args )
    {
        try {
            /** @var SportBase|false $sportSer */
            $sportSer = $this->serializer->deserialize( json_encode($request->getParsedBody()), 'Voetbal\Sport', 'json');
            if ( $sportSer === false ) {
                throw new \Exception("er is geen sport gevonden", E_ERROR);
            }

            $sport = $this->repos->findOneBy( ["name" => $sportSer->getName() ] );
            if ( $sport === null ) {
                $sport = new SportBase( $sportSer->getName() );
                $sport->setTeam( $sportSer->getTeam() );
                if( $sportSer->getCustomId() !== null ) {
                    $sport->setCustomId( $sportSer->getCustomId() );
                }
                $this->repos->save($sport);
            }

            return $response
                ->withStatus(201)
                ->withHeader('Content-Type', 'application/json;charset=utf-8')
                ->write($this->serializer->serialize( $sport, 'json'));
            ;
        }
        catch( \Exception $e ){
            return $response->withStatus(422 )->write( $e->getMessage() );
        }
    }

    public function edit( $request, $response, $args )
    {
        throw new \Exception("not implemented", E_ERROR );
    }

    public function remove( $request, $response, $args)
    {
        throw new \Exception("not implemented", E_ERROR );
    }
}