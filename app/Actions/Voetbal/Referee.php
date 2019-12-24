<?php
/**
 * Created by PhpStorm.
 * User: coen
 * Date: 26-11-17
 * Time: 12:35
 */

namespace App\Actions\Voetbal;

use JMS\Serializer\Serializer;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\DeserializationContext;
use Voetbal\Referee\Repository as RefereeRepository;
use Voetbal\Referee\Service as RefereeService;
use Voetbal\Competition\Repository as CompetitionRepos;
use Voetbal\Referee as RefereeBase;

final class Referee
{
    /**
     * @var RefereeRepository
     */
    protected $repos;
    /**
     * @var CompetitionRepos
     */
    protected $competitionRepos;
    /**
     * @var Serializer
     */
    protected $serializer;

    public function __construct(
        RefereeRepository $repos,
        CompetitionRepos $competitionRepos,
        Serializer $serializer
    ) {
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
        $object = $this->repos->find($args['id']);
        if ($object) {
            return $response
                ->withHeader('Content-Type', 'application/json;charset=utf-8')
                ->write($this->serializer->serialize( $object, 'json'));
            ;
        }
        return $response->withStatus(404)->write('geen scheidsrechter met het opgegeven id gevonden');
    }

    public function add($request, $response, $args)
    {
        try {
            $serGroups = ['Default','privacy'];
            $competitionId = (int)$request->getParam("competitionid");
            $competition = $this->competitionRepos->find($competitionId);
            if ($competition === null) {
                throw new \Exception("de competitie kan niet gevonden worden", E_ERROR);
            }

            $deserializationContext = DeserializationContext::create();
            $deserializationContext->setGroups($serGroups);
            /** @var \Voetbal\Referee|false $refereeSer */
            $refereeSer = $this->serializer->deserialize(json_encode($request->getParsedBody()), 'Voetbal\Referee', 'json', $deserializationContext);
            if ($refereeSer === false) {
                throw new \Exception("er kan geen scheidsrechter worden aangemaakt o.b.v. de invoergegevens", E_ERROR);
            }

            $refereesWithSameInitials = $competition->getReferees()->filter( function( $refereeIt ) use ( $refereeSer ) {
                return $refereeIt->getInitials() === $refereeSer->getInitials();
            });
            if( !$refereesWithSameInitials->isEmpty() ) {
                throw new \Exception("de scheidsrechter met de initialen ".$refereeSer->getInitials()." bestaat al", E_ERROR );
            }

            $referee = new RefereeBase( $competition, $refereeSer->getRank() );
            $referee->setInitials($refereeSer->getInitials());
            $referee->setName($refereeSer->getName());
            $referee->setEmailaddress($refereeSer->getEmailaddress());
            $referee->setInfo($refereeSer->getInfo());

            $this->repos->save( $referee );

            $serializationContext = SerializationContext::create();
            $serializationContext->setGroups($serGroups);
            return $response
                ->withStatus(201)
                ->withHeader('Content-Type', 'application/json;charset=utf-8')
                ->write($this->serializer->serialize($referee, 'json', $serializationContext));
        } catch (\Exception $e) {
            return $response->withStatus(422)->write($e->getMessage());
        }
    }

    public function edit($request, $response, $args)
    {
        try {
            $serGroups = ['Default','privacy'];
            $referee = $this->getReferee((int)$args["id"], (int)$request->getParam("competitionid"));

            $deserializationContext = DeserializationContext::create();
            $deserializationContext->setGroups($serGroups);
            /** @var \Voetbal\Referee|false $refereeSer */
            $refereeSer = $this->serializer->deserialize(json_encode($request->getParsedBody()), 'Voetbal\Referee', 'json', $deserializationContext);
            if ($refereeSer === false) {
                throw new \Exception("de scheidsrechter kon niet gevonden worden o.b.v. de invoer", E_ERROR);
            }

            $competition = $referee->getCompetition();

            $refereesWithSameInitials = $competition->getReferees()->filter( function( $refereeIt ) use ( $refereeSer, $referee ) {
                return $refereeIt->getInitials() === $refereeSer->getInitials() && $referee !== $refereeIt;
            });
            if( !$refereesWithSameInitials->isEmpty() ) {
                throw new \Exception("de scheidsrechter met de initialen ".$refereeSer->getInitials()." bestaat al", E_ERROR );
            }

            $referee->setRank( $refereeSer->getRank() );
            $referee->setInitials( $refereeSer->getInitials() );
            $referee->setName( $refereeSer->getName() );
            $referee->setEmailaddress($refereeSer->getEmailaddress());
            $referee->setInfo( $refereeSer->getInfo() );

            $this->repos->save( $referee );

            $serializationContext = SerializationContext::create();
            $serializationContext->setGroups($serGroups);
            return $response
                ->withStatus(201)
                ->withHeader('Content-Type', 'application/json;charset=utf-8')
                ->write($this->serializer->serialize($referee, 'json', $serializationContext));
        } catch (\Exception $e) {
            return $response->withStatus(404)->write($e->getMessage());
        }
    }

    public function remove($request, $response, $args)
    {
        try {
            $referee = $this->getReferee((int)$args["id"], (int)$request->getParam("competitionid"));
            $this->repos->remove($referee);
            return $response->withStatus(204);
        } catch (\Exception $e) {
            return $response->withStatus(404)->write($e->getMessage());
        }
    }

    protected function getReferee(int $id, int $competitionId): RefereeBase
    {
        $referee = $this->repos->find($id);
        if ($referee === null) {
            throw new \Exception('de te verwijderen scheidsrechter kan niet gevonden worden', E_ERROR);
        }
        $competition = $this->competitionRepos->find($competitionId);
        if ($competition === null) {
            throw new \Exception("er kan geen competitie worden gevonden o.b.v. de invoergegevens", E_ERROR);
        }
        if ($referee->getCompetition() !== $competition) {
            throw new \Exception("de competitie van de scheidsrechter komt niet overeen met de verstuurde competitie",
                E_ERROR);
        }
        return $referee;
    }
}