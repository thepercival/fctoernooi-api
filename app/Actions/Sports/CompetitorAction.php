<?php

namespace App\Actions\Sports;

use App\Response\ErrorResponse;
use FCToernooi\Tournament;
use Psr\Log\LoggerInterface;
use JMS\Serializer\SerializerInterface;
use FCToernooi\Competitor\Repository as CompetitorRepository;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Actions\Action;
use FCToernooi\Competitor;
use Sports\Availability\Checker as AvailabilityChecker;

final class CompetitorAction extends Action
{
    /**
     * @var CompetitorRepository
     */
    protected $competitorRepos;

    public function __construct(
        LoggerInterface $logger,
        SerializerInterface $serializer,
        CompetitorRepository $competitorRepos
    ) {
        parent::__construct($logger, $serializer);
        $this->competitorRepos = $competitorRepos;
    }

    public function add(Request $request, Response $response, $args): Response
    {
        try {
            /** @var Competitor $competitor */
            $competitor = $this->serializer->deserialize($this->getRawData(), Competitor::class, 'json');
            /** @var Tournament $tournament */
            $tournament = $request->getAttribute("tournament");

            $availabilityChecker = new AvailabilityChecker();
            $availabilityChecker->checkCompetitorName($tournament->getCompetitors()->toArray(), $competitor->getName());
            $availabilityChecker->checkCompetitorPlaceLocation($tournament->getCompetitors()->toArray(), $competitor );

            $newCompetitor = new Competitor(
                $tournament,
                $competitor->getPouleNr(),
                $competitor->getPlaceNr(),
                $competitor->getName()
            );
            $newCompetitor->setRegistered($competitor->getRegistered());
            $newCompetitor->setInfo($competitor->getInfo());

            $this->competitorRepos->save($newCompetitor);

            $json = $this->serializer->serialize($newCompetitor, 'json');
            return $this->respondWithJson($response, $json);
        } catch (\Exception $e) {
            return new ErrorResponse($e->getMessage(), 422);
        }
    }

    public function edit($request, $response, $args)
    {
        try {
            /** @var Competitor $competitorSer */
            $competitorSer = $this->serializer->deserialize($this->getRawData(), Competitor::class, 'json');

            /** @var Tournament $tournament */
            $tournament = $request->getAttribute("tournament");

            $competitor = $this->getCompetitorFromInput((int)$args["competitorId"], $tournament);

            $availabilityChecker = new AvailabilityChecker();
            $availabilityChecker->checkCompetitorName($tournament->getCompetitors()->toArray(), $competitor->getName(), $competitor);
            $availabilityChecker->checkCompetitorPlaceLocation($tournament->getCompetitors()->toArray(), $competitor, $competitor );

            $competitor->setName($competitorSer->getName());
            $competitor->setRegistered($competitorSer->getRegistered());
            $competitor->setInfo($competitorSer->getInfo());
            $this->competitorRepos->save($competitor);

            $json = $this->serializer->serialize($competitor, 'json');
            return $this->respondWithJson($response, $json);
        } catch (\Exception $e) {
            return new ErrorResponse($e->getMessage(), 422);
        }
    }

    protected function getCompetitorFromInput(int $id, Tournament $tournament): Competitor
    {
        $competitor = $this->competitorRepos->find($id);
        if ($competitor === null) {
            throw new \Exception("de deelnemer kon niet gevonden worden o.b.v. de invoer", E_ERROR);
        }
        if ($competitor->getTournament() !== $tournament) {
            throw new \Exception("de deelnemer is van een ander toernooi", E_ERROR);
        }
        return $competitor;
    }
}
