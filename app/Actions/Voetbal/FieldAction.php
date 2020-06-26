<?php
/**
 * Created by PhpStorm.
 * User: coen
 * Date: 14-11-17
 * Time: 14:02
 */

namespace App\Actions\Voetbal;

use App\Response\ErrorResponse;
use App\Response\ForbiddenResponse as ForbiddenResponse;
use FCToernooi\Tournament;
use Psr\Log\LoggerInterface;
use JMS\Serializer\SerializerInterface;
use Voetbal\Availability\Checker as AvailabilityChecker;
use Voetbal\Competition\Repository as CompetitionRepos;
use Voetbal\Field as FieldBase;
use Voetbal\Field\Repository as FieldRepository;
use Voetbal\Priority\Service as PriorityService;
use Voetbal\Sport\Config\Repository as SportConfigRepository;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Actions\Action;
use Voetbal\Field;
use Voetbal\Competition;
use Voetbal\Sport\Config as SportConfig;

final class FieldAction extends Action
{
    /**
     * @var FieldRepository
     */
    protected $fieldRepos;
    /**
     * @var SportConfigRepository
     */
    protected $sportConfigRepos;
    /**
     * @var CompetitionRepos
     */
    protected $competitionRepos;

    public function __construct(
        LoggerInterface $logger,
        SerializerInterface $serializer,
        FieldRepository $fieldRepos,
        SportConfigRepository $sportConfigRepos,
        CompetitionRepos $competitionRepos
    ) {
        parent::__construct($logger, $serializer);
        $this->fieldRepos = $fieldRepos;
        $this->sportConfigRepos = $sportConfigRepos;
        $this->competitionRepos = $competitionRepos;
    }

    public function add(Request $request, Response $response, $args): Response
    {
        try {
            /** @var Competition $competition */
            $competition = $request->getAttribute("tournament")->getCompetition();

            $sportConfig = $this->sportConfigRepos->find((int)$args['sportconfigId']);
            if ($sportConfig === null || $sportConfig->getCompetition() !== $competition) {
                throw new \Exception("de sport-configuratie is onjuist", E_ERROR);
            }

            /** @var Field $field */
            $field = $this->serializer->deserialize($this->getRawData(), Field::class, 'json');

            $availabilityChecker = new AvailabilityChecker();
            $availabilityChecker->checkFieldName($competition, $field->getName());

            $newField = new FieldBase($sportConfig);
            $newField->setName($field->getName());

            $this->fieldRepos->save($newField);

            $json = $this->serializer->serialize($newField, 'json');
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

            /** @var SportConfig|null $sportConfig */
            $sportConfig = $this->sportConfigRepos->find((int)$args['sportconfigId']);
            if ($sportConfig === null || $sportConfig->getCompetition() !== $competition) {
                throw new \Exception("de sport-configuratie is onjuist", E_ERROR);
            }

            $field = $this->fieldRepos->find((int)$args["fieldId"]);
            if ($field === null || $field->getSportConfig() !== $sportConfig) {
                throw new \Exception("het veld en de sport-configuratie zijn een onjuiste combinatie", E_ERROR);
            }

            /** @var Field|false $fieldSer */
            $fieldSer = $this->serializer->deserialize($this->getRawData(), Field::class, 'json');
            if ($fieldSer === false) {
                throw new \Exception("het veld kon niet gevonden worden o.b.v. de invoer", E_ERROR);
            }

            $availabilityChecker = new AvailabilityChecker();
            $availabilityChecker->checkFieldPriority($sportConfig, $fieldSer->getPriority(), $field);
            $availabilityChecker->checkFieldName($competition, $fieldSer->getName(), $field);

            $field->setName($fieldSer->getName());
            $this->fieldRepos->save($field);

            $json = $this->serializer->serialize($field, 'json');
            return $this->respondWithJson($response, $json);
        } catch (\Exception $e) {
            return new ErrorResponse($e->getMessage(), 422);
        }
    }

    public function priorityUp($request, $response, $args)
    {
        try {
            /** @var Tournament $tournament */
            $tournament = $request->getAttribute("tournament");

            $competition = $tournament->getCompetition();

            $sportConfig = $this->sportConfigRepos->find((int)$args['sportconfigId']);
            if ($sportConfig === null || $sportConfig->getCompetition() !== $competition) {
                throw new \Exception("de sport-configuratie is onjuist", E_ERROR);
            }

            $field = $this->fieldRepos->find((int)$args["fieldId"]);
            if ($field === null || $field->getSportConfig() !== $sportConfig) {
                throw new \Exception("het veld en de sport-configuratie zijn een onjuiste combinatie", E_ERROR);
            }

            $priorityService = new PriorityService($sportConfig->getFields()->toArray());
            $changedFields = $priorityService->upgrade($field);
            foreach ($changedFields as $changedField) {
                $this->fieldRepos->save($changedField);
            }

            return $response->withStatus(200);
        } catch (\Exception $e) {
            return new ErrorResponse($e->getMessage(), 422);
        }
    }

    public function remove(Request $request, Response $response, $args): Response
    {
        try {
            /** @var Competition $competition */
            $competition = $request->getAttribute("tournament")->getCompetition();

            $sportConfig = $this->sportConfigRepos->find((int)$args['sportconfigId']);
            if ($sportConfig === null || $sportConfig->getCompetition() !== $competition) {
                throw new \Exception("de sport-configuratie is onjuist", E_ERROR);
            }

            $field = $this->fieldRepos->find((int)$args["fieldId"]);
            if ($field === null || $field->getSportConfig() !== $sportConfig) {
                throw new \Exception("het veld en de sport-configuratie zijn een onjuiste combinatie", E_ERROR);
            }

            $sportConfig->getFields()->removeElement($field);
            $this->fieldRepos->remove($field);

            $priorityService = new PriorityService($sportConfig->getFields()->toArray());
            $changedFields = $priorityService->upgrade($field);
            foreach ($changedFields as $changedField) {
                $this->fieldRepos->save($changedField);
            }

            return $response->withStatus(200);
        } catch (\Exception $e) {
            return new ErrorResponse($e->getMessage(), 422);
        }
    }
}
