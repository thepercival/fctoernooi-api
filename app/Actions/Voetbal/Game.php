<?php
/**
 * Created by PhpStorm.
 * User: coen
 * Date: 28-3-17
 * Time: 20:37
 */

namespace App\Actions\Voetbal;

use JMS\Serializer\Serializer;
use Voetbal\Game\Service as GameService;
use Voetbal\Game\Repository as GameRepository;
use Voetbal\Game\Score\Repository as GameScoreRepository;
use Voetbal\Place\Repository as PlaceRepository;
use Voetbal\Poule\Repository as PouleRepository;
use Voetbal\Field\Repository as FieldRepository;
use Voetbal\Referee\Repository as RefereeRepository;
use Voetbal\Competition\Repository as CompetitionRepository;
use Voetbal;
use Voetbal\Poule;
use Voetbal\Game as GameBase;
use VoetbalApp\Action\PostSerialize\RefereeService as DeserializeRefereeService;

final class Game
{
    /**
     * @var GameService
     */
    protected $service;
    /**
     * @var GameRepository
     */
    protected $repos;
    /**
     * @var GameScoreRepository
     */
    protected $gameScoreRepos;
    /**
     * @var PlaceRepository
     */
    protected $placeRepos;
    /**
     * @var PouleRepository
     */
    protected $pouleRepos;
    /**
     * @var FieldRepository
     */
    protected $fieldRepos;
    /**
     * @var RefereeRepository
     */
    protected $refereeRepos;
    /**
     * @var CompetitionRepository
     */
    protected $competitionRepos;
    /**
     * @var Serializer
     */
    protected $serializer;
    /**
     * @var DeserializeRefereeService
     */
    protected $deserializeRefereeService;

    public function __construct(
        GameService $service,
        GameRepository $repos,
        GameScoreRepository $gameScoreRepos,
        PlaceRepository $placeRepos,
        PouleRepository $pouleRepos,
        FieldRepository $fieldRepos,
        RefereeRepository $refereeRepos,
        CompetitionRepository $competitionRepos,
        Serializer $serializer)
    {
        $this->service = $service;
        $this->repos = $repos;
        $this->gameScoreRepos = $gameScoreRepos;
        $this->placeRepos = $placeRepos;
        $this->pouleRepos = $pouleRepos;
        $this->fieldRepos = $fieldRepos;
        $this->refereeRepos = $refereeRepos;
        $this->competitionRepos = $competitionRepos;
        $this->serializer = $serializer;
        $this->deserializeRefereeService = new DeserializeRefereeService();
    }

    public function fetchOne( $request, $response, $args)
    {
        $sErrorMessage = null;
        try {
            $game = $this->repos->find($args['id']);
            if (!$game) {
                throw new \Exception("geen wedstrijd met het opgegeven id gevonden", E_ERROR);
            }
            return $response
                ->withHeader('Content-Type', 'application/json;charset=utf-8')
                ->write($this->serializer->serialize( $game, 'json'));
            ;
        }
        catch( \Exception $e ){
            $sErrorMessage = $e->getMessage();
        }
        return $response->withStatus(422)->write( $sErrorMessage);
    }

    public function add($request, $response, $args)
    {
        $sErrorMessage = null;
        try {
            $poule = $this->getPoule( (int)$request->getParam("pouleid"), (int)$request->getParam("competitionid") );
            $roundNumber = $poule->getRound()->getNumber();
            $competition = $roundNumber->getCompetition();

            /** @var \Voetbal\Game|false $gameSer */
            $gameSer = $this->serializer->deserialize(json_encode($request->getParsedBody()), 'Voetbal\Game', 'json');
            if ( $gameSer === false ) {
                throw new \Exception("er kan geen wedstrijd worden toegevoegd o.b.v. de invoergegevens", E_ERROR);
            }

            foreach( $gameSer->getPlaces() as $gamePlaceSer ){
                $place = $poule->getPlace($gamePlaceSer->getPlace()->getNumber());
                if ( $place === null ) {
                    throw new \Exception("er kan geen deelnemer worden gevonden o.b.v. de invoergegevens", E_ERROR);
                }
                $gamePlaceSer->setPlace($place);
            }
            $game = new GameBase( $poule, $gameSer->getBatchNr(), $gameSer->getStartDateTime() );
            $game->setPlaces($gameSer->getPlaces());
            $refereePlace = null;
            if ( $gameSer->getRefereePlaceId() !== null ) {
                $refereePlace = $this->deserializeRefereeService->getPlace($roundNumber, $gameSer->getRefereePlaceId());
            }
            $field = $gameSer->getFieldNr() ? $competition->getField($gameSer->getFieldNr()) : null;
            $referee = $gameSer->getRefereeRank() ? $competition->getReferee($gameSer->getRefereeRank()) : null;
            $game = $this->service->editResource( $game, $field, $referee, $refereePlace );

            return $response
                ->withStatus(200)
                ->withHeader('Content-Type', 'application/json;charset=utf-8')
                ->write($this->serializer->serialize($game, 'json'));
        } catch (\Exception $e) {
            $sErrorMessage = $e->getMessage();
        }
        return $response->withStatus(422)->write($sErrorMessage);
    }

    public function edit($request, $response, $args)
    {
        try {
            $poule = $this->getPoule( (int)$request->getParam("pouleid"), (int)$request->getParam("competitionid") );

            /** @var \Voetbal\Game|false $game */
            $gameSer = $this->serializer->deserialize(json_encode($request->getParsedBody()), 'Voetbal\Game', 'json');
            if ( $gameSer === false ) {
                throw new \Exception("er kan geen wedstrijd worden gewijzigd o.b.v. de invoergegevens", E_ERROR);
            }

            $game = $this->repos->find($gameSer->getId());
            if ( $game === null ) {
                throw new \Exception("de wedstrijd kan niet gevonden worden obv id", E_ERROR);
            }
            if ( $game->getPoule() !== $poule ) {
                throw new \Exception("de poule van de wedstrijd komt niet overeen met de verstuurde poule", E_ERROR);
            }

            $this->gameScoreRepos->removeScores( $game );

            $game->setState( $gameSer->getState() );
            $game->setStartDateTime( $gameSer->getStartDateTime() );
            $this->service->addScores( $game, $gameSer->getScores()->toArray() );

            $this->repos->save( $game );

            return $response
                ->withStatus(200)
                ->withHeader('Content-Type', 'application/json;charset=utf-8')
                ->write($this->serializer->serialize($game, 'json'));
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