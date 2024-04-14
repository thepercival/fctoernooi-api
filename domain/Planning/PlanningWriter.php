<?php

namespace FCToernooi\Planning;

use Doctrine\ORM\EntityManagerInterface;
use FCToernooi\CacheService;
use FCToernooi\Recess;
use FCToernooi\Tournament;
use Psr\Log\LoggerInterface;
use Sports\Round\Number\PlanningAssigner;
use Sports\Round\Number\PlanningScheduler;
use Sports\Round\Number as RoundNumber;
use Sports\Round\Number\Repository as RoundNumberRepository;

class PlanningWriter
{
    public function __construct(
        private CacheService $cacheService,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger
    ) {
    }


    /**
     * @param Tournament $tournament
     * @param list<RoundNumberWithPlanning> $roundNumbersWithPlanning
     * @return bool
     * @throws \Doctrine\DBAL\Exception
     */
    public function write(
        Tournament $tournament,
        array $roundNumbersWithPlanning): bool // Response
    {
        $conn = $this->entityManager->getConnection();

        $conn->beginTransaction();
        $metaData = $this->entityManager->getClassMetadata(RoundNumber::class);
        $roundNumberRepos = new RoundNumberRepository($this->entityManager, $metaData);
        try {
            $recesses = array_values($tournament->getRecesses()->toArray());
            $recessPeriods = array_map(fn(Recess $recess) => $recess->getPeriod(), $recesses);
            $planningAssigner = new PlanningAssigner(new PlanningScheduler($recessPeriods));
            foreach ($roundNumbersWithPlanning as $roundNumberWithPlanning) {
                $roundNumber = $roundNumberWithPlanning->roundNumber;
                $planning = $roundNumberWithPlanning->planning;
                $planningAssigner->assignPlanningToRoundNumber($roundNumber, $planning );
                $roundNumberRepos->savePlanning($roundNumber);


                $nrOfAgainstGames = 0;
                foreach( $roundNumber->getPoules() as $poule ) {
                    $nrOfAgainstGames += count( $poule->getAgainstGames() );
                }
                $this->logger->info("   roundNumber " . $roundNumber->getNumber() . " has " . $nrOfAgainstGames . " againstGames");
                // $this->entityManager->persist($roundNumberWithPlanning->roundNumber);
            }
            $conn->commit();
            $this->cacheService->removeCompetitionIdWithoutPlanning($tournament->getCompetition()->getId());

            // $json = $this->serializer->serialize($newTournament->getId(), 'json');
            // return $this->respondWithJson($response, $json);
        } catch (\Exception $exception) {
            $conn->rollBack();
            $this->logger->error($exception->getMessage());
            // throw new HttpException($request, $exception->getMessage(), 422);
            return false;
        }
        return true;
    }
}