<?php
/**
 * Created by PhpStorm.
 * User: coen
 * Date: 26-11-17
 * Time: 12:35
 */

namespace App\Actions\Voetbal\Sport;

use Voetbal\Competition;
use Voetbal\Competition\Repository as CompetitionRepository;
use Voetbal\Sport;
use Voetbal\Sport\Config\Repository as SportConfigRepository;
use Voetbal\Sport\Repository as SportRepository;
use JMS\Serializer\Serializer;
use Voetbal\Sport\CustomId as SportCustomId;
use Voetbal\Sport\Config as SportConfig;
use Voetbal\Structure;
use Voetbal\Structure\Repository as StructureRepos;
use Voetbal\Sport\Config\Service as SportConfigService;

final class Config
{
    /**
     * @var SportConfigService
     */
    protected $service;
    /**
     * @var CompetitionRepository
     */
    protected $competitionRepos;
    /**
     * @var StructureRepos
     */
    protected $structureRepos;
    /**
     * @var SportConfigRepository
     */
    protected $repos;
    /**
     * @var SportRepository
     */
    protected $sportRepos;

    /**
     * @var Serializer
     */
    protected $serializer;

    public function __construct(
        SportConfigService $service,
        CompetitionRepository $competitionRepos,
        StructureRepos $structureRepos,
        SportConfigRepository $repos,
        SportRepository $sportRepos,
        Serializer $serializer
    )
    {
        $this->service = $service;
        $this->competitionRepos = $competitionRepos;
        $this->structureRepos = $structureRepos;
        $this->repos = $repos;
        $this->sportRepos = $sportRepos;
        $this->serializer = $serializer;
    }

    public function fetch( $request, $response, $args)
    {
        $competitionId = (int) $request->getParam("competitionid");
        $competition = $this->competitionRepos->find($competitionId);
        $params = [];
        if ( $competition !== null ) {
            $params = ["competition" => $competition];
        }
        $objects = $this->repos->findBy($params);
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
        return $response->withStatus(404)->write('geen sportconfiguratie met het opgegeven id gevonden');
    }

    public function add( $request, $response, $args )
    {
        try {
            $competitionId = (int) $request->getParam("competitionid");
            $competition = $this->competitionRepos->find($competitionId);
            if ( $competition === null ) {
                throw new \Exception("de competitie kan niet gevonden worden", E_ERROR);
            }
            /** @var \Voetbal\Sport\Config|false $sportConfigSer */
            $sportConfigSer = $this->serializer->deserialize( json_encode($request->getParsedBody()), 'Voetbal\Sport\Config', 'json');
            if ( $sportConfigSer === false ) {
                throw new \Exception("er kunnen geen sportconfiguratie-instellingen worden gewijzigd o.b.v. de invoergegevens", E_ERROR);
            }
            $sport = $this->sportRepos->find( $sportConfigSer->getSportIdSer() );
            if ( $sport === null ) {
                throw new \Exception("de sport van de configuratie kan niet gevonden worden", E_ERROR);
            }
            if ( $competition->getSportConfig( $sport ) !== null ) {
                throw new \Exception("de sport wordt al gebruikt binnen de competitie", E_ERROR);
            }

            $structure = $this->structureRepos->getStructure($competition);
            $sportConfig = $this->service->createDefault( $sport, $competition, $structure );
            $sportConfig->setWinPoints( $sportConfigSer->getWinPoints() );
            $sportConfig->setDrawPoints( $sportConfigSer->getDrawPoints() );
            $sportConfig->setWinPointsExt( $sportConfigSer->getWinPointsExt() );
            $sportConfig->setDrawPointsExt( $sportConfigSer->getDrawPointsExt() );
            $sportConfig->setPointsCalculation( $sportConfigSer->getPointsCalculation() );
            $sportConfig->setNrOfGamePlaces( $sportConfigSer->getNrOfGamePlaces() );
            $this->repos->customAdd($sportConfig, $structure->getFirstRoundNumber());

            return $response
                ->withStatus(201)
                ->withHeader('Content-Type', 'application/json;charset=utf-8')
                ->write($this->serializer->serialize( $sportConfig, 'json'));
            ;
        }
        catch( \Exception $e ){
            return $response->withStatus(422 )->write( $e->getMessage() );
        }
    }

    public function edit( $request, $response, $args )
    {
        try {
            $competitionId = (int) $request->getParam("competitionid");
            $competition = $this->competitionRepos->find($competitionId);
            if ( $competition === null ) {
                throw new \Exception("de competitie kan niet gevonden worden", E_ERROR);
            }

            /** @var \Voetbal\Sport\Config|false $sportConfigSer */
            $sportConfigSer = $this->serializer->deserialize( json_encode($request->getParsedBody()), 'Voetbal\Sport\Config', 'json');
            if ( $sportConfigSer === false ) {
                throw new \Exception("er zijn geen sport-instellingen gevonden o.b.v. de invoergegevens", E_ERROR);
            }
            $sport = $this->sportRepos->find( $sportConfigSer->getSportIdSer() );
            if ( $sport === null ) {
                throw new \Exception("de sport van de configuratie kan niet gevonden worden", E_ERROR);
            }
            $sportConfig = $competition->getSportConfig( $sport );
            if( $sportConfig === null ) {
                throw new \Exception("de sportconfig is niet gevonden bij de competitie", E_ERROR);
            }
            $sportConfig->setWinPoints( $sportConfigSer->getWinPoints() );
            $sportConfig->setDrawPoints( $sportConfigSer->getDrawPoints() );
            $sportConfig->setWinPointsExt( $sportConfigSer->getWinPointsExt() );
            $sportConfig->setDrawPointsExt( $sportConfigSer->getDrawPointsExt() );
            $sportConfig->setPointsCalculation( $sportConfigSer->getPointsCalculation() );
            $sportConfig->setNrOfGamePlaces( $sportConfigSer->getNrOfGamePlaces() );
            $this->repos->save($sportConfig);

            return $response
                ->withStatus(201)
                ->withHeader('Content-Type', 'application/json;charset=utf-8')
                ->write($this->serializer->serialize( $sportConfig, 'json'));
            ;
        }
        catch( \Exception $e ){
            return $response->withStatus(404)->write( $e->getMessage() );
        }
    }

    public function remove( $request, $response, $args)
    {
        try {
            $competitionId = (int) $request->getParam("competitionid");
            $competition = $this->competitionRepos->find($competitionId);
            if ( $competition === null ) {
                throw new \Exception("de competitie kan niet gevonden worden", E_ERROR);
            }
            $sportConfig = $this->repos->find($args['id']);
            if( $sportConfig === null ) {
                throw new \Exception("de sportconfig is niet gevonden", E_ERROR);
            }
            if( $competition->getSportConfig( $sportConfig->getSport() ) === null ) {
                throw new \Exception("de sport is niet gevonden bij de competitie", E_ERROR);
            }
            $this->repos->customRemove($sportConfig, $this->sportRepos);
            return $response->withStatus(204);
        }
        catch( \Exception $e ){
            return $response->withStatus(404)->write( $e->getMessage() );
        }
    }
}