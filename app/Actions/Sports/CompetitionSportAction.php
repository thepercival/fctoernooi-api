<?php

declare(strict_types=1);

namespace App\Actions\Sports;

use App\Actions\Action;
use App\Response\ErrorResponse;
use Doctrine\Common\Collections\Collection;
use Exception;
use FCToernooi\Tournament;
use JMS\Serializer\DeserializationContext;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\SerializerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Sports\Competition;
use Sports\Competition\Field;
use Sports\Competition\Field\Repository as FieldRepository;
use Sports\Competition\Sport as CompetitionSport;
use Sports\Competition\Sport\Repository as CompetitionSportRepository;
use Sports\Competition\Sport\Service as CompetitionSportService;
use Sports\Round;
use Sports\Sport;
use Sports\Sport\Repository as SportRepository;
use Sports\Structure;
use Sports\Structure\Repository as StructureRepository;
use SportsHelpers\Sport\PersistVariant;
use SportsHelpers\Sport\Variant\Against\GamesPerPlace as AgainstGpp;
use SportsHelpers\Sport\Variant\Against\H2h as AgainstH2h;
use SportsHelpers\Sport\Variant\AllInOneGame;
use SportsHelpers\Sport\Variant\Single;

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

    /**
     * @return list<string>
     */
    protected function getDeserializationGroups(): array
    {
        return ['Default', 'noReference'];
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
            /** @var Tournament $tournament */
            $tournament = $request->getAttribute("tournament");

            $competition = $tournament->getCompetition();

            /** @var CompetitionSport $serializedCompSport */
            $serializedCompSport = $this->deserialize(
                $request,
                CompetitionSport::class,
                $this->getDeserializationGroups()
            );

            $sport = $this->sportRepos->find($serializedCompSport->getSport()->getId());
            if ($sport === null) {
                throw new \Exception('de sport kan niet gevonden worden', E_ERROR);
            }

            $structure = $this->structureRepos->getStructure($competition);

            if (!$competition->hasMultipleSports()) {
                $firstCompetitionSport = $competition->getSingleSport();
                if ($firstCompetitionSport->createVariant() instanceof AgainstH2h) {
                    $firstCompetitionSport->convertAgainst();
                    $this->competitionSportRepos->save($firstCompetitionSport);
                }
            }

            $sportPersistVariant = $serializedCompSport->createVariant()->toPersistVariant();
            $fieldNames = $this->createFieldNames($serializedCompSport, $competition->getSports());
            $newCompSport = $this->addHelper($sportPersistVariant, $fieldNames, $sport, $competition, $structure);

            $json = $this->serializer->serialize($newCompSport, 'json', $this->getSerializationContext());
            return $this->respondWithJson($response, $json);
        } catch (\Exception $exception) {
            return new ErrorResponse($exception->getMessage(), 422);
        }
    }

    /**
     * @param CompetitionSport $newCompetitionSport
     * @param Collection<int|string, CompetitionSport>|null $existingCompetitionSports
     * @return list<string>
     */
    protected function createFieldNames(
        CompetitionSport $newCompetitionSport,
        Collection|null $existingCompetitionSports = null
    ): array {
        $fieldPrefix = $this->createFieldNamePrefix($newCompetitionSport->getSport(), $existingCompetitionSports);
        $fieldNames = [];
        foreach ($newCompetitionSport->getFields() as $field) {
            $fieldNames[] = $fieldPrefix . $field->getPriority();
        }
        return $fieldNames;
    }

    /**
     * @param Sport $sport
     * @param Collection<int|string, CompetitionSport> $existingCompetitionSports
     * @return string
     */
    protected function createFieldNamePrefix(Sport $sport, Collection|null $existingCompetitionSports): string
    {
        $chars = str_split($sport->getName());
        $char = array_shift($chars);
        if ($char === null) {
            return '?';
        }
        $prefix = mb_strtoupper($char);
        if ($existingCompetitionSports === null) {
            return $prefix;
        }
        if (!$this->prefixInExisting($prefix, $existingCompetitionSports)) {
            return $prefix;
        }
        $char = array_shift($chars);
        if ($char === null) {
            return '?';
        }
        if ($this->prefixInExisting($prefix . mb_strtoupper($char), $existingCompetitionSports)) {
            return $prefix . '?';
        }
        return $prefix . mb_strtoupper($char);
    }

    /**
     * @param string $prefix
     * @param Collection<int|string, CompetitionSport> $existingCompetitionSports
     * @return bool
     */
    protected function prefixInExisting(string $prefix, Collection $existingCompetitionSports): bool
    {
        foreach ($existingCompetitionSports as $existingCompetitionSport) {
            $firstField = $existingCompetitionSport->getFields()->first();
            if ($firstField === false) {
                continue;
            }
            $firstFieldName = $firstField->getName();
            if ($firstFieldName !== null && $prefix === mb_substr($firstFieldName, 0, mb_strlen($prefix))) {
                return true;
            }
        }
        return false;
    }

