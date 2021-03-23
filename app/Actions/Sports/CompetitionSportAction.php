<?php

declare(strict_types=1);

namespace App\Actions\Sports;

use App\Response\ErrorResponse;
use JMS\Serializer\DeserializationContext;
use JMS\Serializer\SerializationContext;
use Psr\Log\LoggerInterface;
use JMS\Serializer\SerializerInterface;
use Sports\Competition\Field;
use Sports\Sport;
use Sports\Structure\Repository as StructureRepository;
use Sports\Competition\Sport\Repository as CompetitionSportRepository;
use Sports\Competition\Sport\Service as CompetitionSportService;
use Sports\Sport\Repository as SportRepository;
use Sports\Competition\Field\Repository as FieldRepository;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Actions\Action;
use Sports\Competition;
use Sports\Competition\Sport as CompetitionSport;

final class CompetitionSportAction extends Action
{
    public function __construct(
        LoggerInterface $logger,
        SerializerInterface $serializer,
        protected SportRepository $sportRepos,
        protected StructureRepository $structureRepos,
        protected CompetitionSportRepository $competitionSportRepos,
        protected FieldRepository $fieldRepos
    ) {
        parent::__construct($logger, $serializer);
    }

    protected function getDeserializationContext(): DeserializationContext
    {
        return DeserializationContext::create()->setGroups(['Default', 'noReference']);
    }

    protected function getSerializationContext(): SerializationContext
    {
        return SerializationContext::create()->setGroups(['Default', 'noReference']);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param array<string, int|string> $args
     * @return Response
     */
    public function add(Request $request, Response $response, array $args): Response
    {
        try {
            /** @var Competition $competition */
            $competition = $request->getAttribute('tournament')->getCompetition();

            /** @var Sport $sportSer */
            $sportSer = $this->serializer->deserialize($this->getRawData(), Sport::class, 'json');

            $sport = $this->sportRepos->find($sportSer->getId());
            if ($sport === null) {
                throw new \Exception('de sport van de configuratie kan niet gevonden worden', E_ERROR);
            }
            if ($competition->getSport($sport) !== null) {
                throw new \Exception('de sport wordt al gebruikt binnen de competitie', E_ERROR);
            }

            $competitionSportService = new CompetitionSportService();
            $structure = $this->structureRepos->getStructure($competition);
            $newCompetitionSport = $competitionSportService->createDefault($sport, $competition, $structure);
            $this->competitionSportRepos->customAdd($newCompetitionSport, $structure);

            // add one field!!
            $field = new Field($newCompetitionSport);
            $field->setName(substr($newCompetitionSport->getSport()->getName(), 0, 1) . '1');
            $this->fieldRepos->save($field);

            $json = $this->serializer->serialize($newCompetitionSport, 'json', $this->getSerializationContext());
            return $this->respondWithJson($response, $json);
        } catch (\Exception $exception) {
            return new ErrorResponse($exception->getMessage(), 422);
        }
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param array<string, int|string> $args
     * @return Response
     */
    public function edit(Request $request, Response $response, array $args): Response
    {
        try {
            /** @var Competition $competition */
            $competition = $request->getAttribute('tournament')->getCompetition();

            /** @var CompetitionSport|false $competitionSportSer */
            $competitionSportSer = $this->serializer->deserialize(
                $this->getRawData(),
                CompetitionSport::class,
                'json',
                $this->getDeserializationContext()
            );

            $sport = $this->sportRepos->findOneBy(['name' => $competitionSportSer->getSport()->getName()]);
            if ($sport === null) {
                throw new \Exception('de sport van de configuratie kan niet gevonden worden', E_ERROR);
            }
            $competitionSport = $competition->getSport($sport);
            if ($competitionSport === null) {
                throw new \Exception('de competitionSport is niet gevonden bij de competitie', E_ERROR);
            }
            $this->competitionSportRepos->save($competitionSport);

            $json = $this->serializer->serialize($competitionSport, 'json', $this->getSerializationContext());
            return $this->respondWithJson($response, $json);
        } catch (\Exception $exception) {
            return new ErrorResponse($exception->getMessage(), 422);
        }
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param array<string, int|string> $args
     * @return Response
     */
    public function remove(Request $request, Response $response, array $args): Response
    {
        try {
            /** @var Competition $competition */
            $competition = $request->getAttribute('tournament')->getCompetition();

            $competitionSport = $this->getCompetitionSportFromInput((int)$args['competitionSportId'], $competition);

            $this->competitionSportRepos->remove($competitionSport);

            return $response->withStatus(200);
        } catch (\Exception $exception) {
            return new ErrorResponse($exception->getMessage(), 422);
        }
    }

    protected function getCompetitionSportFromInput(int $id, Competition $competition): CompetitionSport
    {
        $competitionSport = $this->competitionSportRepos->find($id);
        if ($competitionSport === null) {
            throw new \Exception('de sport kon niet gevonden worden o.b.v. de invoer', E_ERROR);
        }
        if ($competitionSport->getCompetition() !== $competition) {
            throw new \Exception(
                'de competitie van de sport komt niet overeen met de verstuurde competitie',
                E_ERROR
            );
        }
        return $competitionSport;
    }
}
