<?php
/**
 * Created by PhpStorm.
 * User: coen
 * Date: 6-12-17
 * Time: 10:17
 */

namespace App\Actions\Voetbal;

use JMS\Serializer\Serializer;
use Voetbal\Place\Repository as PlaceRepository;
use Voetbal\Competitor\Repository as CompetitorRepository;
use Voetbal\Poule\Repository as PouleRepository;
use Voetbal\Competition\Repository as CompetitionRepository;
use Voetbal\Poule;

final class Place
{
    /**
     * @var PlaceRepository
     */
    protected $repos;
    /**
     * @var CompetitorRepository
     */
    protected $competitorRepos;
    /**
     * @var CompetitionRepository
     */
    protected $competitionRepos;
    /**
     * @var PouleRepository
     */
    protected $pouleRepos;
    /**
     * @var Serializer
     */
    protected $serializer;

    public function __construct(
        PlaceRepository $repos,
        CompetitorRepository $competitorRepos,
        PouleRepository $pouleRepos,
        CompetitionRepository $competitionRepos,
        Serializer $serializer
    )
    {
        $this->repos = $repos;
        $this->competitorRepos = $competitorRepos;
        $this->pouleRepos = $pouleRepos;
        $this->competitionRepos = $competitionRepos;
        $this->serializer = $serializer;
    }

    public function edit($request, $response, $args)
    {
        try {
            $poule = $this->getPoule( (int)$request->getParam("pouleid"), (int)$request->getParam("competitionid") );

            /** @var \Voetbal\Place|false $placeSer */
            $placeSer = $this->serializer->deserialize(json_encode($request->getParsedBody()), 'Voetbal\Place', 'json');
            if ( $placeSer === false ) {
                throw new \Exception("er kan geen pouleplek worden gewijzigd o.b.v. de invoergegevens", E_ERROR);
            }
            $place = $this->repos->find($placeSer->getId());
            if ( $place === null ) {
                throw new \Exception("de pouleplek kan niet gevonden worden obv id", E_ERROR);
            }
            if ( $place->getPoule() !== $poule ) {
                throw new \Exception("de poule van de pouleplek komt niet overeen met de verstuurde poule", E_ERROR);
            }
            $competitor = $placeSer->getCompetitor() ? $this->competitorRepos->find($placeSer->getCompetitor()->getId()) : null;
            $place->setCompetitor($competitor);
            $this->repos->save($place);

            return $response
                ->withStatus(200)
                ->withHeader('Content-Type', 'application/json;charset=utf-8')
                ->write($this->serializer->serialize($place, 'json'));
        } catch (\Exception $e) {
            return $response->withStatus(422)->write($e->getMessage());
        }
    }

    protected function getPoule( int $pouleId, int $competitionId ): Poule
    {
        $poule = $this->pouleRepos->find($pouleId);
        if ( $poule === null ) {
            throw new \Exception("er kan poule worden gevonden o.b.v. de invoergegevens", E_ERROR);
        }
        $competition = $this->competitionRepos->find($competitionId);
        if ($competition === null) {
            throw new \Exception("er kan geen competitie worden gevonden o.b.v. de invoergegevens", E_ERROR);
        }
        if ($poule->getRound()->getNumber()->getCompetition() !== $competition) {
            throw new \Exception("de competitie van de poule komt niet overeen met de verstuurde competitie",
                E_ERROR);
        }
        return $poule;
    }

}