//    /**
//     * @param Request $request
//     * @param Response $response
//     * @param array<string, int|string> $args
//     * @return Response
//     */
//    public function edit(Request $request, Response $response, array $args): Response
//    {
//        try {
//            /** @var Competition $competition */
//            $competition = $request->getAttribute('tournament')->getCompetition();
//
//            /** @var CompetitionSport $competitionSportSer */
//            $competitionSportSer = $this->serializer->deserialize(
//                $this->getRawData($request),
//                CompetitionSport::class,
//                'json',
//                $this->getDeserializationContext()
//            );
//
//            $sport = $this->sportRepos->findOneBy(['name' => $competitionSportSer->getSport()->getName()]);
//            if ($sport === null) {
//                throw new \Exception('de sport van de configuratie kan niet gevonden worden', E_ERROR);
//            }
//            $competitionSport = $competition->getSport($sport);
//            if ($competitionSport === null) {
//                throw new \Exception('de competitionSport is niet gevonden bij de competitie', E_ERROR);
//            }
//            $this->competitionSportRepos->save($competitionSport);
//
//            $json = $this->serializer->serialize($competitionSport, 'json', $this->getSerializationContext());
//            return $this->respondWithJson($response, $json);
//        } catch (\Exception $exception) {
//            return new ErrorResponse($exception->getMessage(), 422);
//        }
//    }

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

            $structure = $this->structureRepos->getStructure($competition);

            $competitionSport = $this->getCompetitionSportFromInput((int)$args['competitionSportId'], $competition);

            if (count($competition->getSports()) <= 1) {
                throw new \Exception('er moet minimaal 1 sport zijn', E_ERROR);
            }

            $this->removeHelper($competitionSport, $structure);

            if (!$competition->hasMultipleSports()) {
                $firstCompetitionSport = $competition->getSingleSport();
                $variant = $firstCompetitionSport->createVariant();
                if ($variant instanceof AgainstGpp && !$variant->hasMultipleSidePlaces()) {
                    $firstCompetitionSport->convertAgainst();
                    $this->competitionSportRepos->save($firstCompetitionSport, true);
                }
            }

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

    protected function removeHelper(CompetitionSport $competitionSport, Structure $structure): void
    {
        // (new CompetitionSportService())->remove($competitionSport, $structure);
        $this->competitionSportRepos->customRemove($competitionSport, $structure);
    }

    /**
     * @param PersistVariant $sportPersistVariant
     * @param list<string> $fieldNames
     * @param Sport $sport
     * @param Competition $competition
     * @param Structure $structure
     * @return CompetitionSport
     * @throws \Exception
     */
    protected function addHelper(
        PersistVariant $sportPersistVariant,
        array $fieldNames,
        Sport $sport,
        Competition $competition,
        Structure $structure
    ): CompetitionSport {
        $newCompetitionSport = new CompetitionSport(
            $sport,
            $competition,
            $sportPersistVariant
        );
        $smallestNrOfPoulePlaces = $this->getSmallestNrOfPoulePlaces($structure->getRootRound());
        if ($this->tooFewPoulePlaces($smallestNrOfPoulePlaces, $sportPersistVariant->createVariant())) {
            throw new Exception(
                'te weinig poule-plekken om wedstrijden te kunnen plannen, maak de poule(s) groter',
                E_ERROR
            );
        }
        (new CompetitionSportService())->addToStructure($newCompetitionSport, $structure);
        $this->competitionSportRepos->customAdd($newCompetitionSport, $structure);

        foreach ($fieldNames as $fieldName) {
            $field = new Field($newCompetitionSport);
            $field->setName($fieldName);
            $this->fieldRepos->save($field);
        }
        return $newCompetitionSport;
    }

    protected function getSmallestNrOfPoulePlaces(Round $round, int $smallestNrOfPoulePlaces = 0): int
    {
        $smallestNrOfRoundPoulePlaces = min($round->createPouleStructure()->toArray());

        if ($smallestNrOfPoulePlaces === 0 || $smallestNrOfRoundPoulePlaces < $smallestNrOfPoulePlaces) {
            $smallestNrOfPoulePlaces = $smallestNrOfRoundPoulePlaces;
        }
        foreach ($round->getChildren() as $childRound) {
            $smallestNrOfPoulePlaces = $this->getSmallestNrOfPoulePlaces($childRound, $smallestNrOfPoulePlaces);
        }
        return $smallestNrOfPoulePlaces;
    }

    protected function tooFewPoulePlaces(
        int $smallestNrOfPoulePlaces,
        Single|AllInOneGame|AgainstH2h|AgainstGpp $variant
    ): bool {
        if ($variant instanceof AllInOneGame) {
            return false;
        }
        return $smallestNrOfPoulePlaces < $variant->getNrOfGamePlaces();
    }
}
