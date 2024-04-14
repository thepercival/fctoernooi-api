<?php

declare(strict_types=1);

namespace App\Actions\Sports;

use App\Actions\Action;
use App\Exceptions\DomainRecordBeingCalculatedException;
use App\Exceptions\DomainRecordNotFoundException;
use App\GuzzleClient;
use App\Response\ErrorResponse;
use Doctrine\ORM\EntityManagerInterface;
use FCToernooi\CacheService;
use FCToernooi\Competitor\Repository as CompetitorRepository;
use FCToernooi\Planning\Totals\CompetitorAmountCalculator;
use FCToernooi\Planning\Totals\PlanningTotals;
use FCToernooi\Planning\Totals\RoundNumberWithMinNrOfBatches;
use FCToernooi\Planning\Totals\TotalPeriodCalculator;
use FCToernooi\Recess;
use FCToernooi\Tournament;
use FCToernooi\Tournament\Registration\Repository as RegistrationRepository;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\SerializerInterface;
use Memcached;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Selective\Config\Configuration;
use Sports\Category;
use Sports\Output\StructureOutput;
use Sports\Round\Number as RoundNumber;
use Sports\Round\Number\InputConfigurationCreator;
use Sports\Structure;
use Sports\Structure\Copier as StructureCopier;
use Sports\Structure\Repository as StructureRepository;
use Sports\Structure\Validator as StructureValidator;
use SportsPlanning\Referee\Info as PlanningRefereeInfo;
use Sports\Competition\Sport\FromToMapper;
use Sports\Competition\Sport\FromToMapStrategy;
use Sports\Qualify\Rule\Creator as QualifyRuleCreator;
use Sports\Poule\Horizontal\Creator as HorizontalPouleCreator;

final class StructureAction extends Action
{
    private CacheService $cacheService;
    private GuzzleClient $planningClient;

