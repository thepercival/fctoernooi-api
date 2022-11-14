<?php

declare(strict_types=1);

namespace App\Actions\Sports;

use App\Actions\Action;
use App\Response\ErrorResponse;
use Doctrine\ORM\EntityManagerInterface;
use Memcached;
use FCToernooi\CacheService;
use FCToernooi\Competitor\Repository as CompetitorRepository;
use FCToernooi\PlanningInfo\Calculator as PlanningInfoCalculator;
use FCToernooi\Tournament;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\SerializerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Selective\Config\Configuration;
use Sports\Structure;
use Sports\Structure\Copier as StructureCopier;
use Sports\Structure\Repository as StructureRepository;
use Sports\Structure\Validator as StructureValidator;
use SportsPlanning\Input\Repository as InputRepository;

final class StructureAction extends Action
{
    private CacheService $cacheService;

    public function __construct(
        LoggerInterface $logger,
        SerializerInterface $serializer,
        protected StructureRepository $structureRepos,
        protected InputRepository $inputRepos,
        private StructureCopier $structureCopier,
        protected CompetitorRepository $competitorRepos,
        protected EntityManagerInterface $em,
        Memcached $memcached,
        protected Configuration $config
    ) {
        parent::__construct($logger, $serializer);
        $this->cacheService = new CacheService($memcached, $config->getString('namespace'));
    }

    /**
     * @param bool $bWithGames
     * @return list<string>
     */
    protected function getDeserialzeGroups(bool $bWithGames = true): array
    {
        return ['Default', 'structure', 'games'];
    }

    protected function getSerializationContext(): SerializationContext
    {
        return SerializationContext::create()->setGroups(['Default', 'structure', 'games']);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param array<string, int|string> $args
     * @return Response
     */
    public function fetchOne(Request $request, Response $response, array $args): Response
    {
        try {
            /** @var Tournament $tournament */
            $tournament = $request->getAttribute("tournament");

            $competition = $tournament->getCompetition();

            $tournamentId = (int)$tournament->getId();
            $json = $this->cacheService->getStructure($tournamentId);
            if ($json === false /*|| $this->config->getString('environment') === 'development'*/) {
                $structure = $this->structureRepos->getStructure($competition);
                $json = $this->serializer->serialize(
                    $structure,
                    'json',
                    $this->getSerializationContext()
                );
                $this->cacheService->setStructure($tournamentId, $json);
            }
            return $this->respondWithJson($response, $json);
        } catch (\Exception $exception) {
            return new ErrorResponse($exception->getMessage(), 500, $this->logger);
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
        $conn = $this->em->getConnection();
        $conn->beginTransaction();
        try {
            /** @var Structure|false $structureSer */
            $structureSer = $this->deserialize($request, Structure::class, $this->getDeserialzeGroups());
            if ($structureSer === false) {
                throw new \Exception("er kan geen ronde worden gewijzigd o.b.v. de invoergegevens", E_ERROR);
            }

            /** @var Tournament $tournament */
            $tournament = $request->getAttribute("tournament");

            $competition = $tournament->getCompetition();

            if ($this->structureRepos->hasStructure($competition)) {
                $this->structureRepos->remove($competition);
            }
            $newStructure = $this->structureCopier->copy($structureSer, $competition);
            $this->structureRepos->add($newStructure/*, $roundNumberAsValue*/);

            $structureValidator = new StructureValidator();
            $structureValidator->checkValidity($competition, $newStructure, $tournament->getPlaceRanges());

            // $roundNumberAsValue = 1;
//            try {
//                $this->structureRepos->getStructure($competition);
//                $this->structureRepos->removeAndAdd($competition, $newStructure/*, $roundNumberAsValue*/);
//            } catch (NoStructureException $e) {
//                $this->structureRepos->add($newStructure/*, $roundNumberAsValue*/);
//            } catch (Exception $e) {
//                throw new \Exception($e->getMessage(), E_ERROR);
//            }

            $structure = $this->structureRepos->getStructure($competition);
            foreach ($structure->getCategories() as $category) {
                $this->competitorRepos->syncCompetitors($tournament, $category->getRootRound());
            }
            $conn->commit();
            $json = $this->serializer->serialize($structure, 'json', $this->getSerializationContext());
            return $this->respondWithJson($response, $json);
        } catch (\Exception $exception) {
            $conn->rollBack();
            return new ErrorResponse($exception->getMessage(), 422, $this->logger);
        }
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param array<string, int|string> $args
     * @return Response
     */
    public function getPlanningInfo(Request $request, Response $response, array $args): Response
    {
        try {
            /** @var Structure|false $structureSer */
            $structureSer = $this->deserialize($request, Structure::class, $this->getDeserialzeGroups(false));
            if ($structureSer === false) {
                throw new \Exception("de planning-Info kan niet berekend worden o.b.v. de invoergegevens", E_ERROR);
            }

            /** @var Tournament $tournament */
            $tournament = $request->getAttribute("tournament");

            $competition = $tournament->getCompetition();
            $nrOfReferees = $competition->getReferees()->count();

            $newStructure = $this->structureCopier->copy($structureSer, $competition);
            $recesses = array_values($tournament->getRecesses()->toArray());

            $calculator = new PlanningInfoCalculator($this->inputRepos);

            $planningInfo = $calculator->calculate($newStructure, $recesses, $nrOfReferees);
            if ($planningInfo === null) {
                throw new \Exception('unknown planning', E_ERROR);
            }

            $json = $this->serializer->serialize($planningInfo, 'json');
            return $this->respondWithJson($response, $json);
        } catch (\Exception $exception) {
            return new ErrorResponse($exception->getMessage(), 422, $this->logger);
        }
    }
}
