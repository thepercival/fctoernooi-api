<?php

declare(strict_types=1);

namespace App\Actions\Sports;

use App\Response\ErrorResponse;
use Exception;
use FCToernooi\Tournament;
use JMS\Serializer\DeserializationContext;
use JMS\Serializer\SerializationContext;
use Psr\Log\LoggerInterface;
use JMS\Serializer\SerializerInterface;
use Sports\Availability\Checker as AvailabilityChecker;
use Sports\Competition\Sport as CompetitionSport;
use Sports\Competition\Repository as CompetitionRepos;
use Sports\Competition\Field;
use Sports\Competition\Field\Repository as FieldRepository;
use Sports\Priority\Service as PriorityService;
use Sports\Competition\Sport\Repository as CompetitionSportRepository;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Actions\Action;
use Sports\Competition;

final class FieldAction extends Action
{
    protected FieldRepository $fieldRepos;
    protected CompetitionSportRepository $competitionSportRepos;
    protected CompetitionRepos $competitionRepos;

    public function __construct(
        LoggerInterface $logger,
        SerializerInterface $serializer,
        FieldRepository $fieldRepos,
        CompetitionSportRepository $competitionSportRepos,
        CompetitionRepos $competitionRepos
    ) {
        parent::__construct($logger, $serializer);
        $this->fieldRepos = $fieldRepos;
        $this->competitionSportRepos = $competitionSportRepos;
        $this->competitionRepos = $competitionRepos;
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

            $competitionSport = $this->competitionSportRepos->find((int)$args['competitionSportId']);
            if ($competitionSport === null || $competitionSport->getCompetition() !== $competition) {
                throw new Exception("de sport is onjuist", E_ERROR);
            }

            /** @var Field $field */
            $field = $this->serializer->deserialize($this->getRawData(), Field::class, 'json',
                                                    $this->getDeserializationContext());

            $availabilityChecker = new AvailabilityChecker();
            $availabilityChecker->checkFieldName($competition, $field->getName());

            $newField = new Field($competitionSport);
            $newField->setName($field->getName());

            $this->fieldRepos->save($newField);

            $json = $this->serializer->serialize($newField, 'json', $this->getSerializationContext());
            return $this->respondWithJson($response, $json);
        } catch (Exception $e) {
            return new ErrorResponse($e->getMessage(), 422);
        }
    }

    public function edit($request, $response, $args)
    {
        try {
            /** @var Competition $competition */
            $competition = $request->getAttribute("tournament")->getCompetition();

            /** @var CompetitionSport|null $competitionSport */
            $competitionSport = $this->competitionSportRepos->find((int)$args['competitionSportId']);
            if ($competitionSport === null || $competitionSport->getCompetition() !== $competition) {
                throw new Exception("de sport is onjuist", E_ERROR);
            }

            $field = $this->fieldRepos->find((int)$args["fieldId"]);
            if ($field === null || $field->getCompetitionSport() !== $competitionSport) {
                throw new Exception("het veld en de sport zijn een onjuiste combinatie", E_ERROR);
            }

            /** @var Field|false $fieldSer */
            $fieldSer = $this->serializer->deserialize($this->getRawData(), Field::class, 'json',
                                                       $this->getDeserializationContext());
            if ($fieldSer === false) {
                throw new Exception("het veld kon niet gevonden worden o.b.v. de invoer", E_ERROR);
            }

            $availabilityChecker = new AvailabilityChecker();
            $availabilityChecker->checkFieldPriority($competitionSport, $fieldSer->getPriority(), $field);
            $availabilityChecker->checkFieldName($competition, $fieldSer->getName(), $field);

            $field->setName($fieldSer->getName());
            $this->fieldRepos->save($field);

            $json = $this->serializer->serialize($field, 'json', $this->getSerializationContext());
            return $this->respondWithJson($response, $json);
        } catch (Exception $e) {
            return new ErrorResponse($e->getMessage(), 422);
        }
    }

    public function priorityUp($request, $response, $args)
    {
        try {
            /** @var Tournament $tournament */
            $tournament = $request->getAttribute("tournament");

            $competition = $tournament->getCompetition();

            $competitionSport = $this->competitionSportRepos->find((int)$args['competitionSportId']);
            if ($competitionSport === null || $competitionSport->getCompetition() !== $competition) {
                throw new Exception("de sport is onjuist", E_ERROR);
            }

            $field = $this->fieldRepos->find((int)$args["fieldId"]);
            if ($field === null || $field->getCompetitionSport() !== $competitionSport) {
                throw new Exception("het veld en de sport-configuratie zijn een onjuiste combinatie", E_ERROR);
            }

            $priorityService = new PriorityService($competitionSport->getFields()->toArray());
            $changedFields = $priorityService->upgrade($field);
            foreach ($changedFields as $changedField) {
                $this->fieldRepos->save($changedField);
            }

            return $response->withStatus(200);
        } catch (Exception $e) {
            return new ErrorResponse($e->getMessage(), 422);
        }
    }

    public function remove(Request $request, Response $response, $args): Response
    {
        try {
            /** @var Competition $competition */
            $competition = $request->getAttribute("tournament")->getCompetition();

            $competitionSport = $this->competitionSportRepos->find((int)$args['competitionSportId']);
            if ($competitionSport === null || $competitionSport->getCompetition() !== $competition) {
                throw new Exception("de sport is onjuist", E_ERROR);
            }

            $field = $this->fieldRepos->find((int)$args["fieldId"]);
            if ($field === null || $field->getCompetitionSport() !== $competitionSport) {
                throw new Exception("het veld en de sport-configuratie zijn een onjuiste combinatie", E_ERROR);
            }

            $competitionSport->getFields()->removeElement($field);
            $this->fieldRepos->remove($field);

            $priorityService = new PriorityService($competitionSport->getFields()->toArray());
            $changedFields = $priorityService->upgrade($field);
            foreach ($changedFields as $changedField) {
                $this->fieldRepos->save($changedField);
            }

            return $response->withStatus(200);
        } catch (Exception $e) {
            return new ErrorResponse($e->getMessage(), 422);
        }
    }
}