    public function __construct(
        LoggerInterface $logger,
        SerializerInterface $serializer,
        protected StructureRepository $structureRepos,
        protected CompetitorRepository $competitorRepos,
        protected RegistrationRepository $registrationRepos,
        protected EntityManagerInterface $em,
        Memcached $memcached,
        protected Configuration $config
    ) {
        parent::__construct($logger, $serializer);
        $this->cacheService = new CacheService($memcached, $config->getString('namespace'));
        $this->planningClient = new GuzzleClient(
            $config->getString('scheduler.url'),
            $config->getString('scheduler.apikey'),
            $this->cacheService, $serializer, $logger
        );
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

    protected function getPlanningSerializationContext(): SerializationContext
    {
        return SerializationContext::create()->setGroups(['Default', 'noReference']);
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
            if ($json === false || $this->config->getString('environment') === 'development') {
                $structure = $this->structureRepos->getStructure($competition);
                $structureOutput = new StructureOutput($this->logger);
                $structureOutput->output($structure);
//                $this->logger->info('categoryid-map : ' . join(',',
//                        array_map( function(Category $category): string {
//                            return ((string)$category->getId()) . ' - '.$category->getNumber() . '.';
//                        }, $structure->getCategories()
//                        )
//                    )
//                );
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
            $structureOutput = new StructureOutput($this->logger);
//            $this->logger->warning('####### DESER. STRUCTURE ########');
//            $structureOutput->output($structureSer);
//            $this->logger->warning('####### END DESER. STRUCTURE ########');

            /** @var Tournament $tournament */
            $tournament = $request->getAttribute("tournament");

            $competition = $tournament->getCompetition();
            $fromToCategoryMap = null;
            if ($this->structureRepos->hasStructure($competition)) {
                $oldStructure = $this->structureRepos->getStructure($competition);

                $this->logger->warning('####### OLD STRUCTURE ########');
                $structureOutput->output($oldStructure);

                $fromToCategoryMap = $this->getFromToMap($oldStructure, $structureSer);
                $this->structureRepos->remove($competition);
            }

            $competitionSportsSer = $structureSer->getFirstRoundNumber()->getCompetitionSports();
            $fromToMapper = new FromToMapper(
                array_values( $competitionSportsSer->toArray() ),
                array_values( $competition->getSports()->toArray() ),
                FromToMapStrategy::ById
            );

            $structureCopier = new StructureCopier(
                new HorizontalPouleCreator(),
                new QualifyRuleCreator(),
                $fromToMapper
            );

            $newStructure = $structureCopier->copy($structureSer, $competition);
            $this->structureRepos->add($newStructure/*, $roundNumberAsValue*/);

            $this->logger->warning('####### END OLD STRUCTURE ########');

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
            if( $fromToCategoryMap !== null ) {
                $this->registrationRepos->syncRegistrations($tournament, $fromToCategoryMap);
            }
            $conn->commit();

            $this->logger->warning('####### START NEW STRUCTURE ########');
            $structureOutput->output($structure);

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
    public function getPlanningTotals(Request $request, Response $response, array $args): Response
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

            $competitionSportsSer = $structureSer->getFirstRoundNumber()->getCompetitionSports();
            $fromToMapper = new FromToMapper(
                array_values( $competitionSportsSer->toArray() ),
                array_values( $competition->getSports()->toArray() ),
                FromToMapStrategy::ById
            );

            $structureCopier = new StructureCopier(
                new HorizontalPouleCreator(),
                new QualifyRuleCreator(),
                $fromToMapper
            );

            $newStructure = $structureCopier->copy($structureSer, $competition);
            $recesses = array_values($tournament->getRecesses()->toArray());

            $json = '';
            try {
                $roundNumbersWithMinNrOfBatches = array_map( function(RoundNumber $roundNumber): RoundNumberWithMinNrOfBatches {
                    $planningRefereeInfo = new PlanningRefereeInfo($roundNumber->getRefereeInfo());
                    $cfg = (new InputConfigurationCreator())->create($roundNumber, $planningRefereeInfo);
                    $jsonCfg = $this->serializer->serialize($cfg, 'json');
                    return new RoundNumberWithMinNrOfBatches(
                        $roundNumber,
                        $this->planningClient->getMinNrOfBatches($jsonCfg)
                    );
                } , $newStructure->getRoundNumbers() );

                $competitorAmountRange = (new CompetitorAmountCalculator())->calculate(
                    $newStructure->getCategories(),
                    $competition->createSportVariantsWithFields()
                );

                $competitionStartDateTime = $competition->getStartDateTime();
                $recessPeriods = array_map(fn(Recess $recess) => $recess->getPeriod(), $recesses);
                $totalPeriod = (new TotalPeriodCalculator())->calculate(
                    $competitionStartDateTime, $roundNumbersWithMinNrOfBatches, $recessPeriods);

                $planningTotals = new PlanningTotals($totalPeriod, $competitorAmountRange);
                $json = $this->serializer->serialize($planningTotals, 'json');
            } catch( DomainRecordNotFoundException $e ) {
            } catch( DomainRecordBeingCalculatedException $e ) {
            }

            return $this->respondWithJson($response, $json);
        } catch (\Exception $exception) {
            // \SportsPlanning\Exception\NoBestPlanning
            return new ErrorResponse($exception->getMessage(), 422, $this->logger);
        }
    }

    /**
     * @param Structure $oldStructure
     * @param Structure $structureSer
     * @return array<int, int|null>
     */
    private function getFromToMap(Structure $oldStructure, Structure $structureSer): array {
        $categoryMap = [];
        foreach( $oldStructure->getCategories() as $oldCategory ) {
            $category = $this->getCategoryById($structureSer, $oldCategory->getId());
            $categoryMap[$oldCategory->getNumber()] = $category?->getNumber();
        }
        return $categoryMap;
    }

    private function getCategoryById(Structure $structure, string|int|null $id): Category|null {
        foreach( $structure->getCategories() as $category ) {
            if( $category->getId() === $id ) {
                return $category;
            }
        }
        return null;
    }

}
