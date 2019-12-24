<?php
/**
 * Created by PhpStorm.
 * User: coen
 * Date: 2-2-17
 * Time: 21:49
 */

namespace App\Actions\Voetbal;

use JMS\Serializer\Serializer;
use Voetbal\Competition\Service as CompetitionService;
use Voetbal\Competition\Repository as CompetitionRepository;
use Voetbal\League\Repository as LeagueRepository;
use Voetbal\Season\Repository as SeasonRepository;
use Voetbal\Association\Repository as AssociationRepository;

final class Competition
{
    /**
     * @var CompetitionService
     */
    protected $service;
    /**
     * @var CompetitionRepository
     */
	protected $repos;
    /**
     * @var LeagueRepository
     */
    protected $leagueRepos;
    /**
     * @var SeasonRepository
     */
    protected $seasonRepos;
    /**
     * @var Serializer
     */
	protected $serializer;

	public function __construct(
        CompetitionService $service,
        CompetitionRepository $repos,
        LeagueRepository $leagueRepos,
        SeasonRepository $seasonRepos,
        Serializer $serializer
    )
	{
        $this->service = $service;
		$this->repos = $repos;
        $this->leagueRepos = $leagueRepos;
        $this->seasonRepos = $seasonRepos;
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
		return $response->withStatus(404)->write('geen competitieseizoen met het opgegeven id gevonden');
	}


	public function add($request, $response, $args)
    {
        try {
            $leagueid = (int) $request->getParam("leagueid");
            $league = $this->leagueRepos->find($leagueid);
            if ( $league === null ) {
                throw new \Exception("er kan geen league worden gevonden o.b.v. de invoergegevens", E_ERROR);
            }

            $seasonid = (int) $request->getParam("seasonid");
            $season = $this->seasonRepos->find($seasonid);
            if ( $season === null ) {
                throw new \Exception("er kan geen seizoen worden gevonden o.b.v. de invoergegevens", E_ERROR);
            }

            /** @var \Voetbal\Competition|false $competitionSer */
            $competitionSer = $this->serializer->deserialize(json_encode($request->getParsedBody()), 'Voetbal\Competition', 'json');
            if ( $competitionSer === false ) {
                throw new \Exception("er kan geen competitie worden toegevoegd o.b.v. de invoergegevens", E_ERROR);
            }

            $sameCompetition = $this->repos->findExt( $league, $season );
            if ( $sameCompetition !== false ){
                throw new \Exception("de competitie bestaat al", E_ERROR );
            }

            $competition = $this->service->create( $league, $season,$competitionSer->getRuleSet(),$competitionSer->getStartDateTime() );
            $this->repos->save( $competition );

            return $response
                ->withStatus(201)
                ->withHeader('Content-Type', 'application/json;charset=utf-8')
                ->write($this->serializer->serialize( $competition, 'json'));
            ;
        }
        catch( \Exception $e ){
            return $response->withStatus(404)->write( $e->getMessage() );
        }
    }

    public function edit( $request, $response, $args)
    {
        try {
            /** @var \Voetbal\Competition|false $competitionSer */
            $competitionSer = $this->serializer->deserialize(json_encode($request->getParsedBody()), 'Voetbal\Competition', 'json');
            if ( $competitionSer === false ) {
                throw new \Exception("er kan competitieseizoen worden toegevoegd o.b.v. de invoergegevens", E_ERROR);
            }

            $competition = $this->repos->find($args['id']);
            if ( $competition === null ) {
                throw new \Exception("het competitieseizoen kon niet gevonden worden o.b.v. de invoer", E_ERROR);
            }

            $competitionRet = $this->service->changeStartDateTime( $competition, $competitionSer->getStartDateTime() );
            $competitionRet = $this->service->changeRuleSet( $competition, $competitionSer->getRuleSet() );

            return $response
                ->withStatus(201)
                ->withHeader('Content-Type', 'application/json;charset=utf-8')
                ->write($this->serializer->serialize( $competitionRet, 'json'));
            ;
        }
        catch( \Exception $e ){
            return $response->withStatus(404)->write( $e->getMessage() );
        }
    }

	public function remove( $request, $response, $args)
	{
		$competition = $this->repos->find($args['id']);
		try {
			$this->repos->remove($competition);
			return $response->withStatus(204);
		}
		catch( \Exception $e ){
            return $response->withStatus(404, $e->getMessage() );
		}
	}
}