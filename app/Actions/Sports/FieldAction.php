<?php

declare(strict_types=1);

namespace App\Actions\Sports;

use App\Actions\Action;
use App\Response\ErrorResponse;
use Exception;
use FCToernooi\Tournament;
use JMS\Serializer\DeserializationContext;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\SerializerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Sports\Availability\Checker as AvailabilityChecker;
use Sports\Competition\Field;
use Sports\Competition\Field\Repository as FieldRepository;
use Sports\Competition\Repository as CompetitionRepos;
use Sports\Competition\Sport as CompetitionSport;
use Sports\Competition\Sport\Repository as CompetitionSportRepository;
use Sports\Priority\Service as PriorityService;

final class FieldAction extends Action
{
    public function __construct(
        LoggerInterface $logger,
        SerializerInterface $serializer,
        protected FieldRepository $fieldRepos,
        protected CompetitionSportRepository $competitionSportRepos,
        protected CompetitionRepos $competitionRepos
    ) {
        parent::__construct($logger, $serializer);
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
            /** @var Tournament $tournament */
            $tournament = $request->getAttribute("tournament");

            $competition = $tournament->getCompetition();

            $competitionSport = $this->competitionSportRepos->find((int)$args['competitionSportId']);
            if ($competitionSport === null || $competitionSport->getCompetition() !== $competition) {
                throw new Exception('de sport is onjuist', E_ERROR);
            }

            /** @var Field $field */
            $field = $this->serializer->deserialize(
                $this->getRawData($request),
                Field::class,
                'json'
            );

            $availabilityChecker = new AvailabilityChecker();
            $availabilityChecker->checkFieldName($competition, (string)$field->getName());

            $newField = new Field($competitionSport);
            $newField->setName($field->getName());

            $this->fieldRepos->save($newField);

            $json = $this->serializer->serialize($newField, 'json');
            return $this->respondWithJson($response, $json);
        } catch (Exception $exception) {
            return new ErrorResponse($exception->getMessage(), 422, $this->logger);
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
            /** @var Tournament $tournament */
            $tournament = $request->getAttribute("tournament");

            $competition = $tournament->getCompetition();

            /** @var CompetitionSport|null $competitionSport */
            $competitionSport = $this->competitionSportRepos->find((int)$args['competitionSportId']);
            if ($competitionSport === null || $competitionSport->getCompetition() !== $competition) {
                throw new Exception('de sport is onjuist', E_ERROR);
            }

            $field = $this->fieldRepos->find((int)$args['fieldId']);
            if ($field === null || $field->getCompetitionSport() !== $competitionSport) {
                throw new Exception('het veld en de sport zijn een onjuiste combinatie', E_ERROR);
            }

            /** @var Field|false $fieldSer */
            $fieldSer = $this->serializer->deserialize(
                $this->getRawData($request),
                Field::class,
                'json'
            );
            if ($fieldSer === false) {
                throw new Exception('het veld kon niet gevonden worden o.b.v. de invoer', E_ERROR);
            }

            $availabilityChecker = new AvailabilityChecker();
            $availabilityChecker->checkFieldPriority($competitionSport, $fieldSer->getPriority(), $field);
            $availabilityChecker->checkFieldName($competition, (string)$fieldSer->getName(), $field);

            $field->setName($fieldSer->getName());
            $this->fieldRepos->save($field);

            $json = $this->serializer->serialize($field, 'json');
            return $this->respondWithJson($response, $json);
        } catch (Exception $exception) {
            return new ErrorResponse($exception->getMessage(), 422, $this->logger);
        }
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param array<string, int|string> $args
     * @return Response
     */
    public function priorityUp(Request $request, Response $response, array $args): Response
    {
        try {
            /** @var Tournament $tournament */
            $tournament = $request->getAttribute('tournament');

            $competition = $tournament->getCompetition();

            $competitionSport = $this->competitionSportRepos->find((int)$args['competitionSportId']);
            if ($competitionSport === null || $competitionSport->getCompetition() !== $competition) {
                throw new Exception('de sport is onjuist', E_ERROR);
            }

            $field = $this->fieldRepos->find((int)$args['fieldId']);
            if ($field === null || $field->getCompetitionSport() !== $competitionSport) {
                throw new Exception('het veld en de sport-configuratie zijn een onjuiste combinatie', E_ERROR);
            }

            $priorityService = new PriorityService(array_values($competitionSport->getFields()->toArray()));
            $changedFields = $priorityService->upgrade($field);
            foreach ($changedFields as $changedField) {
                if ($changedField instanceof Field) {
                    $this->fieldRepos->save($changedField);
                }
            }

            return $response->withStatus(200);
        } catch (Exception $exception) {
            return new ErrorResponse($exception->getMessage(), 422, $this->logger);
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
            /** @var Tournament $tournament */
            $tournament = $request->getAttribute("tournament");

            $competition = $tournament->getCompetition();

            $competitionSport = $this->competitionSportRepos->find((int)$args['competitionSportId']);
            if ($competitionSport === null || $competitionSport->getCompetition() !== $competition) {
                throw new Exception('de sport is onjuist', E_ERROR);
            }

            $field = $this->fieldRepos->find((int)$args['fieldId']);
            if ($field === null || $field->getCompetitionSport() !== $competitionSport) {
                throw new Exception('het veld en de sport-configuratie zijn een onjuiste combinatie', E_ERROR);
            }

            $competitionSport->getFields()->removeElement($field);
            $this->fieldRepos->remove($field);

            $priorityService = new PriorityService(array_values($competitionSport->getFields()->toArray()));
            $changedFields = $priorityService->upgrade($field);
            foreach ($changedFields as $changedField) {
                if ($changedField instanceof Field) {
                    $this->fieldRepos->save($changedField);
                }
            }

            return $response->withStatus(200);
        } catch (Exception $exception) {
            return new ErrorResponse($exception->getMessage(), 422, $this->logger);
        }
    }
}
