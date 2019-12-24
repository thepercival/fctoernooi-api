<?php
/**
 * Created by PhpStorm.
 * User: coen
 * Date: 26-11-17
 * Time: 12:35
 */

namespace App\Actions\Voetbal\Sport;

use JMS\Serializer\Serializer;
use Voetbal\Sport\ScoreConfig as SportScoreConfig;
use Voetbal\Round\Number as RoundNumber;
use Voetbal\Sport;
use Voetbal\Structure\Repository as StructureRepository;
use Voetbal\Sport\ScoreConfig\Repository as SportScoreConfigRepository;
use Voetbal\Competition\Repository as CompetitionRepository;

final class ScoreConfig
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
     * @var SportScoreConfigRepository
     */
    protected $repos;

    /**
     * @var Serializer
     */
    protected $serializer;

    public function __construct(
        SportScoreConfigRepository $repos,
        StructureRepository $structureRepos,
        CompetitionRepository $competitionRepos,
        Serializer $serializer
    )
    {
        $this->repos = $repos;
        $this->structureRepos = $structureRepos;
        $this->competitionRepos = $competitionRepos;
        $this->serializer = $serializer;
    }


    public function add( $request, $response, $args )
    {
        try {
            $competitionId = (int) $request->getParam("competitionid");
            /** @var \Voetbal\Competition|null $competition */
            $competition = $this->competitionRepos->find($competitionId);
            if ( $competition === null ) {
                throw new \Exception("de competitie kan niet gevonden worden", E_ERROR);
            }
            /** @var \Voetbal\Sport\ScoreConfig|null $scoreConfigSer */
            $scoreConfigSer = $this->serializer->deserialize( json_encode($request->getParsedBody()), 'Voetbal\Sport\ScoreConfig', 'json');
            if ( $scoreConfigSer === null ) {
                throw new \Exception("er zijn geen score-instellingen gevonden o.b.v. de invoergegevens", E_ERROR);
            }
            $roundNumberAsValue = (int) $request->getParam("roundnumber");
            if ( $roundNumberAsValue === 0 ) {
                throw new \Exception("geen rondenummer opgegeven", E_ERROR);
            }
            $structure = $this->structureRepos->getStructure( $competition );
            $roundNumber = $structure->getRoundNumber( $roundNumberAsValue );

            $sport = $competition->getSportBySportId( (int) $request->getParam("sportid") );
            if ( $sport === null ) {
                throw new \Exception("de sport kon niet gevonden worden", E_ERROR);
            }
            if ( $roundNumber->getSportScoreConfig( $sport ) !== null ) {
                throw new \Exception("er zijn al een score-instellingen aanwezig", E_ERROR);
            }

            $scoreConfig = new SportScoreConfig( $sport, $roundNumber, null );
            $scoreConfig->setDirection( SportScoreConfig::UPWARDS );
            $scoreConfig->setMaximum( $scoreConfigSer->getMaximum() );
            if( $scoreConfigSer->hasNext() ) {
                $nextScoreConfig = new SportScoreConfig( $sport, $roundNumber, $scoreConfig );
                $nextScoreConfig->setDirection( SportScoreConfig::UPWARDS );
                $nextScoreConfig->setMaximum( $scoreConfigSer->getNext()->getMaximum() );
            }

            $this->repos->save($scoreConfig);

            $this->removeNext($roundNumber, $sport);

            return $response
                ->withStatus(201)
                ->withHeader('Content-Type', 'application/json;charset=utf-8')
                ->write($this->serializer->serialize( $scoreConfig, 'json'));
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
            $structure = $this->structureRepos->getStructure( $competition ); // to init next/previous
            $roundNumber = $structure->getRoundNumber( (int) $request->getParam("roundnumber") );
            if ( $roundNumber === null ) {
                throw new \Exception("het rondenummer kan niet gevonden worden", E_ERROR);
            }

            /** @var \Voetbal\Sport\ScoreConfig|null $scoreConfigSer */
            $scoreConfigSer = $this->serializer->deserialize( json_encode($request->getParsedBody()), 'Voetbal\Sport\ScoreConfig', 'json');
            if ( $scoreConfigSer === null ) {
                throw new \Exception("er zijn geen score-instellingen gevonden o.b.v. de invoergegevens", E_ERROR);
            }

            /** @var \Voetbal\Sport\ScoreConfig|null $scoreConfig */
            $scoreConfig = $this->repos->find( $args['id'] );
            if ( $scoreConfig === null ) {
                throw new \Exception("er zijn geen score-instellingen gevonden om te wijzigen", E_ERROR);
            }

            $scoreConfig->setMaximum( $scoreConfigSer->getMaximum() );
            $this->repos->save($scoreConfig);
            if( $scoreConfig->hasNext() && $scoreConfigSer->hasNext() ) {
                $nextScoreConfig = $scoreConfig->getNext();
                $nextScoreConfig->setMaximum( $scoreConfigSer->getNext()->getMaximum() );
                $this->repos->save($nextScoreConfig);
            }

            $this->removeNext($roundNumber, $scoreConfig->getSport() );

            return $response
                ->withStatus(201)
                ->withHeader('Content-Type', 'application/json;charset=utf-8')
                ->write($this->serializer->serialize( $scoreConfig, 'json'));
            ;
        }
        catch( \Exception $e ){
            return $response->withStatus(422 )->write( $e->getMessage() );
        }
    }

    protected function removeNext( RoundNumber $roundNumber, Sport $sport) {
        while( $roundNumber->hasNext() ) {
            $roundNumber = $roundNumber->getNext();
            $scoreConfig = $roundNumber->getSportScoreConfig( $sport );
            if( $scoreConfig === null ) {
                continue;
            }
            $roundNumber->getSportScoreConfigs()->removeElement( $scoreConfig );
            $this->repos->remove($scoreConfig);
        }
    }
}