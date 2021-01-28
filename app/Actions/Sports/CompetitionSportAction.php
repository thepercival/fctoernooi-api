<?php

declare(strict_types=1);

namespace App\Actions\Sports;

use App\Response\ErrorResponse;
use JMS\Serializer\DeserializationContext;
use JMS\Serializer\SerializationContext;
use Psr\Log\LoggerInterface;
use JMS\Serializer\SerializerInterface;
use Sports\Structure\Repository as StructureRepository;
use Sports\Competition\Sport\Repository as CompetitionSportRepository;
use Sports\Competition\Sport\Service as CompetitionSportService;
use Sports\Sport\Repository as SportRepository;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Actions\Action;
use Sports\Competition;
use Sports\Competition\Sport as CompetitionSport;

final class CompetitionSportAction extends Action
{
    protected SportRepository $sportRepos;
    protected StructureRepository $structureRepos;
    protected CompetitionSportRepository $competitionSportRepos;

    public function __construct(
        LoggerInterface $logger,
        SerializerInterface $serializer,
        SportRepository $sportRepos,
        StructureRepository $structureRepos,
        CompetitionSportRepository $competitionSportRepos
    ) {
        parent::__construct($logger, $serializer);
        $this->sportRepos = $sportRepos;
        $this->structureRepos = $structureRepos;
        $this->competitionSportRepos = $competitionSportRepos;
    }

    protected function getDeserializationContext()
    {
        return DeserializationContext::create()->setGroups(['Default', 'noReference']);
    }

    protected function getSerializationContext()
    {
        return SerializationContext::create()->setGroups(['Default', 'noReference']);
    }

    public function add(Request $request, Response $response, $args): Response
    {
        try {
            /** @var Competition $competition */
            $competition = $request->getAttribute("tournament")->getCompetition();

            /** @var CompetitionSport $competitionSport */
            $competitionSport = $this->serializer->deserialize(
                $this->getRawData(),
                CompetitionSport::class,
                'json',
                $this->getDeserializationContext()
            );

            $sport = $this->sportRepos->findOneBy(["name" => $competitionSport->getSport()->getName()]);
            if ($sport === null) {
                throw new \Exception("de sport van de configuratie kan niet gevonden worden", E_ERROR);
            }
            if ($competition->getSport($sport) !== null) {
                throw new \Exception("de sport wordt al gebruikt binnen de competitie", E_ERROR);
            }

            $competitionSportService = new CompetitionSportService();
            $structure = $this->structureRepos->getStructure($competition);
            $newCompetitionSport = $competitionSportService->createDefault($sport, $competition, $structure);
            $this->competitionSportRepos->customAdd($newCompetitionSport, $structure);

            $json = $this->serializer->serialize($newCompetitionSport, 'json', $this->getSerializationContext());
            return $this->respondWithJson($response, $json);
        } catch (\Exception $e) {
            return new ErrorResponse($e->getMessage(), 422);
        }
    }

    public function edit($request, $response, $args)
    {
        try {
            /** @var Competition $competition */
            $competition = $request->getAttribute("tournament")->getCompetition();

            /** @var CompetitionSport|false $competitionSportSer */
            $competitionSportSer = $this->serializer->deserialize(
                $this->getRawData(),
                CompetitionSport::class,
                'json',
                $this->getDeserializationContext()
            );

            $sport = $this->sportRepos->findOneBy(["name" => $competitionSportSer->getSport()->getName()]);
            if ($sport === null) {
                throw new \Exception("de sport van de configuratie kan niet gevonden worden", E_ERROR);
            }
            $competitionSport = $competition->getSport($sport);
            if ($competitionSport === null) {
                throw new \Exception("de competitionSport is niet gevonden bij de competitie", E_ERROR);
            }
            $this->competitionSportRepos->save($competitionSport);

            $json = $this->serializer->serialize($competitionSport, 'json', $this->getSerializationContext());
            return $this->respondWithJson($response, $json);
        } catch (\Exception $e) {
            return new ErrorResponse($e->getMessage(), 422);
        }
    }

    public function remove(Request $request, Response $response, $args): Response
    {
        try {
            /** @var Competition $competition */
            $competition = $request->getAttribute("tournament")->getCompetition();

            $competitionSport = $this->getCompetitionSportFromInput((int)$args["competitionSportId"], $competition);

            $this->competitionSportRepos->remove($competitionSport);

            return $response->withStatus(200);
        } catch (\Exception $e) {
            return new ErrorResponse($e->getMessage(), 422);
        }
    }

    protected function getCompetitionSportFromInput(int $id, Competition $competition): CompetitionSport
    {
        $competitionSport = $this->competitionSportRepos->find($id);
        if ($competitionSport === null) {
            throw new \Exception("de sport kon niet gevonden worden o.b.v. de invoer", E_ERROR);
        }
        if ($competitionSport->getCompetition() !== $competition) {
            throw new \Exception(
                "de competitie van de sport komt niet overeen met de verstuurde competitie", E_ERROR
            );
        }
        return $competitionSport;
    }
}
